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
    $processed = 0;
    $errors = array();

    foreach ($paid_orders as $order) {
        $order_id = $order->ID;
        
        // Get order details
        $name = get_field('buyer_name', $order_id);
        $buyer_email = get_field('buyer_email', $order_id);
        $order_total = get_field('payment_amount', $order_id);
        $gender = get_field('child_gender', $order_id);
        
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
        
        // Get email template
        require_once __DIR__ . '/email/thank-you-template.php';
        $subject = 'Obrigado pela sua compra!';
        $message = trinitykit_get_thank_you_email_template($name, $order_id, $order_total, $gender);
        
        // Send email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Protagonizei <noreply@protagonizei.com>'
        );
        
        $sent = wp_mail($buyer_email, $subject, $message, $headers);
        
        if ($sent) {
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