<?php
/**
 * Webhook endpoint for initiating FAL.AI Nano Banana Pro (Modo Assíncrono com Queue + Webhook)
 * 
 * Este endpoint encontra pedidos com status 'created_assets_text' e inicia o processamento
 * de face edit com FAL.AI em modo assíncrono usando queue.fal.run. 
 * 
 * FUNCIONAMENTO:
 * 1. Inicia as tarefas no FAL.AI e salva o request_id de cada página
 * 2. FAL.AI processa as imagens e chama o webhook de callback automaticamente
 * 3. Como fallback, webhook-check-falai.php pode fazer polling manual dos status
 * 
 * Este arquivo NÃO baixa imagens - apenas inicia as tarefas.
 * O download é feito via webhook callback ou polling fallback.
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
    register_rest_route('trinitykitcms-api/v1', '/webhook/initiate-falai', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_initiate_falai_webhook',
    ));
});

/**
 * Log estruturado para análise de performance
 */
function log_falai_performance($action, $data = array()) {
    $timestamp = date('Y-m-d H:i:s');
    $log_data = array_merge([
        'timestamp' => $timestamp,
        'action' => $action
    ], $data);
    error_log("[TrinityKit FAL.AI PERFORMANCE] " . json_encode($log_data, JSON_UNESCAPED_SLASHES));
}

/**
 * Process face edit with FAL.AI Nano Banana Pro (Modo Assíncrono)
 * 
 * @param string $face_image_url URL da imagem do rosto da criança
 * @param string $target_image_url URL da imagem base para aplicar o face edit
 * @param string $prompt Prompt para guiar o processamento
 * @param string $aspect_ratio Proporção da imagem (ex: "16:9", "1:1", "9:16")
 * @param int $order_id ID do pedido (para logs)
 * @param int $page_index Índice da página (para logs)
 * @return string|false Request ID (request_id ou gateway_request_id) ou false em caso de erro
 */
function initiate_face_edit_with_falai($face_image_url, $target_image_url, $prompt, $aspect_ratio = '16:9', $order_id = null, $page_index = null) {
    $api_request_start = microtime(true);
    
    $api_key = get_option('trinitykitcms_falai_api_key');
    $base_url = get_option('trinitykitcms_falai_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit FAL.AI] Configurações do FAL.AI não encontradas");
        log_falai_performance('initiate_api_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'error' => 'config_not_found'
        ]);
        return false;
    }

    // URL para modo assíncrono usando queue
    // Endpoint: https://queue.fal.run/fal-ai/nano-banana-pro/edit
    $run_url = rtrim($base_url, '/');
    // Se base_url não contém queue.fal.run, substituir fal.run por queue.fal.run
    if (strpos($run_url, 'queue.fal.run') === false) {
        $run_url = str_replace('fal.run', 'queue.fal.run', $run_url);
    }
    $run_url .= '/fal-ai/nano-banana-pro/edit';
    
    // Header de autenticação
    $headers = [
        'Content-Type: application/json',
        'Authorization: Key ' . trim($api_key)
    ];

    // Webhook URL para receber callback do FAL.AI quando a imagem estiver pronta
    $site_url = get_site_url();
    $webhook_url = $site_url . '/wp-json/trinitykitcms-api/v1/webhook/falai-callback';
    
    // Log da URL do webhook para debug
    error_log("[TrinityKit FAL.AI] Webhook URL configurada: $webhook_url");
    
    // Body para modo ASSÍNCRONO com WEBHOOK
    // A API espera: prompt + image_urls (array de 2 imagens)
    // Primeira imagem: ilustração base (target)
    // Segunda imagem: rosto da criança (face)
    // aspect_ratio: proporção definida no template da página
    // resolution: resolução 2K para alta qualidade
    // webhook_url: URL para o FAL.AI chamar quando a imagem estiver pronta
    $body = [
        'prompt' => $prompt,
        'image_urls' => [
            $target_image_url,  // Ilustração base
            $face_image_url      // Rosto da criança
        ],
        'aspect_ratio' => $aspect_ratio,  // Proporção da imagem do template
        'resolution' => '2K',  // Resolução 2K para alta qualidade
        'webhook_url' => $webhook_url  // Webhook para callback automático (campo correto da API FAL.AI)
    ];

    // Log do body da requisição para debug
    error_log("[TrinityKit FAL.AI] Body da requisição (Pedido #$order_id, Página $page_index): " . json_encode($body, JSON_UNESCAPED_SLASHES));
    
    // Requisição para iniciar o face edit (modo assíncrono)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $run_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $api_request_time = microtime(true) - $api_request_start;
    
    if (curl_errno($ch)) {
        $curl_error = curl_error($ch);
        error_log("[TrinityKit FAL.AI] Erro cURL na requisição: " . $curl_error);
        curl_close($ch);
        log_falai_performance('initiate_api_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'error' => 'curl_error',
            'error_message' => $curl_error,
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $request_info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($http_code !== 200 && $http_code !== 201 && $http_code !== 202) {
        error_log("[TrinityKit FAL.AI] Erro API ($http_code): " . $response);
        error_log("[TrinityKit FAL.AI] Request URL: " . $request_info['url']);
        log_falai_performance('initiate_api_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'error' => 'http_error',
            'http_code' => $http_code,
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit FAL.AI] Resposta JSON inválida: " . json_last_error_msg());
        log_falai_performance('initiate_api_error', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'error' => 'json_error',
            'api_request_time' => round($api_request_time, 3)
        ]);
        return false;
    }
    
    // Modo ASSÍNCRONO: a API retorna request_id ou gateway_request_id
    // Formato esperado: { "request_id": "...", "status": "IN_QUEUE" }
    // ou { "gateway_request_id": "..." }
    
    // Tenta encontrar o request_id na resposta
    $request_id = null;
    
    if (isset($response_data['request_id'])) {
        $request_id = $response_data['request_id'];
    } elseif (isset($response_data['gateway_request_id'])) {
        $request_id = $response_data['gateway_request_id'];
    } elseif (isset($response_data['id'])) {
        $request_id = $response_data['id'];
    }
    
    if ($request_id) {
        log_falai_performance('initiate_api_success', [
            'order_id' => $order_id,
            'page_index' => $page_index,
            'request_id' => $request_id,
            'api_request_time' => round($api_request_time, 3),
            'http_code' => $http_code
        ]);
        return $request_id;
    }
    
    error_log("[TrinityKit FAL.AI] Formato de resposta inesperado: " . json_encode($response_data));
    log_falai_performance('initiate_api_error', [
        'order_id' => $order_id,
        'page_index' => $page_index,
        'error' => 'unexpected_response_format',
        'api_request_time' => round($api_request_time, 3)
    ]);
    return false;
}

/**
 * Send Telegram notification for errors
 */
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

/**
 * Handles the initiate FAL.AI webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_initiate_falai_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    
    $start_time = microtime(true);
    
    log_falai_performance('webhook_initiate_start', [
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Get all orders with status 'created_assets_text' and falai_initiated = false
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
                'relation' => 'OR',
                array(
                    'key' => 'falai_initiated',
                    'value' => '1',
                    'compare' => '!='
                ),
                array(
                    'key' => 'falai_initiated',
                    'compare' => 'NOT EXISTS'
                )
            )
        )
    );

    $orders = get_posts($args);
    $processed_orders = 0;
    $initiated_pages_total = 0;
    $errors = array();

    // Buscar prompt das configurações
    $custom_prompt = get_option('trinitykitcms_falai_prompt', '');
    $default_prompt = trim($custom_prompt);
    
    // Verificar se o prompt está configurado
    if (empty($default_prompt)) {
        $error_msg = "Prompt FAL.AI não configurado. Configure o prompt em Integrações > Configurações do FAL.AI.";
        error_log("[TrinityKit FAL.AI] $error_msg");
        send_telegram_error_notification_falai($error_msg);
        
        return new WP_REST_Response(array(
            'message' => $error_msg,
            'initiated_pages' => 0,
            'processed_orders' => 0,
            'total_orders' => count($orders),
            'elapsed_time' => 0,
            'errors' => array($error_msg)
        ), 500);
    }

    foreach ($orders as $order) {
        $order_id = $order->ID;
        $order_start_time = microtime(true);
        
        // Get order details
        $child_name = get_field('child_name', $order_id);
        $child_gender = strtolower(trim((string) get_field('child_gender', $order_id)));
        $child_skin_tone = strtolower(trim((string) get_field('child_skin_tone', $order_id)));
        $child_face_photo = get_field('child_face_photo', $order_id);
        $book_template = get_field('book_template', $order_id);
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        // Check required fields
        $missing_fields = array();
        
        if (empty($child_name)) $missing_fields[] = 'child_name';
        if (empty($child_gender)) $missing_fields[] = 'child_gender';
        if (empty($child_skin_tone)) $missing_fields[] = 'child_skin_tone';
        if (empty($child_face_photo)) $missing_fields[] = 'child_face_photo';
        if (!$book_template) $missing_fields[] = 'book_template';
        if (empty($generated_pages)) $missing_fields[] = 'generated_book_pages';
        
        if (!empty($missing_fields)) {
            $missing_fields_str = implode(', ', $missing_fields);
            $error_msg = "Campos obrigatórios vazios para o pedido #$order_id: $missing_fields_str";
            error_log("[TrinityKit FAL.AI] $error_msg");
            $errors[] = $error_msg;
            
            send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
            continue;
        }

        $template_pages = get_field('template_book_pages', $book_template->ID);
        $page_errors = 0;
        $page_error_messages = array();
        $initiated_pages = 0;
        $skipped_pages = 0;
        
        // Process each page individually
        foreach ($template_pages as $index => $page) {
            // Verificar se esta página já tem falai_task_id (já foi iniciada)
            $existing_task_id = $page['falai_task_id'] ?? null;
            if (!empty($existing_task_id)) {
                $initiated_pages++;
                continue;
            }
            
            // Verificar se esta página já tem generated_illustration (já está completa)
            if (!empty($page['generated_illustration'])) {
                $initiated_pages++;
                continue;
            }
            // Check if this page should skip face edit
            $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
            
            if ($skip_faceswap) {
                // For pages that skip face edit, try to copy the base illustration directly
                $base_illustrations = $page['base_illustrations'];
                $base_image = null;
                
                if (!empty($base_illustrations)) {
                    foreach ($base_illustrations as $illustration) {
                        $ill_gender = strtolower(trim((string) ($illustration['gender'] ?? '')));
                        $ill_skin = strtolower(trim((string) ($illustration['skin_tone'] ?? '')));

                        if ($ill_gender === $child_gender && $ill_skin === $child_skin_tone) {
                            $base_image = $illustration['illustration_asset'];
                            break;
                        }
                    }
                }
                
                // Try to copy the base illustration if found
                if (!empty($base_image) && !empty($base_image['ID'])) {
                    $field_key = "generated_book_pages_{$index}_generated_illustration";
                    $update_result = update_field($field_key, $base_image['ID'], $order_id);
                    
                    if ($update_result) {
                        $skipped_pages++;
                    } else {
                        $skipped_pages++;
                    }
                } else {
                    $skipped_pages++;
                }
                continue;
            }
            
            // Find the correct base illustration in the base_illustrations repeater
            $base_illustrations = $page['base_illustrations'];
            $base_image = null;
            
            if (!empty($base_illustrations)) {
                foreach ($base_illustrations as $illustration) {
                    $ill_gender = strtolower(trim((string) ($illustration['gender'] ?? '')));
                    $ill_skin = strtolower(trim((string) ($illustration['skin_tone'] ?? '')));

                    if ($ill_gender === $child_gender && $ill_skin === $child_skin_tone) {
                        $base_image = $illustration['illustration_asset'];
                        break;
                    }
                }
            }
            
            if (empty($base_image)) {
                $error_msg = "Imagem base não encontrada para página $index do pedido #$order_id (gênero: $child_gender, tom: $child_skin_tone)";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
                continue;
            }

            // Image URLs
            $face_image_url = $child_face_photo['url'];
            $target_image_url = $base_image['url'];

            // Get aspect_ratio from template page (default to 16:9 if not set)
            $aspect_ratio = '16:9'; // Default
            if (isset($page['aspect_ratio']) && !empty($page['aspect_ratio'])) {
                $aspect_ratio = $page['aspect_ratio'];
            }

            // Initiate face edit with FAL.AI (modo assíncrono - retorna request_id)
            $page_start_time = microtime(true);
            $request_id = initiate_face_edit_with_falai($face_image_url, $target_image_url, $default_prompt, $aspect_ratio, $order_id, $index);
            $page_api_time = microtime(true) - $page_start_time;
            
            if ($request_id === false) {
                $error_msg = "Erro ao iniciar FAL.AI face edit da página $index do pedido #$order_id";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                log_falai_performance('page_initiate_error', [
                    'order_id' => $order_id,
                    'page_index' => $index,
                    'page_api_time' => round($page_api_time, 3)
                ]);
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
                continue;
            }

            // Salvar o request_id (falai_task_id) no ACF
            $acf_start_time = microtime(true);
            $field_key = "generated_book_pages_{$index}_falai_task_id";
            $update_result = update_field($field_key, $request_id, $order_id);
            $acf_time = microtime(true) - $acf_start_time;
            
            if ($update_result) {
                $initiated_pages++;
                $initiated_pages_total++;
                $total_page_time = microtime(true) - $page_start_time;
                log_falai_performance('page_initiate_success', [
                    'order_id' => $order_id,
                    'page_index' => $index,
                    'request_id' => $request_id,
                    'page_api_time' => round($page_api_time, 3),
                    'acf_time' => round($acf_time, 3),
                    'total_page_time' => round($total_page_time, 3)
                ]);
            } else {
                $error_msg = "Falha ao salvar falai_task_id da página $index do pedido #$order_id no ACF";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                log_falai_performance('page_initiate_acf_error', [
                    'order_id' => $order_id,
                    'page_index' => $index,
                    'request_id' => $request_id,
                    'page_api_time' => round($page_api_time, 3),
                    'acf_time' => round($acf_time, 3)
                ]);
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
            }
        }

        // Verificar se todas as páginas foram iniciadas (não necessariamente completas)
        $total_pages = count($template_pages);
        $total_initiated = $initiated_pages + $skipped_pages;
        
        if ($page_errors > 0) {
            $error_msg = "Falha ao iniciar FAL.AI para $page_errors páginas do pedido #$order_id. Erros: " . implode(' | ', $page_error_messages);
            error_log("[TrinityKit FAL.AI] $error_msg");
            $errors[] = $error_msg;
        }
        
        // Marcar como iniciado se todas as páginas foram iniciadas (ou puladas)
        // Nota: O status do pedido será atualizado no webhook-check-falai.php quando todas as imagens estiverem prontas
        $order_time = microtime(true) - $order_start_time;
        if ($total_initiated === $total_pages) {
            update_field('falai_initiated', true, $order_id);
            $processed_orders++;
            
            // Add log entry
            $log_message = "Tarefas FAL.AI iniciadas para $child_name ($initiated_pages páginas iniciadas";
            if ($skipped_pages > 0) {
                $log_message .= ", $skipped_pages páginas sem processamento";
            }
            $log_message .= "). Aguardando conclusão das imagens.";
            
            trinitykit_add_post_log(
                $order_id,
                'webhook-initiate-falai',
                $log_message,
                'created_assets_text',
                'created_assets_text' // Status não muda ainda, apenas quando imagens estiverem prontas
            );
            
            log_falai_performance('order_completed', [
                'order_id' => $order_id,
                'child_name' => $child_name,
                'initiated_pages' => $initiated_pages,
                'skipped_pages' => $skipped_pages,
                'page_errors' => $page_errors,
                'order_time' => round($order_time, 3)
            ]);
        } else {
            log_falai_performance('order_partial', [
                'order_id' => $order_id,
                'child_name' => $child_name,
                'initiated_pages' => $initiated_pages,
                'skipped_pages' => $skipped_pages,
                'total_pages' => $total_pages,
                'total_initiated' => $total_initiated,
                'page_errors' => $page_errors,
                'order_time' => round($order_time, 3)
            ]);
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit FAL.AI] Erros encontrados: " . implode(" | ", $errors));
    }
    
    $elapsed_time_precise = microtime(true) - $start_time;
    $message = "Processamento assíncrono do FAL.AI concluído. {$initiated_pages_total} tarefas iniciadas em {$processed_orders} pedidos.";
    
    log_falai_performance('webhook_initiate_end', [
        'elapsed_time' => round($elapsed_time_precise, 3),
        'initiated_pages_total' => $initiated_pages_total,
        'processed_orders' => $processed_orders,
        'total_orders' => count($orders),
        'errors_count' => count($errors)
    ]);
    
    return new WP_REST_Response(array(
        'message' => $message,
        'initiated_pages' => $initiated_pages_total,
        'processed_orders' => $processed_orders,
        'total_orders' => count($orders),
        'elapsed_time' => round($elapsed_time_precise, 3),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}

