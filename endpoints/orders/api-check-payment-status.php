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

    if ($is_paid) {
        // Add log entry for order creation
        trinitykit_add_post_log(
            $order_id,
            'api-check-payment-status',
            "Status do pedido #$order_id confirmada API",
            '',
            'paid'
        );

        // Atualizar contato de Lead para Cliente
        $buyer_email = get_field('buyer_email', $order_id);
        $buyer_name = get_field('buyer_name', $order_id);
        $buyer_phone = get_field('buyer_phone', $order_id);
        
        if ($buyer_email) {
            $contact_updated = trinitykit_update_contact_to_client($buyer_email, $buyer_name, $buyer_phone, $order_id);
            
            if ($contact_updated && !is_wp_error($contact_updated)) {
                trinitykit_add_post_log(
                    $order_id,
                    'api-check-payment-status',
                    "Contato #$contact_updated atualizado de Lead para Cliente",
                    '',
                    'info'
                );
            }
        }
    }
    
    // Return the payment status
    return new WP_REST_Response(array(
        'message' => $is_paid ? 'Pedido já foi pago' : 'Pedido ainda não foi pago',
        'status' => $is_paid ? 'paid' : 'pending'
    ), 200);
}

/**
 * Atualiza ou cria um contato e marca como Cliente
 * 
 * @param string $email Email do contato
 * @param string $name Nome do contato
 * @param string $phone Telefone do contato
 * @param int $order_id ID do pedido relacionado
 * @return int|WP_Error ID do contato ou erro
 */
function trinitykit_update_contact_to_client($email, $name, $phone, $order_id = null) {
    // Buscar se já existe um contato com este email
    $existing_contacts = get_posts(array(
        'post_type' => 'contact_form',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'email',
                'value' => $email,
                'compare' => '='
            )
        )
    ));

    if (!empty($existing_contacts)) {
        // Atualizar contato existente
        $contact_id = $existing_contacts[0]->ID;
        
        // Atualizar campos se fornecidos
        if ($name) {
            update_field('name', $name, $contact_id);
        }
        if ($phone) {
            update_field('phone', $phone, $contact_id);
        }
        
        // Atualizar título do post
        if ($name) {
            wp_update_post(array(
                'ID' => $contact_id,
                'post_title' => $name . ' - ' . $email
            ));
        }
        
        // Remover tag "Lead" e adicionar "Cliente"
        wp_remove_object_terms($contact_id, 'Lead', 'contact_tags');
        wp_set_object_terms($contact_id, 'Cliente', 'contact_tags', true);
        
        return $contact_id;
    } else {
        // Se não existe, criar novo contato como Cliente
        $post_title = $name . ' - ' . $email;
        $post_content = $order_id ? "Contato criado automaticamente através da confirmação de pagamento do pedido #$order_id" : '';
        
        $contact_id = wp_insert_post(array(
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'contact_form',
        ));
        
        if (is_wp_error($contact_id)) {
            return $contact_id;
        }
        
        // Atualizar campos ACF
        update_field('email', $email, $contact_id);
        update_field('name', $name, $contact_id);
        update_field('phone', $phone, $contact_id);
        
        // Adicionar a tag Cliente
        wp_set_object_terms($contact_id, 'Cliente', 'contact_tags', false);
        
        return $contact_id;
    }
}
