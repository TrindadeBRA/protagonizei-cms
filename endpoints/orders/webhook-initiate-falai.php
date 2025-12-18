<?php
/**
 * Webhook endpoint for processing FAL.AI Nano Banana Pro (Modo Síncrono)
 * 
 * This endpoint finds orders with status 'created_assets_text' and processes face edit with FAL.AI
 * for each page in synchronous mode. The images are generated and saved immediately,
 * updating the order status to 'created_assets_illustration' when complete.
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
 * Process face edit with FAL.AI Nano Banana Pro (Modo Síncrono)
 * 
 * @param string $face_image_url URL da imagem do rosto da criança
 * @param string $target_image_url URL da imagem base para aplicar o face edit
 * @param string $prompt Prompt para guiar o processamento
 * @return string|false URL da imagem gerada ou false em caso de erro
 */
function initiate_face_edit_with_falai($face_image_url, $target_image_url, $prompt) {
    $api_key = get_option('trinitykitcms_falai_api_key');
    $base_url = get_option('trinitykitcms_falai_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit FAL.AI] Configurações do FAL.AI não encontradas");
        return false;
    }

    // URL CORRETA baseada na documentação oficial
    // Endpoint: https://fal.run/fal-ai/nano-banana-pro/edit
    $run_url = rtrim($base_url, '/') . '/fal-ai/nano-banana-pro/edit';
    
    // Header de autenticação
    $headers = [
        'Content-Type: application/json',
        'Authorization: Key ' . trim($api_key)
    ];
    
    // Log para debug
    error_log("[TrinityKit FAL.AI] URL: " . $run_url);
    error_log("[TrinityKit FAL.AI] API Key Length: " . strlen(trim($api_key)));

    // Body CORRETO baseado na documentação
    // A API espera: prompt + image_urls (array de 2 imagens)
    // Primeira imagem: ilustração base (target)
    // Segunda imagem: rosto da criança (face)
    // sync_mode: true para retornar a imagem diretamente na resposta
    // aspect_ratio: "16:9" para formato widescreen
    $body = [
        'prompt' => $prompt,
        'image_urls' => [
            $target_image_url,  // Ilustração base
            $face_image_url      // Rosto da criança
        ],
        'sync_mode' => true,      // Modo síncrono - retorna imagem diretamente
        'aspect_ratio' => '16:9'  // Formato 16:9 (widescreen)
    ];

    // Requisição para iniciar o face edit
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $run_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("[TrinityKit FAL.AI] Erro cURL na requisição: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $request_info = curl_getinfo($ch);
    curl_close($ch);
    
    // Log detalhado para debug
    error_log("[TrinityKit FAL.AI] HTTP Code: " . $http_code);
    error_log("[TrinityKit FAL.AI] Response: " . substr($response, 0, 1000));
    
    if ($http_code !== 200 && $http_code !== 201 && $http_code !== 202) {
        error_log("[TrinityKit FAL.AI] Erro API ($http_code): " . $response);
        error_log("[TrinityKit FAL.AI] Request URL: " . $request_info['url']);
        return false;
    }
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit FAL.AI] Resposta JSON inválida: " . json_last_error_msg());
        return false;
    }
    
    // Modo SÍNCRONO: a API retorna a imagem diretamente
    // Formato esperado: { "images": [{ "url": "...", ... }] }
    // ou { "image": { "url": "..." } } ou { "image_url": "..." }
    
    // Tenta encontrar a URL da imagem na resposta
    $image_url = null;
    
    if (isset($response_data['images']) && is_array($response_data['images']) && !empty($response_data['images'][0])) {
        $image_url = $response_data['images'][0]['url'] ?? $response_data['images'][0];
    } elseif (isset($response_data['image']['url'])) {
        $image_url = $response_data['image']['url'];
    } elseif (isset($response_data['image_url'])) {
        $image_url = $response_data['image_url'];
    } elseif (isset($response_data['image']) && is_string($response_data['image'])) {
        $image_url = $response_data['image'];
    }
    
    if ($image_url) {
        error_log("[TrinityKit FAL.AI] Imagem recebida com sucesso (modo síncrono)");
        return $image_url;
    }
    
    error_log("[TrinityKit FAL.AI] Formato de resposta inesperado: " . json_encode($response_data));
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
    $processed = 0;
    $errors = array();

    // Prompt padrão para o FAL.AI
    $default_prompt = "Do not alter the illustration, style, or character position. Apply the child's head in the same artistic style, proportions, lighting, and texture, preserving the child's gender, hair color, eye color, and matching the illustrated skin tone exactly to the child's real skin tone.";

    foreach ($orders as $order) {
        $order_id = $order->ID;
        
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
                    $field_key = "generated_book_pages_{$index}_generated_illustration_falai";
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

            // Initiate face edit with FAL.AI (modo síncrono)
            $image_url = initiate_face_edit_with_falai($face_image_url, $target_image_url, $default_prompt);
            
            if ($image_url === false) {
                $error_msg = "Erro ao processar FAL.AI face edit da página $index do pedido #$order_id";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
                continue;
            }

            // Salvar a imagem no WordPress (modo síncrono - imagem já está pronta)
            require_once get_template_directory() . '/endpoints/orders/webhook-check-falai.php';
            $saved_image_url = save_falai_image_from_url_to_wordpress($image_url, $order_id, $child_name, $index);
            
            if ($saved_image_url === false) {
                $error_msg = "Erro ao salvar imagem FAL.AI da página $index do pedido #$order_id";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
                continue;
            }
            
            // Salvar a ilustração processada no ACF
            $attachment_id = attachment_url_to_postid($saved_image_url);
            if (!$attachment_id) {
                $error_msg = "Não foi possível obter ID do attachment da página $index do pedido #$order_id";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
                continue;
            }
            
            $field_key = "generated_book_pages_{$index}_generated_illustration_falai";
            $update_result = update_field($field_key, $attachment_id, $order_id);
            
            if ($update_result) {
                $initiated_pages++;
                error_log("[TrinityKit FAL.AI] Página $index do pedido #$order_id processada com sucesso");
            } else {
                $error_msg = "Falha ao salvar ilustração da página $index do pedido #$order_id no ACF";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
            }
        }

        // Verificar se todas as páginas foram processadas com sucesso (modo síncrono)
        $total_processed_pages = $initiated_pages + $skipped_pages;
        
        if ($page_errors > 0) {
            $error_msg = "Falha ao processar FAL.AI para $page_errors páginas do pedido #$order_id. Erros: " . implode(' | ', $page_error_messages);
            error_log("[TrinityKit FAL.AI] $error_msg");
            $errors[] = $error_msg;
            
            if ($total_processed_pages === 0) {
                continue;
            }
        }
        
        // No modo síncrono, as imagens já estão prontas - atualizar status diretamente
        if ($total_processed_pages === count($template_pages)) {
            // Marcar como iniciado e finalizado
            update_field('falai_initiated', true, $order_id);
            
            // Atualizar status do pedido para 'created_assets_illustration'
            $status_updated = update_field('order_status', 'created_assets_illustration', $order_id);
            
            if ($status_updated) {
                // Add log entry
                $log_message = "Ilustrações com FAL.AI geradas para $child_name ($initiated_pages páginas)";
                if ($skipped_pages > 0) {
                    $log_message .= " e $skipped_pages páginas sem processamento";
                }
                
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-initiate-falai',
                    $log_message,
                    'created_assets_text',
                    'created_assets_illustration'
                );
                
                $processed++;
            } else {
                $error_msg = "Falha ao atualizar status do pedido #$order_id para created_assets_illustration";
                error_log("[TrinityKit FAL.AI] $error_msg");
                $errors[] = $error_msg;
                send_telegram_error_notification_falai("Pedido #$order_id: $error_msg");
            }
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit FAL.AI] Erros encontrados: " . implode(" | ", $errors));
    }
    
    return new WP_REST_Response(array(
        'message' => "Processamento síncrono do FAL.AI concluído. {$processed} pedidos finalizados com ilustrações geradas.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}

