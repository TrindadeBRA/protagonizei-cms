<?php
/**
 * Webhook endpoint for payment confirmation
 * 
 * This endpoint receives payment confirmation notifications and logs them
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/payment-confirm', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_handle_payment_webhook',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Handles the payment confirmation webhook
 * 
 * @param WP_REST_Request $request The request object containing the webhook data
 * @return WP_REST_Response Response object
 * 
 * @since 1.0.0
 */
function trinitykit_handle_payment_webhook($request) {
    // Get the raw request body
    $raw_body = $request->get_body();
    
    // Parse the JSON body
    $data = json_decode($raw_body, true);
    
    // Validate the webhook data
    $event = $data['event'] ?? null;
    $allowed_events = array('PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED');
    if (!$event || !in_array($event, $allowed_events, true)) {
        error_log("[TrinityKit] Evento inválido no webhook: " . ($event ?? 'não definido'));
        return new WP_REST_Response(array(
            'message' => 'Evento inválido'
        ), 400);
    }
    
    if (!isset($data['payment']['externalReference'])) {
        error_log("[TrinityKit] Referência externa não encontrada no webhook");
        return new WP_REST_Response(array(
            'message' => 'Referência externa não encontrada'
        ), 400);
    }
    
    // Get the order ID from external reference
    $order_id = $data['payment']['externalReference'];
    
    // Get the order post
    $order = get_post($order_id);
    
    if (!$order || $order->post_type !== 'orders') {
        error_log("[TrinityKit] Pedido não encontrado: " . $order_id);
        return new WP_REST_Response(array(
            'message' => 'Pedido não encontrado'
        ), 404);
    }
    
    // Update order status to paid
    $status_updated = update_field('order_status', 'paid', $order_id);
    if (!$status_updated) {
        error_log("[TrinityKit] Falha ao atualizar status do pedido #$order_id para 'paid'");
    }
    
    // Save payment transaction ID
    $transaction_updated = update_field('payment_transaction_id', $data['payment']['id'], $order_id);
    if (!$transaction_updated) {
        error_log("[TrinityKit] Falha ao salvar ID da transação para o pedido #$order_id");
    }
    
    // Save payment date
    $date_updated = update_field('payment_date', $data['payment']['paymentDate'], $order_id);
    if (!$date_updated) {
        error_log("[TrinityKit] Falha ao salvar data do pagamento para o pedido #$order_id");
    }

    // Save payment amount
    $amount_updated = update_field('payment_amount', $data['payment']['value'], $order_id);
    if (!$amount_updated) {
        error_log("[TrinityKit] Falha ao salvar valor do pagamento para o pedido #$order_id");
    }

    // Remove PIX QR Code and PIX Code
    update_field('payment_qr_code', '', $order_id);
    update_field('payment_qr_code_text', '', $order_id);
    
    // Add log entry for order creation
    trinitykit_add_post_log(
        $order_id,
        'webhook-payment-confirm',
        "Pedido #$order_id atualizado para pago",
        'awaiting_payment',
        'paid'
    );
    
    // Return success response
    return new WP_REST_Response(array(
        'message' => 'Webhook processado com sucesso'
    ), 200);
}
