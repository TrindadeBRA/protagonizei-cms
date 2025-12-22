<?php
/**
 * Webhook endpoint para atualização automática de status dos pedidos
 * 
 * Este endpoint verifica e atualiza pedidos:
 * - Pedidos entregues (delivered) há mais de 24h → Concluído (completed)
 * - Pedidos aguardando pagamento (awaiting_payment) há mais de 2h → Cancelado (canceled)
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/update-order-status', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_update_order_status_webhook',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Handles the order status update webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 * 
 * @since 1.0.0
 */
function trinitykit_handle_update_order_status_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    $completed_count = 0;
    $canceled_count = 0;
    $errors = array();

    // Data/hora atual
    $current_time = current_time('timestamp');
    
    // 1. Buscar pedidos entregues (delivered) há mais de 24h
    $delivered_orders = get_posts(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'delivered',
                'compare' => '='
            )
        )
    ));

    foreach ($delivered_orders as $order) {
        $order_id = $order->ID;
        $modified_time = strtotime($order->post_modified);
        $hours_since_modified = ($current_time - $modified_time) / 3600;

        // Se passou mais de 24 horas desde a última modificação
        if ($hours_since_modified >= 24) {
            $status_updated = update_field('order_status', 'completed', $order_id);
            
            if ($status_updated) {
                // Adiciona log
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-update-order-status',
                    "Pedido entregue há mais de 24h, status atualizado automaticamente para Concluído",
                    'delivered',
                    'completed'
                );
                
                $completed_count++;
            } else {
                $error_msg = "[TrinityKit] Falha ao atualizar status do pedido #$order_id de 'delivered' para 'completed'";
                error_log($error_msg);
                $errors[] = $error_msg;
            }
        }
    }

    // 2. Buscar pedidos aguardando pagamento (awaiting_payment) há mais de 2h
    $awaiting_payment_orders = get_posts(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'awaiting_payment',
                'compare' => '='
            )
        )
    ));

    foreach ($awaiting_payment_orders as $order) {
        $order_id = $order->ID;
        $modified_time = strtotime($order->post_modified);
        $hours_since_modified = ($current_time - $modified_time) / 3600;

        // Se passou mais de 2 horas desde a última modificação
        if ($hours_since_modified >= 2) {
            $status_updated = update_field('order_status', 'canceled', $order_id);
            
            if ($status_updated) {
                // Adiciona log
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-update-order-status',
                    "Pedido aguardando pagamento há mais de 2h, status atualizado automaticamente para Cancelado",
                    'awaiting_payment',
                    'canceled'
                );
                
                $canceled_count++;
            } else {
                $error_msg = "[TrinityKit] Falha ao atualizar status do pedido #$order_id de 'awaiting_payment' para 'canceled'";
                error_log($error_msg);
                $errors[] = $error_msg;
            }
        }
    }

    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento concluído. {$completed_count} pedidos concluídos, {$canceled_count} pedidos cancelados.",
        'completed' => $completed_count,
        'canceled' => $canceled_count,
        'errors' => $errors
    ), 200);
}

