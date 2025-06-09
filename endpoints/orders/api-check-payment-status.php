<?php
/**
 * API Endpoint for checking payment status
 * 
 * This endpoint checks if an order has been paid
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/payment-status', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_check_payment_status',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Checks if an order has been paid
 * 
 * @param WP_REST_Request $request The request object containing the order_id parameter
 * @return WP_REST_Response|WP_Error Response object with payment status or error object
 * 
 * @since 1.0.0
 */
function trinitykit_check_payment_status($request) {
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

    // Get the order status
    $order_status = get_field('order_status', $order_id);
    
    // Check if the order is paid
    $is_paid = $order_status === 'paid';
    
    // Return the payment status
    return new WP_REST_Response(array(
        'message' => $is_paid ? 'Pedido já foi pago' : 'Pedido ainda não foi pago',
        'status' => $is_paid ? 'paid' : 'pending'
    ), 200);
}
