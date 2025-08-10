<?php
/**
 * Webhook endpoint for checking face swap status and processing completed images
 * 
 * This endpoint finds orders with status 'created_assets_text' and face_swap_initiated = true,
 * checks the status of face swap tasks, and processes completed images
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
    register_rest_route('trinitykitcms-api/v1', '/webhook/check-faceswap', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_check_faceswap_webhook',
    ));
});

/**
 * Check face swap status with FaceSwap API
 * 
 * @param string $task_id ID da tarefa de face swap
 * @return array|false Array com status e dados ou false em caso de erro
 */
function check_face_swap_status($task_id) {
    $api_key = get_option('trinitykitcms_faceswap_api_key');
    $base_url = get_option('trinitykitcms_faceswap_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit] Configurações do FaceSwap não encontradas");
        return false;
    }

    $status_url = $base_url . '/status/' . $task_id;
    
    $headers = [
        'Content-Type: application/json',
        'x-magicapi-key: ' . $api_key
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $status_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("[TrinityKit] Erro cURL na verificação de status: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("[TrinityKit] Erro na verificação de status ($http_code): " . $response);
        return false;
    }
    
    $status_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit] Resposta JSON inválida na verificação de status: " . json_last_error_msg());
        return false;
    }
    
    return $status_data;
}

/**
 * Save base64 image to WordPress with professional metadata
 * 
 * @param string $base64_image String base64 da imagem
 * @param int $order_id ID do pedido
 * @param string $child_name Nome da criança
 * @param int $page_index Índice da página (0-based)
 * @return array|false Array com 'id' e 'url' do attachment salvo ou false em caso de erro
 */
function save_base64_image_to_wordpress($base64_image, $order_id = null, $child_name = '', $page_index = 0) {
    // Remove the data:image/jpeg;base64, prefix if it exists
    if (strpos($base64_image, 'data:image/') === 0) {
        $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
    }
    
    $image_data = base64_decode($base64_image);
    
    if ($image_data === false) {
        error_log("[TrinityKit] Erro ao decodificar imagem base64");
        return false;
    }
    
    // Generate professional filename
    $page_number = $page_index + 1;
    $sanitized_child_name = sanitize_file_name($child_name);
    $timestamp = date('Y-m-d_H-i-s');
    
    if ($order_id && $sanitized_child_name) {
        $filename = "faceswap-pedido-{$order_id}-{$sanitized_child_name}-pagina-{$page_number}-{$timestamp}.jpg";
    } else {
        $filename = "faceswap-{$timestamp}-" . uniqid() . ".jpg";
    }
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    // Save file
    $file_saved = file_put_contents($file_path, $image_data);
    
    if ($file_saved === false) {
        error_log("[TrinityKit] Erro ao salvar arquivo de imagem");
        return false;
    }
    
    // Prepare professional data for WordPress insertion
    $file_type = wp_check_filetype($filename, null);
    
    // Create professional title and description
    $professional_title = $child_name ? 
        "Ilustração Face Swap - {$child_name} - Página {$page_number}" : 
        "Ilustração Face Swap - Página {$page_number}";
    
    $professional_content = $order_id ? 
        "Ilustração personalizada gerada via Face Swap para o pedido #{$order_id}. Criança: {$child_name}. Página {$page_number} do livro personalizado." :
        "Ilustração personalizada gerada via Face Swap.";
    
    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => $professional_title,
        'post_content' => $professional_content,
        'post_excerpt' => "Face Swap - Pedido #{$order_id} - {$child_name} - Página {$page_number}",
        'post_status' => 'inherit'
    );
    
    // Insert into WordPress
    $attach_id = wp_insert_attachment($attachment, $file_path);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit] Erro ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    // Add professional metadata
    if ($order_id) {
        update_post_meta($attach_id, '_trinitykitcms_order_id', $order_id);
        update_post_meta($attach_id, '_trinitykitcms_child_name', $child_name);
        update_post_meta($attach_id, '_trinitykitcms_page_number', $page_number);
        update_post_meta($attach_id, '_trinitykitcms_page_index', $page_index);
        update_post_meta($attach_id, '_trinitykitcms_generation_method', 'face_swap_api');
        update_post_meta($attach_id, '_trinitykitcms_generation_date', current_time('mysql'));
        update_post_meta($attach_id, '_trinitykitcms_file_source', 'webhook_check_faceswap');
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
    
    return array(
        'id' => $attach_id,
        'url' => wp_get_attachment_url($attach_id)
    );
}

// Note: Using send_telegram_error_notification() function from webhook-initiate-faceswap.php

/**
 * Handles the check face swap webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_check_faceswap_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    // Get all orders with status 'created_assets_text' and face_swap_initiated = true
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
                'key' => 'face_swap_initiated',
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
            error_log("[TrinityKit] $error_msg");
            $errors[] = $error_msg;
            continue;
        }

        $completed_pages = 0;
        $failed_pages = 0;
        $pending_pages = 0;
        
        // Check each page's face swap status
        foreach ($generated_pages as $index => $page) {
            $task_id = $page['faceswap_task_id'] ?? null;
            
            if (empty($task_id)) {
                $pending_pages++;
                continue;
            }

            // Check if this page already has a generated illustration
            if (!empty($page['generated_illustration'])) {
                $completed_pages++;
                continue;
            }

            // Check face swap status
            $status_data = check_face_swap_status($task_id);
            
            if ($status_data === false) {
                error_log("[TrinityKit] Erro ao verificar status da página $index do pedido #$order_id");
                $failed_pages++;
                continue;
            }

            if ($status_data['status'] === 'COMPLETED') {
                // Process the base64 image and save to WordPress
                $base64_image = $status_data['output'];
                $image_result = save_base64_image_to_wordpress($base64_image, $order_id, $child_name, $index);
                
                if ($image_result && !empty($image_result['id']) && intval($image_result['id']) > 0) {
                    // Update the page with the new illustration using a valid attachment id
                    $attachment_id = intval($image_result['id']);
                    $image_url = $image_result['url'];
                    
                    // Update only the illustration of this specific page using ACF
                    $field_key = "generated_book_pages_{$index}_generated_illustration";
                    $update_result = update_field($field_key, $attachment_id, $order_id);
                    
                    if ($update_result) {
                        $completed_pages++;
                        error_log("[TrinityKit] Página $index processada com sucesso - URL: $image_url, ID: $attachment_id");
                    } else {
                        $error_msg = "Falha ao atualizar ilustração da página $index do pedido #$order_id";
                        error_log("[TrinityKit] $error_msg");
                        $failed_pages++;
                        
                        // Send notification for ACF update error
                        $child_name = get_field('child_name', $order_id);
                        $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                        
                        $telegram_msg = "🚨 <b>ERRO DE ATUALIZAÇÃO</b> 🚨\n\n";
                        $telegram_msg .= "🔄 <b>Falha ao salvar no ACF</b>\n";
                        $telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                        $telegram_msg .= "📄 <b>Página:</b> " . ($index + 1) . "\n";
                        $telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n\n";
                        $telegram_msg .= "⚠️ <b>FAZER UPLOAD MANUAL</b>\n";
                        $telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO</a>";
                        
                        try {
                            $telegram = new TelegramService();
                            if ($telegram->isConfigured()) {
                                $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                                $telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                                $telegram->sendTextMessage($telegram_msg);
                            }
                        } catch (Exception $e) {
                            error_log("[TrinityKit] Erro ao enviar notificação Telegram: " . $e->getMessage());
                        }
                    }
                } else {
                    $error_msg = "Erro ao salvar imagem processada da página $index do pedido #$order_id (attachment inválido)";
                    error_log("[TrinityKit] $error_msg");
                    $failed_pages++;
                    
                    // Send notification for save error
                    $child_name = get_field('child_name', $order_id);
                    $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                    
                    $telegram_msg = "🚨 <b>ERRO NO PROCESSAMENTO</b> 🚨\n\n";
                    $telegram_msg .= "💾 <b>Falha ao salvar imagem</b>\n";
                    $telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                    $telegram_msg .= "📄 <b>Página:</b> " . ($index + 1) . "\n";
                    $telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n\n";
                    $telegram_msg .= "⚠️ <b>VERIFICAR MANUALMENTE</b>\n";
                    $telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO</a>";
                    
                    try {
                        $telegram = new TelegramService();
                        if ($telegram->isConfigured()) {
                            $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                            $telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                            $telegram->sendTextMessage($telegram_msg);
                        }
                    } catch (Exception $e) {
                        error_log("[TrinityKit] Erro ao enviar notificação Telegram: " . $e->getMessage());
                    }
                }
            } elseif ($status_data['status'] === 'FAILED') {
                $error_msg = "Face swap falhou para página $index do pedido #$order_id (Task ID: $task_id)";
                error_log("[TrinityKit] $error_msg");
                $failed_pages++;
                
                // Send detailed Telegram notification with manual intervention request
                $child_name = get_field('child_name', $order_id);
                $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                
                $telegram_msg = "🚨 <b>ATENÇÃO: FALHA NO FACE SWAP</b> 🚨\n\n";
                $telegram_msg .= "❌ <b>Erro na geração de ativo</b>\n";
                $telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                $telegram_msg .= "📄 <b>Página:</b> " . ($index + 1) . "\n";
                $telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n\n";
                $telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO PARA CORREÇÃO</a>";
                
                try {
                    $telegram = new TelegramService();
                    if ($telegram->isConfigured()) {
                        $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                        $telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                        $telegram->sendTextMessage($telegram_msg);
                    }
                } catch (Exception $e) {
                    error_log("[TrinityKit] Erro ao enviar notificação Telegram: " . $e->getMessage());
                }
            } else {
                // Still processing
                $pending_pages++;
            }
        }

        // Revalidar: todas as páginas precisam ter attachment válido salvo
        $refreshed_pages = get_field('generated_book_pages', $order_id);
        $total_pages = count($generated_pages);
        $all_pages_have_valid_attachments = true;
        if (is_array($refreshed_pages)) {
            foreach ($refreshed_pages as $page_idx => $refreshed_page) {
                $page_attachment_id = isset($refreshed_page['generated_illustration']) ? intval($refreshed_page['generated_illustration']) : 0;
                if ($page_attachment_id <= 0 || get_post_type($page_attachment_id) !== 'attachment') {
                    $all_pages_have_valid_attachments = false;
                    break;
                }
            }
        } else {
            $all_pages_have_valid_attachments = false;
        }

        if ($all_pages_have_valid_attachments && $failed_pages === 0) {
            // Update order status to 'created_assets_illustration'
            $status_updated = update_field('order_status', 'created_assets_illustration', $order_id);
            
            if ($status_updated) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-check-faceswap',
                    "Ilustrações com face swap geradas para $child_name ($completed_pages páginas)",
                    'created_assets_text',
                    'created_assets_illustration'
                );
                
                $processed++;
                error_log("[TrinityKit] Pedido #$order_id concluído com sucesso - $completed_pages páginas processadas");
            } else {
                $error_msg = "Falha ao atualizar status do pedido #$order_id";
                error_log("[TrinityKit] $error_msg");
                $errors[] = $error_msg;
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
            }
        } else {
            // Log current status
            error_log("[TrinityKit] Pedido #$order_id - Status: $completed_pages/$total_pages concluídas, $failed_pages falharam, $pending_pages pendentes (validação anexos: " . ($all_pages_have_valid_attachments ? 'OK' : 'INCOMPLETA') . ")");
            
            if ($failed_pages > 0) {
                $error_msg = "Pedido #$order_id tem $failed_pages páginas com falha no face swap";
                $errors[] = $error_msg;
            }
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit] Erros encontrados: " . implode(" | ", $errors));
    }
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Verificação de face swap concluída. {$processed} pedidos finalizados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
