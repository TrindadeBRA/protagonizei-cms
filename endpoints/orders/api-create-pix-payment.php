<?php
/**
 * API Endpoint for creating a PIX payment for an order
 * 
 * This endpoint creates a static PIX QR Code for a specific order using the Asaas API.
 * The QR Code can be used to make a payment of R$ 49.99 and expires in 24 hours.
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/pix', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_create_pix_key',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Creates a static PIX QR Code for an order using the Asaas API
 * 
 * @param WP_REST_Request $request The request object containing the order_id parameter
 * @return WP_REST_Response|WP_Error Response object with PIX data or error object
 * 
 * @since 1.0.0
 */
function trinitykit_create_pix_key($request) {
    $order_id = $request->get_param('order_id');
    
    // Get the order post
    $order = get_post($order_id);
    
    if (!$order || $order->post_type !== 'orders') {
        return new WP_Error(
            'order_not_found',
            'Pedido não encontrado',
            array('status' => 404)
        );
    }

    $asaas_api_key = get_option('trinitykitcms_asaas_api_key');
    $asaas_wallet_id = get_option('trinitykitcms_asaas_wallet_id');
    $asaas_api_url = get_option('trinitykitcms_asaas_api_url');
    $pix_endpoint = $asaas_api_url . '/pix/qrCodes/static';

    // Make request to create PIX QR Code
    $response = wp_remote_post($pix_endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'access_token' => $asaas_api_key
        ),
        'body' => json_encode(array(
            'addressKey' => $asaas_wallet_id,
            'value' => 49.99,
            'description' => 'Pagamento do pedido #' . $order_id,
            'formOfPayment' => 'ALL',
            'allowsMultiplePayments' => false,
            'externalReference' => $order_id,
            'expiresAt' => date('Y-m-d H:i:s', strtotime('+1 day'))
        )),
        //'timeout' => 30
    ));

    // Handle WordPress HTTP request errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("[TrinityKit] Erro ao criar chave PIX: $error_message");
        return new WP_Error(
            'asaas_pix_key_error',
            'Erro ao criar chave PIX: ' . $error_message,
            array('status' => 500)
        );
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $status_code = wp_remote_retrieve_response_code($response);

    // Handle Asaas API errors
    if ($status_code !== 200) {
        $error_description = isset($body['errors']) ? $body['errors'][0]['description'] : 'Erro desconhecido';
        error_log("[TrinityKit] Erro ao criar chave PIX: $error_description");
        return new WP_Error(
            'asaas_pix_key_error',
            'Erro ao criar chave PIX: ' . $error_description,
            array('status' => $status_code)
        );
    }

    // Update order status to awaiting payment
    update_field('order_status', 'awaiting_payment', $order_id);

    // Add log entry for order creation
    trinitykit_add_post_log(
        $order_id,
        'api-create-pix-payment',
        "Pix criado com sucesso para pedido #$order_id",
        'created',
        'awaiting_payment'
    );

    // Return only the relevant data for the frontend
    return new WP_REST_Response(array(
        'message' => 'Pix criado com sucesso',
        'pix_key_id' => $body['id'] ?? null,
        'qr_code_image' => $body['encodedImage'] ?? null,
        'qr_code_copypaste' => $body['payload'] ?? null
    ), 200);
} 