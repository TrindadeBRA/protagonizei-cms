<?php
/**
 * Webhook endpoint exclusivo para receber callbacks do FAL.AI
 * 
 * Este endpoint é chamado automaticamente pelo FAL.AI quando uma imagem é concluída.
 * 
 * FLUXO:
 * 1. FAL.AI chama este endpoint com o payload contendo request_id e dados da imagem
 * 2. Buscamos qual pedido/página corresponde ao request_id
 * 3. Baixamos e salvamos a imagem no WordPress
 * 4. Atualizamos o campo ACF generated_illustration
 * 5. Se todas as páginas estiverem completas, atualizamos o status do pedido
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Include TelegramService if needed
require_once get_template_directory() . '/includes/integrations.php';

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/falai-callback', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_handle_falai_callback',
        'permission_callback' => '__return_true' // FAL.AI não envia API key
    ));
});

/**
 * Handles FAL.AI webhook callback (automatic notification when image is ready)
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_falai_callback($request) {
    error_log("[TrinityKit FAL.AI CALLBACK] ========== INÍCIO DO CALLBACK ==========");
    
    $body = $request->get_body();
    $payload = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = "Erro ao decodificar JSON: " . json_last_error_msg();
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: $error_msg");
        return new WP_REST_Response(array(
            'error' => 'Invalid JSON',
            'message' => $error_msg
        ), 400);
    }
    
    // Extrair request_id do payload
    $request_id = $payload['request_id'] ?? null;
    
    error_log("[TrinityKit FAL.AI CALLBACK] Request ID extraído: " . ($request_id ?? 'NULL'));
    
    if (empty($request_id)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: request_id não encontrado no payload");
        return new WP_REST_Response(array(
            'error' => 'No request_id in payload'
        ), 400);
    }
    
    // Buscar qual pedido e página tem esse request_id
    error_log("[TrinityKit FAL.AI CALLBACK] Buscando pedido com request_id: $request_id");
    
    $orders = get_posts(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ));
    
    $found_order = null;
    $found_page_index = null;
    
    foreach ($orders as $order) {
        $order_id = $order->ID;
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        if (empty($generated_pages)) {
            continue;
        }
        
        foreach ($generated_pages as $index => $page) {
            $page_task_id = $page['falai_task_id'] ?? null;
            
            if ($page_task_id === $request_id) {
                $found_order = $order_id;
                $found_page_index = $index;
                error_log("[TrinityKit FAL.AI CALLBACK] ✓ ENCONTRADO! Pedido: #$order_id, Página: $index");
                break 2;
            }
        }
    }
    
    if (!$found_order) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Pedido não encontrado para request_id: $request_id");
        return new WP_REST_Response(array(
            'error' => 'Order not found for request_id',
            'request_id' => $request_id
        ), 404);
    }
    
    // Verificar se já tem ilustração (evitar duplicação)
    $generated_pages = get_field('generated_book_pages', $found_order);
    $page = $generated_pages[$found_page_index] ?? null;
    
    if (!empty($page['generated_illustration'])) {
        error_log("[TrinityKit FAL.AI CALLBACK] Página já tem ilustração. Ignorando callback.");
        return new WP_REST_Response(array(
            'message' => 'Page already has illustration',
            'order_id' => $found_order,
            'page_index' => $found_page_index
        ), 200);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Extraindo URL da imagem...");
    
    // FORMATO ESPERADO (nano-banana-multi-edit):
    // {
    //   "images": [
    //     {
    //       "file_name": "nano-banana-multi-edit-output.png",
    //       "content_type": "image/png",
    //       "url": "https://..."
    //     }
    //   ],
    //   "description": ""
    // }
    
    $image_url_to_download = null;
    
    // Extrair URL da imagem do payload
    if (isset($payload['images']) && is_array($payload['images']) && !empty($payload['images'][0])) {
        $first_image = $payload['images'][0];
        if (is_array($first_image) && isset($first_image['url'])) {
            $image_url_to_download = $first_image['url'];
            error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em payload->images[0]->url");
        }
    }
    
    if (empty($image_url_to_download)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: URL da imagem não encontrada no payload");
        return new WP_REST_Response(array(
            'error' => 'No image URL in payload'
        ), 400);
    }
    
    // Baixar e salvar a imagem
    error_log("[TrinityKit FAL.AI CALLBACK] Iniciando download da imagem...");
    $child_name = get_field('child_name', $found_order);
    $saved_image_url = save_falai_callback_image($image_url_to_download, $found_order, $child_name, $found_page_index);
    
    if (!$saved_image_url) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Falha ao salvar imagem");
        send_telegram_error_notification_callback(
            "Falha ao salvar imagem do callback\n" .
            "Pedido: #$found_order\n" .
            "Página: " . ($found_page_index + 1) . "\n" .
            "Request ID: $request_id",
            "Callback FAL.AI - Erro ao salvar"
        );
        return new WP_REST_Response(array(
            'error' => 'Failed to save image'
        ), 500);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Imagem salva com sucesso: $saved_image_url");
    
    // Atualizar o campo generated_illustration no ACF
    $attachment_id = attachment_url_to_postid($saved_image_url);
    if (!$attachment_id) {
        $attachment_id = 0;
    }
    
    $field_key = "generated_book_pages_{$found_page_index}_generated_illustration";
    $update_result = update_field($field_key, $attachment_id, $found_order);
    
    if (!$update_result) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Falha ao atualizar campo ACF");
        send_telegram_error_notification_callback(
            "Falha ao atualizar ACF do callback\n" .
            "Pedido: #$found_order\n" .
            "Página: " . ($found_page_index + 1) . "\n" .
            "Request ID: $request_id",
            "Callback FAL.AI - Erro ACF"
        );
        return new WP_REST_Response(array(
            'error' => 'Failed to update ACF field'
        ), 500);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] SUCESSO! Pedido #$found_order, Página $found_page_index");
    
    // Verificar se todas as páginas do pedido estão completas
    $generated_pages = get_field('generated_book_pages', $found_order);
    $all_complete = true;
    $completed_count = 0;
    
    foreach ($generated_pages as $idx => $pg) {
        $skip_faceswap = !empty($pg['skip_faceswap']) && $pg['skip_faceswap'] === true;
        
        if ($skip_faceswap) {
            $completed_count++;
            continue;
        }
        
        if (empty($pg['generated_illustration'])) {
            $all_complete = false;
        } else {
            $completed_count++;
        }
    }
    
    // Se todas as páginas estão completas, atualizar status do pedido
    if ($all_complete) {
        update_field('order_status', 'created_assets_illustration', $found_order);
        trinitykit_add_post_log(
            $found_order,
            'webhook-falai-callback',
            "Ilustrações com FAL.AI geradas para $child_name ($completed_count páginas) - concluído via callback",
            'created_assets_text',
            'created_assets_illustration'
        );
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] ========== FIM DO CALLBACK ==========");
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Image processed successfully',
        'order_id' => $found_order,
        'page_index' => $found_page_index,
        'request_id' => $request_id,
        'all_complete' => $all_complete
    ), 200);
}

/**
 * Download and save image from URL to WordPress
 */
function save_falai_callback_image($image_url, $order_id = null, $child_name = '', $page_index = 0) {
    // Download the image from HTTP URL
    $response = wp_remote_get($image_url);
    
    if (is_wp_error($response)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO ao baixar imagem: " . $response->get_error_message());
        return false;
    }
    
    if (wp_remote_retrieve_response_code($response) !== 200) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO HTTP ao baixar imagem");
        return false;
    }
    
    $image_data = wp_remote_retrieve_body($response);
    
    if (empty($image_data)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Dados da imagem vazios");
        return false;
    }
    
    // Generate filename
    $page_number = $page_index + 1;
    $sanitized_child_name = sanitize_file_name($child_name);
    $timestamp = date('Y-m-d_H-i-s');
    
    if ($order_id && $sanitized_child_name) {
        $filename = "falai-pedido-{$order_id}-{$sanitized_child_name}-pagina-{$page_number}-{$timestamp}.jpg";
    } else {
        $filename = "falai-{$timestamp}-" . uniqid() . ".jpg";
    }
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    // Save file
    if (file_put_contents($file_path, $image_data) === false) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO ao salvar arquivo de imagem");
        return false;
    }
    
    // Prepare data for WordPress insertion
    $file_type = wp_check_filetype($filename, null);
    
    $title = $child_name ? 
        "Ilustração FAL.AI - {$child_name} - Página {$page_number}" : 
        "Ilustração FAL.AI - Página {$page_number}";
    
    $content = $order_id ? 
        "Ilustração personalizada gerada via FAL.AI Nano Banana Pro para o pedido #{$order_id}. Criança: {$child_name}. Página {$page_number} do livro personalizado." :
        "Ilustração personalizada gerada via FAL.AI.";
    
    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => $title,
        'post_content' => $content,
        'post_excerpt' => "FAL.AI - Pedido #{$order_id} - {$child_name} - Página {$page_number}",
        'post_status' => 'inherit'
    );
    
    // Insert into WordPress
    $attach_id = wp_insert_attachment($attachment, $file_path);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    // Add metadata
    if ($order_id) {
        update_post_meta($attach_id, '_trinitykitcms_order_id', $order_id);
        update_post_meta($attach_id, '_trinitykitcms_child_name', $child_name);
        update_post_meta($attach_id, '_trinitykitcms_page_number', $page_number);
        update_post_meta($attach_id, '_trinitykitcms_page_index', $page_index);
        update_post_meta($attach_id, '_trinitykitcms_generation_method', 'falai_nano_banana_pro_callback');
        update_post_meta($attach_id, '_trinitykitcms_generation_date', current_time('mysql'));
        update_post_meta($attach_id, '_trinitykitcms_file_source', 'webhook_falai_callback');
        update_post_meta($attach_id, '_trinitykitcms_original_url', $image_url);
    }
    
    // Add ALT text
    $alt_text = $child_name ? 
        "Ilustração personalizada de {$child_name} na página {$page_number}" :
        "Ilustração personalizada página {$page_number}";
    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
    
    // Generate image sizes
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    return wp_get_attachment_url($attach_id);
}

/**
 * Send Telegram notification for errors
 */
function send_telegram_error_notification_callback($message, $title = "Erro no FAL.AI Callback") {
    try {
        $telegram = new TelegramService();
        if ($telegram->isConfigured()) {
            $error_message = "🚨 <b>$title</b>\n\n";
            $error_message .= $message;
            $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
            $error_message .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
            
            $telegram->sendTextMessage($error_message);
        }
    } catch (Exception $e) {
        error_log("[TrinityKit FAL.AI CALLBACK] Erro ao enviar notificação Telegram: " . $e->getMessage());
    }
}



