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
        $child_gender = get_field('child_gender', $order_id);
        $child_skin_tone = get_field('child_skin_tone', $order_id);
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
        $initiated_pages = 0;
        
        // Process each page individually
        foreach ($template_pages as $index => $page) {
            // Find the correct base illustration in the base_illustrations repeater
            $base_illustrations = $page['base_illustrations'];
            $base_image = null;
            
            if (!empty($base_illustrations)) {
                foreach ($base_illustrations as $illustration) {
                    if ($illustration['gender'] === $child_gender && $illustration['skin_tone'] === $child_skin_tone) {
                        $base_image = $illustration['illustration_asset'];
                        break;
                    }
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
                send_telegram_error_notification("Pedido #$order_id: $error_msg");
            }
        }

        if ($page_errors > 0) {
            $error_msg = "Falha ao iniciar face swap para $page_errors páginas do pedido #$order_id";
            error_log("[TrinityKit] $error_msg");
            $errors[] = $error_msg;
            continue;
        }

        // Mark face swap as initiated if all pages were processed successfully
        if ($initiated_pages === count($template_pages)) {
            $face_swap_initiated = update_field('face_swap_initiated', true, $order_id);
            
            if ($face_swap_initiated) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-initiate-faceswap',
                    "Face swap iniciado para todas as $initiated_pages páginas de $child_name",
                    'created_assets_text',
                    'created_assets_text'
                );
                
                $processed++;
                error_log("[TrinityKit] Face swap iniciado com sucesso para pedido #$order_id ($initiated_pages páginas)");
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
