<?php
/**
 * Webhook endpoint para verificar status (Polling Fallback)
 * 
 * Este endpoint é um FALLBACK usado quando o callback automático do FAL.AI falha.
 * O callback automático está em webhook-falai-callback.php e é o método prioritário.
 * 
 * FUNCIONAMENTO:
 * - Executado manualmente ou via cron
 * - Encontra pedidos com 'created_assets_text' e 'falai_initiated = true'
 * - Verifica páginas que têm 'falai_task_id' mas não têm 'generated_illustration'
 * - Baixa e salva imagens prontas
 * - Atualiza status do pedido para 'created_assets_illustration' quando completo
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Include TelegramService if needed
require_once get_template_directory() . '/includes/integrations.php';

// Register the webhook endpoint for polling fallback
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/check-falai', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_check_falai_webhook',
    ));
});

/**
 * Log estruturado para análise de performance
 */
if (!function_exists('log_falai_performance')) {
    function log_falai_performance($action, $data = array()) {
        $timestamp = date('Y-m-d H:i:s');
        $log_data = array_merge([
            'timestamp' => $timestamp,
            'action' => $action
        ], $data);
        error_log("[TrinityKit FAL.AI PERFORMANCE] " . json_encode($log_data, JSON_UNESCAPED_SLASHES));
    }
}

/**
 * Check FAL.AI status with FAL.AI API
 * 
 * @param string $request_id ID da tarefa do FAL.AI (request_id ou gateway_request_id)
 * @param int $order_id ID do pedido (para logs)
 * @param int $page_index Índice da página (para logs)
 * @return array|false Array com status e dados ou false em caso de erro
 */
function check_falai_status($request_id, $order_id = null, $page_index = null) {
    $api_request_start = microtime(true);
    
    $api_key = get_option('trinitykitcms_falai_api_key');
    $base_url = get_option('trinitykitcms_falai_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit FAL.AI] Configurações do FAL.AI não encontradas");
        log_falai_performance('check_status_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'request_id' => $request_id,
            'error' => 'config_not_found'
        ]);
        return false;
    }

    // URL para verificar status: https://queue.fal.run/fal-ai/nano-banana-pro/requests/{request_id}/status
    $status_url = rtrim($base_url, '/');
    // Se base_url não contém queue.fal.run, substituir fal.run por queue.fal.run
    if (strpos($status_url, 'queue.fal.run') === false) {
        $status_url = str_replace('fal.run', 'queue.fal.run', $status_url);
    }
    $status_url .= '/fal-ai/nano-banana-pro/requests/' . $request_id . '/status';
    
    $headers = [
        'accept: application/json',
        'Authorization: Key ' . trim($api_key)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $status_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $api_request_time = microtime(true) - $api_request_start;
    
    if (curl_errno($ch)) {
        $curl_error = curl_error($ch);
        error_log("[TrinityKit FAL.AI] Erro cURL na verificação de status: " . $curl_error);
        curl_close($ch);
        log_falai_performance('check_status_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'request_id' => $request_id,
            'error' => 'curl_error',
            'error_message' => $curl_error,
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("[TrinityKit FAL.AI] Erro na verificação de status ($http_code): " . $response);
        if ($http_code === 404 && is_string($response) && stripos($response, 'request does not exist') !== false) {
            log_falai_performance('check_status_not_found', [
                'order_id' => $order_id,
                'page_index' => $page_index,
                'request_id' => $request_id,
                'api_request_time' => round($api_request_time, 3)
            ]);
            return array(
                'status' => 'NOT_FOUND',
                'error' => $response,
            );
        }
        log_falai_performance('check_status_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'request_id' => $request_id,
            'error' => 'http_error',
            'http_code' => $http_code,
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    $status_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit FAL.AI] Resposta JSON inválida na verificação de status: " . json_last_error_msg());
        log_falai_performance('check_status_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'request_id' => $request_id,
            'error' => 'json_error',
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    $task_status = $status_data['status'] ?? 'UNKNOWN';
    log_falai_performance('check_status_success', [
        'order_id' => $order_id,
        'page_index' => $page_index,
        'request_id' => $request_id,
        'status' => $task_status,
        'api_request_time' => round($api_request_time, 3)
    ]);
    
    return $status_data;
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
if (!function_exists('save_falai_image_from_url_to_wordpress')) {
    function save_falai_image_from_url_to_wordpress($image_url, $order_id = null, $child_name = '', $page_index = 0) {
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
}

/**
 * Send Telegram notification for errors
 */
if (!function_exists('send_telegram_error_notification_falai')) {
    function send_telegram_error_notification_falai($message, $title = "Erro no FAL.AI") {
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
    
    $start_time = microtime(true);
    
    log_falai_performance('webhook_check_start', [
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
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
        $order_start_time = microtime(true);
        
        // Get order details
        $child_name = get_field('child_name', $order_id);
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        if (empty($generated_pages)) {
            $error_msg = "Páginas geradas não encontradas para pedido #$order_id";
            error_log("[TrinityKit FAL.AI] $error_msg");
            $errors[] = $error_msg;
            continue;
        }

        $completed_pages = 0;
        $failed_pages = 0;
        $pending_pages = 0;
        
        // Check each page's FAL.AI status
        foreach ($generated_pages as $index => $page) {
            // Check if this page should skip face edit
            $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
            
            // If page skips face edit, just copy the base illustration
            if ($skip_faceswap) {
                if (!empty($page['generated_illustration'])) {
                    $completed_pages++;
                } else {
                    // Get the base illustration from template matching gender and skin tone
                    $book_template = get_field('book_template', $order_id);
                    if (!$book_template) {
                        $error_msg = "Template do livro não encontrado para pedido #$order_id";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $errors[] = $error_msg;
                        $pending_pages++;
                        continue;
                    }
                    
                    $template_pages = get_field('template_book_pages', $book_template->ID);
                    if (empty($template_pages[$index])) {
                        $error_msg = "Página $index do template não encontrada para pedido #$order_id";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $errors[] = $error_msg;
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
                    
                    if (empty($base_image) || empty($base_image['ID'])) {
                        $error_msg = "Imagem base não encontrada para página $index do pedido #$order_id (gênero: $child_gender, tom: $child_skin_tone)";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $errors[] = $error_msg;
                        $pending_pages++;
                        continue;
                    }
                    
                    // Copy the base illustration to generated_illustration
                    $field_key = "generated_book_pages_{$index}_generated_illustration";
                    $update_result = update_field($field_key, $base_image['ID'], $order_id);
                    
                    if ($update_result) {
                        $completed_pages++;
                    } else {
                        $error_msg = "Falha ao copiar ilustração base da página $index do pedido #$order_id";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $errors[] = $error_msg;
                        $pending_pages++;
                    }
                }
                continue;
            }
            
            $request_id = $page['falai_task_id'] ?? null;
            
            if (empty($request_id)) {
                $pending_pages++;
                continue;
            }

            // Check if this page already has a FAL.AI illustration (verificar campo específico)
            if (!empty($page['falai_illustration'])) {
                $completed_pages++;
                continue;
            }

            // Check FAL.AI status (rate limit)
            usleep(1500000); // 1.5 segundos entre requisições
            $page_check_start = microtime(true);
            $status_data = check_falai_status($request_id, $order_id, $index);
            $page_check_time = microtime(true) - $page_check_start;
            
            if ($status_data === false) {
                error_log("[TrinityKit FAL.AI] Erro ao verificar status da página $index do pedido #$order_id");
                $failed_pages++;
                log_falai_performance('page_check_error', [
                    'order_id' => $order_id,
                    'page_index' => $index,
                    'request_id' => $request_id,
                    'page_check_time' => round($page_check_time, 3)
                ]);
                continue;
            }
            
            if (is_array($status_data) && isset($status_data['status']) && $status_data['status'] === 'NOT_FOUND') {
                $pending_pages++;
                log_falai_performance('page_check_not_found', [
                    'order_id' => $order_id,
                    'page_index' => $index,
                    'request_id' => $request_id,
                    'page_check_time' => round($page_check_time, 3)
                ]);
                continue;
            }

            // Verificar status da tarefa
            $task_status = $status_data['status'] ?? null;
            
            if ($task_status === 'COMPLETED' || $task_status === 'SUCCESS') {
                // Para queue requests, a resposta tem um response_url que precisa ser consultado
                $image_url_to_download = null;
                
                if (isset($status_data['response_url'])) {
                    // Fazer requisição para pegar a resposta completa
                    $response_url = $status_data['response_url'];
                    
                    // Buscar API key para fazer a requisição
                    $falai_api_key = get_option('trinitykitcms_falai_api_key');
                    
                    $response_fetch_start = microtime(true);
                    $response = wp_remote_get($response_url, array(
                        'headers' => array(
                            'Authorization' => 'Key ' . trim($falai_api_key),
                            'Accept' => 'application/json'
                        )
                    ));
                    $response_fetch_time = microtime(true) - $response_fetch_start;
                    
                    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                        $response_body = wp_remote_retrieve_body($response);
                        $response_data = json_decode($response_body, true);
                        
                        // Agora sim, extrair a URL da imagem
                        if (isset($response_data['images']) && is_array($response_data['images']) && !empty($response_data['images'][0])) {
                            $image_url_to_download = $response_data['images'][0]['url'] ?? $response_data['images'][0];
                        } elseif (isset($response_data['image']['url'])) {
                            $image_url_to_download = $response_data['image']['url'];
                        } elseif (isset($response_data['image_url'])) {
                            $image_url_to_download = $response_data['image_url'];
                        } elseif (isset($response_data['image']) && is_string($response_data['image'])) {
                            $image_url_to_download = $response_data['image'];
                        }
                        
                        log_falai_performance('response_url_fetched', [
                            'order_id' => $order_id,
                            'page_index' => $index,
                            'request_id' => $request_id,
                            'response_fetch_time' => round($response_fetch_time, 3),
                            'image_url_found' => !empty($image_url_to_download)
                        ]);
                    } else {
                        $error_msg = "Erro ao buscar response_url para página $index do pedido #$order_id";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $failed_pages++;
                        continue;
                    }
                } else {
                    // Fallback: tentar extrair diretamente da resposta de status (caso antigo)
                    if (isset($status_data['images']) && is_array($status_data['images']) && !empty($status_data['images'][0])) {
                        $image_url_to_download = $status_data['images'][0]['url'] ?? $status_data['images'][0];
                    } elseif (isset($status_data['image']['url'])) {
                        $image_url_to_download = $status_data['image']['url'];
                    } elseif (isset($status_data['image_url'])) {
                        $image_url_to_download = $status_data['image_url'];
                    } elseif (isset($status_data['image']) && is_string($status_data['image'])) {
                        $image_url_to_download = $status_data['image'];
                    }
                }
                
                if (empty($image_url_to_download)) {
                    $error_msg = "URL da imagem não encontrada na resposta da API para página $index do pedido #$order_id";
                    error_log("[TrinityKit FAL.AI] $error_msg");
                    $failed_pages++;
                    continue;
                }
                
                $download_start = microtime(true);
                $saved_image_url = save_falai_image_from_url_to_wordpress($image_url_to_download, $order_id, $child_name, $index);
                $download_time = microtime(true) - $download_start;
                
                if ($saved_image_url) {
                    // Update the page with the new illustration
                    $acf_start = microtime(true);
                    $attachment_id = attachment_url_to_postid($saved_image_url);
                    if (!$attachment_id) {
                        $attachment_id = 0; // Fallback
                    }
                    
                    // Save to the specific FAL.AI illustration field (campo específico do FAL.AI)
                    $falai_field_key = "generated_book_pages_{$index}_falai_illustration";
                    $update_result = update_field($falai_field_key, $attachment_id, $order_id);
                    
                    // Also update the general illustration field (campo geral para compatibilidade)
                    $field_key = "generated_book_pages_{$index}_generated_illustration";
                    update_field($field_key, $attachment_id, $order_id);
                    
                    $acf_time = microtime(true) - $acf_start;
                    
                    if ($update_result) {
                        $completed_pages++;
                        $total_page_time = microtime(true) - $page_check_start;
                        log_falai_performance('page_completed', [
                            'order_id' => $order_id,
                            'page_index' => $index,
                            'request_id' => $request_id,
                            'page_check_time' => round($page_check_time, 3),
                            'download_time' => round($download_time, 3),
                            'acf_time' => round($acf_time, 3),
                            'total_page_time' => round($total_page_time, 3)
                        ]);
                    } else {
                        $error_msg = "Falha ao atualizar ilustração da página $index do pedido #$order_id";
                        error_log("[TrinityKit FAL.AI] $error_msg");
                        $failed_pages++;
                        
                        // Send notification for ACF update error
                        $child_name = get_field('child_name', $order_id);
                        $order_url = get_permalink($order_id);
                        
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
                            error_log("[TrinityKit FAL.AI] Erro ao enviar notificação Telegram: " . $e->getMessage());
                        }
                    }
                } else {
                    $error_msg = "Erro ao salvar imagem processada da página $index do pedido #$order_id";
                    error_log("[TrinityKit FAL.AI] $error_msg");
                    $failed_pages++;
                    
                    // Send notification for save error
                    $child_name = get_field('child_name', $order_id);
                    $order_url = get_permalink($order_id);
                    
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
                        error_log("[TrinityKit FAL.AI] Erro ao enviar notificação Telegram: " . $e->getMessage());
                    }
                }
            } elseif ($task_status === 'FAILED' || $task_status === 'ERROR') {
                $error_msg = "FAL.AI falhou para página $index do pedido #$order_id (Request ID: $request_id)";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $failed_pages++;
                
                // Send detailed Telegram notification with manual intervention request
                $child_name = get_field('child_name', $order_id);
                $order_url = get_permalink($order_id);
                
                $telegram_msg = "🚨 <b>ATENÇÃO: FALHA NO FAL.AI</b> 🚨\n\n";
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
                    error_log("[TrinityKit FAL.AI] Erro ao enviar notificação Telegram: " . $e->getMessage());
                }
            } else {
                // Still processing (IN_QUEUE, PROCESSING, etc.)
                $pending_pages++;
            }
        }

        // Check if all pages are completed
        $total_pages = count($generated_pages);
        $order_time = microtime(true) - $order_start_time;
        
        if ($completed_pages === $total_pages && $failed_pages === 0) {
            // Update order status to 'created_assets_illustration'
            $status_updated = update_field('order_status', 'created_assets_illustration', $order_id);
            
            if ($status_updated) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-check-falai',
                    "Ilustrações com FAL.AI geradas para $child_name ($completed_pages páginas)",
                    'created_assets_text',
                    'created_assets_illustration'
                );
                
                $processed++;
                log_falai_performance('order_completed', [
                    'order_id' => $order_id,
                    'child_name' => $child_name,
                    'completed_pages' => $completed_pages,
                    'total_pages' => $total_pages,
                    'pending_pages' => $pending_pages,
                    'failed_pages' => $failed_pages,
                    'order_time' => round($order_time, 3)
                ]);
            } else {
                $error_msg = "Falha ao atualizar status do pedido #$order_id";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $errors[] = $error_msg;
                log_falai_performance('order_status_update_error', [
                    'order_id' => $order_id,
                    'child_name' => $child_name,
                    'completed_pages' => $completed_pages,
                    'order_time' => round($order_time, 3)
                ]);
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
            }
        } else {
            if ($failed_pages > 0) {
                $error_msg = "Pedido #$order_id tem $failed_pages páginas com falha no FAL.AI";
                $errors[] = $error_msg;
            }
            log_falai_performance('order_partial', [
                'order_id' => $order_id,
                'child_name' => $child_name,
                'completed_pages' => $completed_pages,
                'pending_pages' => $pending_pages,
                'failed_pages' => $failed_pages,
                'total_pages' => $total_pages,
                'order_time' => round($order_time, 3)
            ]);
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit FAL.AI] Erros encontrados: " . implode(" | ", $errors));
    }
    
    $elapsed_time_precise = microtime(true) - $start_time;
    $message = "Verificação de FAL.AI concluída. {$processed} pedidos finalizados.";
    
    log_falai_performance('webhook_check_end', [
        'elapsed_time' => round($elapsed_time_precise, 3),
        'processed_orders' => $processed,
        'total_orders' => count($orders),
        'errors_count' => count($errors)
    ]);
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => $message,
        'processed' => $processed,
        'total' => count($orders),
        'elapsed_time' => round($elapsed_time_precise, 3),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
