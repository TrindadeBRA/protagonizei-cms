<?php
/**
 * Handlers AJAX para o template de pedidos
 * 
 * @package TrinityKit
 */

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handler AJAX para verificar status do pedido
 */
function ajax_check_order_status() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'check_order_status')) {
        wp_send_json_error('Nonce inválido');
        return;
    }
    
    // Verificar permissões
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permissões insuficientes');
        return;
    }
    
    $order_id = intval($_POST['order_id']);
    
    if (!$order_id || get_post_type($order_id) !== 'orders') {
        wp_send_json_error('Pedido inválido');
        return;
    }
    
    $current_status = get_field('order_status', $order_id);
    
    wp_send_json_success(array(
        'status' => $current_status,
        'order_id' => $order_id
    ));
}

// Registrar handlers AJAX
add_action('wp_ajax_check_order_status', 'ajax_check_order_status');
add_action('wp_ajax_nopriv_check_order_status', 'ajax_check_order_status');
