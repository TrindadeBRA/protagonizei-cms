<?php
/**
 * Webhook endpoint for creating text assets
 * 
 * This endpoint checks for thanked orders and creates text assets
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/generate-text-assets', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_text_assets_webhook',
    ));
});

/**
 * Process text with Deepseek API
 * 
 * @param string $original_text The original text to process
 * @param string $child_name The child's name to replace
 * @return string|false Processed text or false on error
 */
function process_text_with_deepseek($original_text, $child_name) {
    $api_key = get_option('trinitykitcms_deepseek_api_key');
    $base_url = get_option('trinitykitcms_deepseek_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit] Configurações do Deepseek não encontradas");
        return false;
    }

    $url = $base_url . '/chat/completions';
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];

    $body = [
        'model' => 'deepseek-chat',
        'messages' => [
            [
                'role' => 'system',
                'content' => "INSTRUÇÕES PARA SUBSTITUIÇÃO INTELIGENTE DE {nome}:

1. ANALISE o texto original para detectar se há um nome completo (ex: 'Lucas Trindade') ou apenas um nome simples (ex: 'Lucas') no lugar onde está {nome}
2. REGRAS DE SUBSTITUIÇÃO:
   - Se o texto original tem um nome COMPLETO (2+ palavras): use o nome completo da criança se disponível, senão apenas o primeiro nome
   - Se o texto original tem apenas UM NOME: use apenas o primeiro nome da criança
3. Mantenha TODO o resto do texto ABSOLUTAMENTE IDÊNTICO (pontuação, formatação, quebras de linha)
4. Substitua APENAS {nome}, não invente ou altere nada mais"
            ],
            [
                'role' => 'user',
                'content' => "Texto original:\n" . $original_text . "\n\nNome completo da criança: " . $child_name . "\n\nFaça a substituição inteligente seguindo as regras."
            ]
        ],
        'temperature' => 0.1,
        'max_tokens' => 2000
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("[TrinityKit] Erro cURL: " . curl_error($ch));
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code !== 200) {
        error_log("[TrinityKit] Erro Deepseek API ($http_code): " . $response);
        return false;
    }
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit] Resposta JSON inválida: " . json_last_error_msg());
        return false;
    }
    
    $processed_text = $response_data['choices'][0]['message']['content'];
    
    // Log para debug da substituição inteligente
    error_log("[TrinityKit] DEBUG - Substituição inteligente realizada:");
    error_log("[TrinityKit] DEBUG - Nome fornecido: " . $child_name);
    error_log("[TrinityKit] DEBUG - Texto original: " . mb_substr($original_text, 0, 100, 'UTF-8') . "...");
    error_log("[TrinityKit] DEBUG - Texto processado: " . mb_substr($processed_text, 0, 100, 'UTF-8') . "...");
    
    return $processed_text;
}

/**
 * Handles the text assets webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_text_assets_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    // Get all orders with status 'thanked'
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'thanked',
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
        $child_gender = get_field('child_gender', $order_id);
        $book_template = get_field('book_template', $order_id);
        
        // Skip if required fields are empty
        if (empty($child_name) || empty($child_gender) || !$book_template) {
            $error_msg = "[TrinityKit] Campos obrigatórios vazios para o pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        $template_pages = get_field('template_book_pages', $book_template->ID);
        $generated_pages = array();
        $page_errors = 0;

        foreach ($template_pages as $index => $page) {
            $base_text = ($child_gender === 'boy') ? 
                $page['base_text_content_boy'] : 
                $page['base_text_content_girl'];

            $processed_text = process_text_with_deepseek($base_text, $child_name);
            
            if ($processed_text === false) {
                $error_msg = "[TrinityKit] Erro ao processar página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                continue;
            }

            $generated_pages[] = array(
                'generated_text_content' => $processed_text
            );
        }

        if ($page_errors > 0) {
            $error_msg = "[TrinityKit] Falha ao processar $page_errors páginas do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        // Update order with generated pages
        $pages_updated = update_field('generated_book_pages', $generated_pages, $order_id);
        $status_updated = update_field('order_status', 'created_assets_text', $order_id);
        
        if ($pages_updated && $status_updated) {
            // Add log entry
            trinitykit_add_post_log(
                $order_id,
                'webhook-text-assets',
                "Textos personalizados gerados para $child_name",
                'thanked',
                'created_assets_text'
            );
            
            $processed++;
        } else {
            $error_msg = "[TrinityKit] Falha ao atualizar campos do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit] Erros encontrados: " . implode(" | ", $errors));
    }
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento concluído. {$processed} pedidos processados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
