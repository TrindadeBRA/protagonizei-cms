<?php
/**
 * Webhook endpoint for validating FAL.AI Nano Banana Pro completion (Modo Síncrono)
 * 
 * This endpoint verifies orders with status 'created_assets_text' and falai_initiated = true,
 * and ensures all pages have been processed successfully. In sync mode, this is mainly
 * a safety check as images are generated immediately in the initiate webhook.
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
    register_rest_route('trinitykitcms-api/v1', '/webhook/check-falai', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_check_falai_webhook',
    ));
});

/**
 * Send Telegram notification for errors
 */
function send_telegram_error_notification_check_falai($message, $title = "Erro no FAL.AI Check") {
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
        error_log("[TrinityKit FAL.AI] Erro ao enviar notificação Telegram: " . $e->getMessage());
    }
}

/**
 * Download and save image from URL to WordPress with professional metadata
 * 
 * @param string $image_url URL da imagem para download
 * @param int $order_id ID do pedido
 * @param string $child_name Nome da criança
 * @param int $page_index Índice da página (0-based)
 * @return string|false URL da imagem salva ou false em caso de erro
 */
function save_falai_image_from_url_to_wordpress($image_url, $order_id = null, $child_name = '', $page_index = 0) {
    // Check if it's a Base64 data URI (FAL.AI modo síncrono retorna Base64)
    if (strpos($image_url, 'data:image/') === 0) {
        // Extract Base64 data from data URI
        // Format: data:image/png;base64,iVBORw0KGgo...
        $parts = explode(',', $image_url, 2);
        
        if (count($parts) !== 2) {
            error_log("[TrinityKit FAL.AI] Formato de data URI inválido");
            return false;
        }
        
        $image_data = base64_decode($parts[1]);
        
        if ($image_data === false) {
            error_log("[TrinityKit FAL.AI] Erro ao decodificar Base64");
            return false;
        }
        
        if (empty($image_data)) {
            error_log("[TrinityKit FAL.AI] Dados da imagem Base64 vazios");
            return false;
        }
        
        error_log("[TrinityKit FAL.AI] Imagem Base64 decodificada com sucesso (" . strlen($image_data) . " bytes)");
    } else {
        // Download the image from HTTP URL
        $response = wp_remote_get($image_url);
        
        if (is_wp_error($response)) {
            error_log("[TrinityKit FAL.AI] Erro ao baixar imagem: " . $response->get_error_message());
            return false;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            error_log("[TrinityKit FAL.AI] Erro HTTP ao baixar imagem: $http_code");
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        
        if (empty($image_data)) {
            error_log("[TrinityKit FAL.AI] Dados da imagem vazios");
            return false;
        }
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
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    // Save file
    $file_saved = file_put_contents($file_path, $image_data);
    
    if ($file_saved === false) {
        error_log("[TrinityKit FAL.AI] Erro ao salvar arquivo de imagem");
        return false;
    }
    
    // Prepare professional data for WordPress insertion
    $file_type = wp_check_filetype($filename, null);
    
    $professional_title = $child_name ? 
        "Ilustração FAL.AI - {$child_name} - Página {$page_number}" : 
        "Ilustração FAL.AI - Página {$page_number}";
    
    $professional_content = $order_id ? 
        "Ilustração personalizada gerada via FAL.AI Nano Banana Pro para o pedido #{$order_id}. Criança: {$child_name}. Página {$page_number} do livro personalizado. Campo unificado: generated_illustration." :
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
        error_log("[TrinityKit FAL.AI] Erro ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    // Add professional metadata
    if ($order_id) {
        update_post_meta($attach_id, '_trinitykitcms_order_id', $order_id);
        update_post_meta($attach_id, '_trinitykitcms_child_name', $child_name);
        update_post_meta($attach_id, '_trinitykitcms_page_number', $page_number);
        update_post_meta($attach_id, '_trinitykitcms_page_index', $page_index);
        update_post_meta($attach_id, '_trinitykitcms_generation_method', 'falai_nano_banana_pro');
        update_post_meta($attach_id, '_trinitykitcms_generation_date', current_time('mysql'));
        update_post_meta($attach_id, '_trinitykitcms_file_source', 'webhook_check_falai');
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
    
    return wp_get_attachment_url($attach_id);
}

/**
 * Handles the check FAL.AI webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_check_falai_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    
    // Get all orders with status 'created_assets_text' and falai_initiated = true
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'order_status',
                'value' => 'created_assets_text',
                'compare' => '='
            ),
            array(
                'key' => 'falai_initiated',
                'value' => '1',
                'compare' => '='
            )
        )
    );

    $orders = get_posts($args);
    $processed = 0;
    $errors = array();

    foreach ($orders as $order) {
        $order_id = $order->ID;
        
        // Get order details
        $child_name = get_field('child_name', $order_id);
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        if (empty($generated_pages)) {
            $error_msg = "Páginas geradas não encontradas para pedido #$order_id";
            error_log("[TrinityKit FAL.AI Check] $error_msg");
            $errors[] = $error_msg;
            continue;
        }

        $completed_pages = 0;
        $pending_pages = 0;
        
        // Verificar se todas as páginas têm ilustração (modo síncrono - campo unificado)
        foreach ($generated_pages as $index => $page) {
            // Check if this page already has a generated illustration
            if (!empty($page['generated_illustration'])) {
                $completed_pages++;
            } else {
                // Check if this page should skip FAL.AI processing
                $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
                
                if ($skip_faceswap) {
                    // Tentar copiar a ilustração base para páginas que pulam processamento
                    $book_template = get_field('book_template', $order_id);
                    if (!$book_template) {
                        $pending_pages++;
                        continue;
                    }
                    
                    $template_pages = get_field('template_book_pages', $book_template->ID);
                    if (empty($template_pages[$index])) {
                        $pending_pages++;
                        continue;
                    }
                    
                    $template_page = $template_pages[$index];
                    $base_illustrations = $template_page['base_illustrations'] ?? array();
                    $child_gender = strtolower(trim((string) get_field('child_gender', $order_id)));
                    $child_skin_tone = strtolower(trim((string) get_field('child_skin_tone', $order_id)));
                    
                    $base_image = null;
                    foreach ($base_illustrations as $illustration) {
                        $ill_gender = strtolower(trim((string) ($illustration['gender'] ?? '')));
                        $ill_skin = strtolower(trim((string) ($illustration['skin_tone'] ?? '')));

                        if ($ill_gender === $child_gender && $ill_skin === $child_skin_tone) {
                            $base_image = $illustration['illustration_asset'];
                            break;
                        }
                    }
                    
                    if (!empty($base_image) && !empty($base_image['ID'])) {
                        // Copy the base illustration to generated_illustration (campo unificado)
                        $field_key = "generated_book_pages_{$index}_generated_illustration";
                        $update_result = update_field($field_key, $base_image['ID'], $order_id);
                        
                        if ($update_result) {
                            $completed_pages++;
                        } else {
                            $pending_pages++;
                        }
                    } else {
                        $pending_pages++;
                    }
                } else {
                    $pending_pages++;
                }
            }
        }

        // Check if all pages are completed
        $total_pages = count($generated_pages);
        if ($completed_pages === $total_pages) {
            // Update order status to 'created_assets_illustration'
            $status_updated = update_field('order_status', 'created_assets_illustration', $order_id);
            
            if ($status_updated) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-check-falai',
                    "Ilustrações com FAL.AI validadas para $child_name ($completed_pages páginas)",
                    'created_assets_text',
                    'created_assets_illustration'
                );
                
                $processed++;
            } else {
                $error_msg = "Falha ao atualizar status do pedido #$order_id";
                error_log("[TrinityKit FAL.AI Check] $error_msg");
                $errors[] = $error_msg;
                send_telegram_error_notification_check_falai("Pedido #$order_id: $error_msg");
            }
        } else {
            $error_msg = "Pedido #$order_id ainda tem $pending_pages páginas pendentes";
            error_log("[TrinityKit FAL.AI Check] $error_msg");
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit FAL.AI] Erros encontrados: " . implode(" | ", $errors));
    }
    
    return new WP_REST_Response(array(
        'message' => "Validação de ilustrações FAL.AI concluída. {$processed} pedidos validados e finalizados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}

