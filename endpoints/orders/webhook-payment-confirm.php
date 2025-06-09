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
    
    // Log the raw request body
    error_log("[TrinityKit] Webhook recebido: " . $raw_body);
    
    // Return success response
    return new WP_REST_Response(array(
        'message' => 'Webhook recebido com sucesso'
    ), 200);
}
