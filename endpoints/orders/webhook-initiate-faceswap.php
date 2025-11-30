<?php
/**
 * Webhook endpoint for initiating face swap process
 * 
 * This endpoint finds orders with status 'created_assets_text' and initiates face swap
 * for each page, saving the task IDs returned by the FaceSwap API
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
    register_rest_route('trinitykitcms-api/v1', '/webhook/initiate-faceswap', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_initiate_faceswap_webhook',
    ));
});

// Removida normalização de tom de pele; valores canônicos agora são 'claro' e 'escuro'

/**
 * Initiate face swap with FaceSwap API
 * 
 * @param string $swap_image_url URL da imagem do rosto da criança
 * @param string $target_image_url URL da imagem base para aplicar o face swap
 * @return string|false Task ID retornado pela API ou false em caso de erro
 */
function initiate_face_swap_with_faceswap($swap_image_url, $target_image_url) {
    $api_key = get_option('trinitykitcms_faceswap_api_key');
    $base_url = get_option('trinitykitcms_faceswap_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit] Configurações do FaceSwap não encontradas");
        return false;
    }

    // URL para iniciar o face swap
    $run_url = $base_url . '/run';
    
    $headers = [
        'Content-Type: application/json',
        'x-magicapi-key: ' . $api_key
    ];

    $body = [
        'input' => [
            'swap_image' => $swap_image_url,
            'target_image' => $target_image_url,
            'target_face_index' => 1
        ]
    ];

    // Requisição para iniciar o face swap
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $run_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("[TrinityKit] Erro cURL na requisição de face swap: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("[TrinityKit] Erro FaceSwap API ($http_code): " . $response);
        return false;
    }
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit] Resposta JSON inválida do FaceSwap: " . json_last_error_msg());
        return false;
    }
    
    return $response_data['id'] ?? false;
}

/**
 * Send Telegram notification for errors
 */
function send_telegram_error_notification($message, $title = "Erro no FaceSwap") {
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
        error_log("[TrinityKit] Erro ao enviar notificação Telegram: " . $e->getMessage());
    }
}

/**
 * Handles the initiate face swap webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_initiate_faceswap_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    // Get all orders with status 'created_assets_text' and face_swap_initiated = false
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
                    'key' => 'face_swap_initiated',
                    'value' => '1',
                    'compare' => '!='
                ),
                array(
                    'key' => 'face_swap_initiated',
                    'compare' => 'NOT EXISTS'
                )
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
            error_log("[TrinityKit] $error_msg");
            $errors[] = $error_msg;
            
            // Send Telegram notification
            send_telegram_error_notification("Pedido #$order_id: $error_msg");
            continue;
        }

        $template_pages = get_field('template_book_pages', $book_template->ID);
        $page_errors = 0;
        $page_error_messages = array(); // Array para armazenar mensagens de erro específicas de páginas
        $initiated_pages = 0;
        $skipped_pages = 0;
        
        // Process each page individually
        foreach ($template_pages as $index => $page) {
            // Check if this page should skip face swap
            $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
            
            if ($skip_faceswap) {
                // For pages that skip face swap, we need to copy the base illustration directly
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
                
                if (!empty($base_image) && !empty($base_image['ID'])) {
                    // Copy the base illustration directly to generated_illustration
                    $field_key = "generated_book_pages_{$index}_generated_illustration";
                    $update_result = update_field($field_key, $base_image['ID'], $order_id);
                    
                    // Also mark skip_faceswap in the generated page
                    $skip_field_key = "generated_book_pages_{$index}_skip_faceswap";
                    update_field($skip_field_key, true, $order_id);
                    
                    if ($update_result) {
                        $skipped_pages++;
                        error_log("[TrinityKit] Página $index do pedido #$order_id pulou face swap - ilustração base copiada diretamente");
                    } else {
                        $error_msg = "Falha ao copiar ilustração base da página $index do pedido #$order_id (página sem face swap)";
                        error_log("[TrinityKit] $error_msg");
                        $page_errors++;
                        $page_error_messages[] = $error_msg;
                        send_telegram_error_notification("Pedido #$order_id: $error_msg");
                    }
                } else {
                    $error_msg = "Imagem base não encontrada para página $index do pedido #$order_id (página sem face swap)";
                    error_log("[TrinityKit] $error_msg");
                    $page_errors++;
                    $page_error_messages[] = $error_msg;
                    send_telegram_error_notification("Pedido #$order_id: $error_msg");
                }
                continue;
            }
            
            // Find the correct base illustration in the base_illustrations repeater
            $base_illustrations = $page['base_illustrations'];
            $base_image = null;
            
            if (!empty($base_illustrations)) {
                $available_combos = array();
                foreach ($base_illustrations as $illustration) {
                    $ill_gender = strtolower(trim((string) ($illustration['gender'] ?? '')));
                    $ill_skin = strtolower(trim((string) ($illustration['skin_tone'] ?? '')));
                    $available_combos[] = $ill_gender . ':' . $ill_skin;

                    if ($ill_gender === $child_gender && $ill_skin === $child_skin_tone) {
                        $base_image = $illustration['illustration_asset'];
                        error_log("[TrinityKit] Base encontrada (pedido #$order_id, página $index): genero=$child_gender, tom=$child_skin_tone, url=" . ($base_image['url'] ?? 'sem url'));
                        break;
                    }
                }
                if (empty($base_image)) {
                    error_log("[TrinityKit] Nenhuma base casou exatamente (pedido #$order_id, página $index). Solicitado genero=$child_gender, tom=$child_skin_tone. Disponíveis: " . implode(',', $available_combos));
                }
            }
            
            if (empty($base_image)) {
                $error_msg = "Imagem base não encontrada para página $index do pedido #$order_id (gênero: $child_gender, tom: $child_skin_tone)";
                error_log("[TrinityKit] $error_msg");
                $page_errors++;
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
                continue;
            }

            // Image URLs
            $swap_image_url = $child_face_photo['url'];
            $target_image_url = $base_image['url'];

            // Initiate face swap
            $task_id = initiate_face_swap_with_faceswap($swap_image_url, $target_image_url);
            
            if ($task_id === false) {
                $error_msg = "Erro ao iniciar face swap da página $index do pedido #$order_id";
                error_log("[TrinityKit] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
                continue;
            }

            // Save the task ID using ACF
            $field_key = "generated_book_pages_{$index}_faceswap_task_id";
            $update_result = update_field($field_key, $task_id, $order_id);
            
            if ($update_result) {
                $initiated_pages++;
                error_log("[TrinityKit] Face swap iniciado para página $index do pedido #$order_id - Task ID: $task_id");
            } else {
                $error_msg = "Falha ao salvar task ID da página $index do pedido #$order_id";
                error_log("[TrinityKit] $error_msg");
                $page_errors++;
                $page_error_messages[] = $error_msg;
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
            }
        }

        // Mark face swap as initiated if all pages were processed successfully
        // (initiated pages + skipped pages should equal total pages)
        $total_processed_pages = $initiated_pages + $skipped_pages;
        
        // Se houver erros, adicionar aos erros globais mas ainda tentar processar se possível
        if ($page_errors > 0) {
            $error_msg = "Falha ao iniciar face swap para $page_errors páginas do pedido #$order_id. Erros: " . implode(' | ', $page_error_messages);
            error_log("[TrinityKit] $error_msg");
            $errors[] = $error_msg;
            
            // Se não conseguiu processar nenhuma página, pular este pedido
            if ($total_processed_pages === 0) {
                continue;
            }
        }
        
        // Marcar como iniciado se todas as páginas foram processadas (com sucesso ou puladas)
        if ($total_processed_pages === count($template_pages)) {
            $face_swap_initiated = update_field('face_swap_initiated', true, $order_id);
            
            if ($face_swap_initiated) {
                // Add log entry
                $log_message = "Face swap iniciado para $initiated_pages páginas";
                if ($skipped_pages > 0) {
                    $log_message .= " e $skipped_pages páginas sem face swap";
                }
                $log_message .= " de $child_name";
                
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-initiate-faceswap',
                    $log_message,
                    'created_assets_text',
                    'created_assets_text'
                );
                
                $processed++;
                error_log("[TrinityKit] Face swap iniciado com sucesso para pedido #$order_id ($initiated_pages páginas com face swap, $skipped_pages sem face swap)");
            } else {
                $error_msg = "Falha ao marcar face_swap_initiated para pedido #$order_id";
                error_log("[TrinityKit] $error_msg");
                $errors[] = $error_msg;
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
            }
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit] Erros encontrados: " . implode(" | ", $errors));
    }
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento de iniciação do face swap concluído. {$processed} pedidos processados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
