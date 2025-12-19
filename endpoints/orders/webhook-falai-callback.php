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
 * Log estruturado para análise de performance
 */
if (!function_exists('log_falai_callback_performance')) {
    function log_falai_callback_performance($action, $data = array()) {
        $timestamp = date('Y-m-d H:i:s');
        $log_data = array_merge([
            'timestamp' => $timestamp,
            'action' => $action
        ], $data);
        error_log("[TrinityKit FAL.AI CALLBACK PERFORMANCE] " . json_encode($log_data, JSON_UNESCAPED_SLASHES));
    }
}

/**
 * Handles FAL.AI webhook callback (automatic notification when image is ready)
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_falai_callback($request) {
    $callback_start = microtime(true);
    
    error_log("[TrinityKit FAL.AI CALLBACK] ========== INÍCIO DO CALLBACK ==========");
    error_log("[TrinityKit FAL.AI CALLBACK] Método: " . $request->get_method());
    error_log("[TrinityKit FAL.AI CALLBACK] Headers: " . json_encode($request->get_headers()));
    
    // Pegar o body da requisição
    $body = $request->get_body();
    error_log("[TrinityKit FAL.AI CALLBACK] Body raw (primeiros 2000 chars): " . substr($body, 0, 2000));
    
    $payload = json_decode($body, true);
    
    log_falai_callback_performance('callback_received', [
        'payload_size' => strlen($body),
        'method' => $request->get_method()
    ]);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = "Erro ao decodificar JSON: " . json_last_error_msg();
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: $error_msg");
        log_falai_callback_performance('callback_error', [
            'error' => 'json_decode_error',
            'error_message' => json_last_error_msg()
        ]);
        return new WP_REST_Response(array(
            'error' => 'Invalid JSON',
            'message' => $error_msg
        ), 400);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Payload decodificado: " . json_encode($payload, JSON_UNESCAPED_SLASHES));
    
    // Extrair request_id do payload
    $request_id = $payload['request_id'] ?? null;
    $status = $payload['status'] ?? null;
    
    error_log("[TrinityKit FAL.AI CALLBACK] Request ID extraído: " . ($request_id ?? 'NULL'));
    error_log("[TrinityKit FAL.AI CALLBACK] Status extraído: " . ($status ?? 'NULL'));
    
    if (empty($request_id)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: request_id não encontrado no payload");
        error_log("[TrinityKit FAL.AI CALLBACK] Chaves disponíveis: " . implode(', ', array_keys($payload)));
        log_falai_callback_performance('callback_error', [
            'error' => 'no_request_id',
            'payload_keys' => array_keys($payload)
        ]);
        return new WP_REST_Response(array(
            'error' => 'No request_id in payload',
            'available_keys' => array_keys($payload)
        ), 400);
    }
    
    // Buscar qual pedido e página tem esse request_id
    error_log("[TrinityKit FAL.AI CALLBACK] Buscando pedido com request_id: $request_id");
    
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'any'
    );
    
    $orders = get_posts($args);
    error_log("[TrinityKit FAL.AI CALLBACK] Total de pedidos encontrados: " . count($orders));
    
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
        log_falai_callback_performance('callback_error', [
            'error' => 'order_not_found',
            'request_id' => $request_id
        ]);
        return new WP_REST_Response(array(
            'error' => 'Order not found for request_id',
            'request_id' => $request_id
        ), 404);
    }
    
    // Verificar se já tem ilustração (evitar duplicação)
    $generated_pages = get_field('generated_book_pages', $found_order);
    $page = $generated_pages[$found_page_index] ?? null;
    
    if (!empty($page['generated_illustration'])) {
        error_log("[TrinityKit FAL.AI CALLBACK] ℹ️ Página já tem ilustração. Ignorando callback.");
        log_falai_callback_performance('callback_ignored', [
            'order_id' => $found_order,
            'page_index' => $found_page_index,
            'reason' => 'already_has_illustration'
        ]);
        return new WP_REST_Response(array(
            'message' => 'Page already has illustration',
            'order_id' => $found_order,
            'page_index' => $found_page_index
        ), 200);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Status do callback: $status");
    
    // Processar apenas se status for COMPLETED ou SUCCESS
    if ($status !== 'COMPLETED' && $status !== 'SUCCESS') {
        error_log("[TrinityKit FAL.AI CALLBACK] ⚠️ Status não é COMPLETED/SUCCESS: $status");
        log_falai_callback_performance('callback_status_not_ready', [
            'order_id' => $found_order,
            'page_index' => $found_page_index,
            'status' => $status,
            'request_id' => $request_id
        ]);
        
        // Se falhou, enviar notificação
        if ($status === 'FAILED' || $status === 'ERROR') {
            error_log("[TrinityKit FAL.AI CALLBACK] ❌ FALHA no processamento!");
            $child_name = get_field('child_name', $found_order);
            $telegram_msg = "🚨 <b>FAL.AI CALLBACK: FALHA</b> 🚨\n\n";
            $telegram_msg .= "❌ Status: $status\n";
            $telegram_msg .= "👶 Criança: " . htmlspecialchars($child_name) . "\n";
            $telegram_msg .= "📄 Página: " . ($found_page_index + 1) . "\n";
            $telegram_msg .= "🔢 Pedido: #$found_order\n";
            $telegram_msg .= "🆔 Request ID: $request_id";
            
            send_telegram_error_notification_callback($telegram_msg, "Callback FAL.AI - Falha");
        }
        
        return new WP_REST_Response(array(
            'message' => 'Status not completed',
            'status' => $status,
            'order_id' => $found_order,
            'page_index' => $found_page_index
        ), 200);
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] ✓ Status COMPLETED/SUCCESS. Extraindo URL da imagem...");
    
    // Extrair URL da imagem do payload
    $image_url_to_download = null;
    $output = $payload['output'] ?? $payload;
    
    error_log("[TrinityKit FAL.AI CALLBACK] Estrutura do output: " . json_encode($output, JSON_UNESCAPED_SLASHES));
    
    // Tentar várias formas de extrair a URL da imagem
    if (isset($output['images']) && is_array($output['images']) && !empty($output['images'][0])) {
        if (is_array($output['images'][0])) {
            $image_url_to_download = $output['images'][0]['url'] ?? null;
            error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em output->images[0]->url");
        } else {
            $image_url_to_download = $output['images'][0];
            error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em output->images[0] (string direto)");
        }
    } elseif (isset($output['image']['url'])) {
        $image_url_to_download = $output['image']['url'];
        error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em output->image->url");
    } elseif (isset($output['image_url'])) {
        $image_url_to_download = $output['image_url'];
        error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em output->image_url");
    } elseif (isset($output['image']) && is_string($output['image'])) {
        $image_url_to_download = $output['image'];
        error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada em output->image (string)");
    } elseif (isset($payload['response_url'])) {
        error_log("[TrinityKit FAL.AI CALLBACK] response_url encontrado, tentando buscar dados...");
        // Se tiver response_url, precisamos fazer requisição para pegar a imagem
        $response_url = $payload['response_url'];
        $falai_api_key = get_option('trinitykitcms_falai_api_key');
        
        $response = wp_remote_get($response_url, array(
            'headers' => array(
                'Authorization' => 'Key ' . trim($falai_api_key),
                'Accept' => 'application/json'
            )
        ));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            error_log("[TrinityKit FAL.AI CALLBACK] Dados do response_url: " . json_encode($response_data, JSON_UNESCAPED_SLASHES));
            
            if (isset($response_data['images']) && is_array($response_data['images']) && !empty($response_data['images'][0])) {
                $image_url_to_download = $response_data['images'][0]['url'] ?? $response_data['images'][0];
                error_log("[TrinityKit FAL.AI CALLBACK] URL encontrada via response_url");
            }
        }
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] URL final da imagem: " . ($image_url_to_download ?? 'NULL'));
    
    if (empty($image_url_to_download)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: URL da imagem não encontrada no payload");
        log_falai_callback_performance('callback_error', [
            'order_id' => $found_order,
            'page_index' => $found_page_index,
            'error' => 'no_image_url',
            'request_id' => $request_id
        ]);
        return new WP_REST_Response(array(
            'error' => 'No image URL in payload',
            'payload_structure' => array_keys($payload)
        ), 400);
    }
    
    // Baixar e salvar a imagem
    error_log("[TrinityKit FAL.AI CALLBACK] Iniciando download da imagem...");
    $child_name = get_field('child_name', $found_order);
    $download_start = microtime(true);
    $saved_image_url = save_falai_callback_image($image_url_to_download, $found_order, $child_name, $found_page_index);
    $download_time = microtime(true) - $download_start;
    
    error_log("[TrinityKit FAL.AI CALLBACK] Download concluído em " . round($download_time, 3) . "s");
    
    if (!$saved_image_url) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Falha ao salvar imagem");
        log_falai_callback_performance('callback_error', [
            'order_id' => $found_order,
            'page_index' => $found_page_index,
            'error' => 'save_image_failed',
            'download_time' => round($download_time, 3),
            'request_id' => $request_id
        ]);
        
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
    
    error_log("[TrinityKit FAL.AI CALLBACK] ✓ Imagem salva com sucesso: $saved_image_url");
    
    // Atualizar o campo generated_illustration no ACF
    error_log("[TrinityKit FAL.AI CALLBACK] Atualizando campo ACF...");
    $acf_start = microtime(true);
    $attachment_id = attachment_url_to_postid($saved_image_url);
    if (!$attachment_id) {
        $attachment_id = 0;
        error_log("[TrinityKit FAL.AI CALLBACK] ⚠️ Attachment ID não encontrado, usando 0");
    } else {
        error_log("[TrinityKit FAL.AI CALLBACK] ✓ Attachment ID: $attachment_id");
    }
    
    $field_key = "generated_book_pages_{$found_page_index}_generated_illustration";
    $update_result = update_field($field_key, $attachment_id, $found_order);
    $acf_time = microtime(true) - $acf_start;
    
    error_log("[TrinityKit FAL.AI CALLBACK] ACF update result: " . ($update_result ? 'SUCCESS' : 'FAILED'));
    
    if (!$update_result) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Falha ao atualizar campo ACF");
        log_falai_callback_performance('callback_error', [
            'order_id' => $found_order,
            'page_index' => $found_page_index,
            'error' => 'acf_update_failed',
            'download_time' => round($download_time, 3),
            'acf_time' => round($acf_time, 3),
            'request_id' => $request_id
        ]);
        
        send_telegram_error_notification_callback(
            "Falha ao atualizar ACF do callback\n" .
            "Pedido: #$found_order\n" .
            "Página: " . ($found_page_index + 1) . "\n" .
            "Request ID: $request_id\n" .
            "Fazer upload manual!",
            "Callback FAL.AI - Erro ACF"
        );
        
        return new WP_REST_Response(array(
            'error' => 'Failed to update ACF field'
        ), 500);
    }
    
    $callback_time = microtime(true) - $callback_start;
    
    error_log("[TrinityKit FAL.AI CALLBACK] ✓✓✓ SUCESSO TOTAL! Pedido #$found_order, Página $found_page_index");
    log_falai_callback_performance('callback_success', [
        'order_id' => $found_order,
        'page_index' => $found_page_index,
        'request_id' => $request_id,
        'download_time' => round($download_time, 3),
        'acf_time' => round($acf_time, 3),
        'callback_time' => round($callback_time, 3)
    ]);
    
    // Verificar se todas as páginas do pedido estão completas
    error_log("[TrinityKit FAL.AI CALLBACK] Verificando se todas as páginas estão completas...");
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
            error_log("[TrinityKit FAL.AI CALLBACK] Página $idx ainda pendente");
        } else {
            $completed_count++;
        }
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Páginas completas: $completed_count / " . count($generated_pages));
    
    // Se todas as páginas estão completas, atualizar status do pedido
    if ($all_complete) {
        error_log("[TrinityKit FAL.AI CALLBACK] 🎉 TODAS AS PÁGINAS COMPLETAS! Atualizando status do pedido...");
        $status_updated = update_field('order_status', 'created_assets_illustration', $found_order);
        
        if ($status_updated) {
            trinitykit_add_post_log(
                $found_order,
                'webhook-falai-callback',
                "Ilustrações com FAL.AI geradas para $child_name ($completed_count páginas) - concluído via callback",
                'created_assets_text',
                'created_assets_illustration'
            );
            
            error_log("[TrinityKit FAL.AI CALLBACK] ✓ Status do pedido atualizado para 'created_assets_illustration'");
            log_falai_callback_performance('callback_order_completed', [
                'order_id' => $found_order,
                'child_name' => $child_name,
                'completed_count' => $completed_count
            ]);
        } else {
            error_log("[TrinityKit FAL.AI CALLBACK] ⚠️ Falha ao atualizar status do pedido");
        }
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
    error_log("[TrinityKit FAL.AI CALLBACK] save_falai_callback_image() iniciado");
    error_log("[TrinityKit FAL.AI CALLBACK] URL: $image_url");
    
    // Download the image from HTTP URL
    $response = wp_remote_get($image_url);
    
    if (is_wp_error($response)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO wp_remote_get: " . $response->get_error_message());
        return false;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    error_log("[TrinityKit FAL.AI CALLBACK] HTTP code do download: $http_code");
    
    if ($http_code !== 200) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO HTTP ao baixar imagem: $http_code");
        return false;
    }
    
    $image_data = wp_remote_retrieve_body($response);
    $image_size = strlen($image_data);
    error_log("[TrinityKit FAL.AI CALLBACK] Tamanho da imagem baixada: $image_size bytes");
    
    if (empty($image_data)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO: Dados da imagem vazios");
        return false;
    }
    
    // Generate professional filename
    $page_number = $page_index + 1;
    $sanitized_child_name = sanitize_file_name($child_name);
    $timestamp = date('Y-m-d_H-i-s');
    
    if ($order_id && $sanitized_child_name) {
        $filename = "falai-pedido-{$order_id}-{$sanitized_child_name}-pagina-{$page_number}-{$timestamp}.jpg";
    } else {
        $filename = "falai-{$timestamp}-" . uniqid() . ".jpg";
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] Nome do arquivo: $filename");
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    error_log("[TrinityKit FAL.AI CALLBACK] Caminho completo: $file_path");
    
    // Save file
    $file_saved = file_put_contents($file_path, $image_data);
    
    if ($file_saved === false) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO ao salvar arquivo de imagem");
        return false;
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] ✓ Arquivo salvo: $file_saved bytes");
    
    // Prepare professional data for WordPress insertion
    $file_type = wp_check_filetype($filename, null);
    
    $professional_title = $child_name ? 
        "Ilustração FAL.AI - {$child_name} - Página {$page_number}" : 
        "Ilustração FAL.AI - Página {$page_number}";
    
    $professional_content = $order_id ? 
        "Ilustração personalizada gerada via FAL.AI Nano Banana Pro para o pedido #{$order_id}. Criança: {$child_name}. Página {$page_number} do livro personalizado." :
        "Ilustração personalizada gerada via FAL.AI.";
    
    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => $professional_title,
        'post_content' => $professional_content,
        'post_excerpt' => "FAL.AI - Pedido #{$order_id} - {$child_name} - Página {$page_number}",
        'post_status' => 'inherit'
    );
    
    // Insert into WordPress
    $attach_id = wp_insert_attachment($attachment, $file_path);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit FAL.AI CALLBACK] ERRO ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    error_log("[TrinityKit FAL.AI CALLBACK] ✓ Attachment criado com ID: $attach_id");
    
    // Add professional metadata
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
    
    // Add ALT text for accessibility
    $alt_text = $child_name ? 
        "Ilustração personalizada de {$child_name} na página {$page_number}" :
        "Ilustração personalizada página {$page_number}";
    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
    
    // Generate image sizes
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    $final_url = wp_get_attachment_url($attach_id);
    error_log("[TrinityKit FAL.AI CALLBACK] ✓ URL final da imagem no WordPress: $final_url");
    
    return $final_url;
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



