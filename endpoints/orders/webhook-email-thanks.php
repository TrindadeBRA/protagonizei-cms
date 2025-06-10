<?php
/**
 * Webhook endpoint for sending thank you emails
 * 
 * This endpoint checks for paid orders and sends thank you emails
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/send-thanks', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_thank_webhook',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Handles the thank you webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 * 
 * @since 1.0.0
 */
function trinitykit_handle_thank_webhook($request) {
    error_log("[TrinityKit] Iniciando processamento do webhook de agradecimento");
    
    // Get all orders with status 'paid'
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'paid',
                'compare' => '='
            )
        )
    );

    $paid_orders = get_posts($args);
    error_log("[TrinityKit] Encontrados " . count($paid_orders) . " pedidos com status 'paid'");
    
    $processed = 0;
    $errors = array();

    foreach ($paid_orders as $order) {
        $order_id = $order->ID;
        error_log("[TrinityKit] Processando pedido #$order_id");
        
        // Get order details
        $name = get_field('buyer_name', $order_id);
        $buyer_email = get_field('buyer_email', $order_id);
        $order_total = get_field('order_total', $order_id);
        
        error_log("[TrinityKit] Dados do pedido #$order_id - Nome: $name, Email: $buyer_email, Total: $order_total");
        
        // Skip if required fields are empty
        if (empty($name) || empty($buyer_email)) {
            $error_msg = "[TrinityKit] Campos obrigatórios vazios para o pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }
        
        // Validate email format
        if (!is_email($buyer_email)) {
            $error_msg = "[TrinityKit] Email inválido para o pedido #$order_id: $buyer_email";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }
        
        // Ensure order_total is not null and is numeric
        $order_total = is_numeric($order_total) ? floatval($order_total) : 0;
        
        // Prepare email content
        $subject = 'Obrigado pela sua compra!';
        $message = "Olá {$name},\n\n";
        $message .= "Agradecemos imensamente pela sua compra!\n\n";
        $message .= "Detalhes do seu pedido:\n";
        $message .= "Número do pedido: #{$order_id}\n";
        $message .= "Valor total: R$ " . number_format($order_total, 2, ',', '.') . "\n\n";
        $message .= "Estamos muito felizes em tê-lo como cliente!\n\n";
        $message .= "Atenciosamente,\nEquipe Protagonizei";
        
        // Send email
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: noreply@protagonizei.com <noreply@protagonizei.com>'
        );
        
        // Add debug information
        error_log("[TrinityKit] Tentando enviar email para: $buyer_email");
        error_log("[TrinityKit] Assunto: $subject");
        
        $sent = wp_mail($buyer_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log("[TrinityKit] Email enviado com sucesso para o pedido #$order_id");
            
            // Update order status to thanked
            $update_status = update_field('order_status', 'thanked', $order_id);
            
            if ($update_status) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-email-thanks',
                    "Email de agradecimento enviado para o pedido #$order_id",
                    'paid',
                    'thanked'
                );
                
                $processed++;
            } else {
                $error_msg = "[TrinityKit] Falha ao atualizar status do pedido #$order_id";
                error_log($error_msg);
                $errors[] = $error_msg;
            }
        } else {
            global $phpmailer;
            $error_msg = "[TrinityKit] Falha ao enviar email para o pedido #$order_id";
            if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                $error_msg .= " - Erro PHPMailer: " . $phpmailer->ErrorInfo;
            }
            error_log($error_msg);
            $errors[] = $error_msg;
        }
    }
    
    error_log("[TrinityKit] Processamento concluído. $processed pedidos processados.");
    if (!empty($errors)) {
        error_log("[TrinityKit] Erros encontrados: " . implode(" | ", $errors));
    }
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento concluído. {$processed} pedidos processados.",
        'processed' => $processed,
        'errors' => $errors
    ), 200);
} 