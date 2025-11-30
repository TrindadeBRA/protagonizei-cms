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
    $asaas_pix_key = get_option('trinitykitcms_asaas_pix_key');
    $asaas_api_url = get_option('trinitykitcms_asaas_api_url');
    $pix_endpoint = $asaas_api_url . '/pix/qrCodes/static';

    if (empty($asaas_api_key) || empty($asaas_api_url) || empty($asaas_pix_key)) {
        return new WP_Error(
            'asaas_settings_missing',
            'Configurações do Asaas ausentes. Verifique API URL, API Key e Chave PIX nas Integrações.',
            array('status' => 500)
        );
    }

    // Obter o modelo de livro do pedido (ACF post_object com return_format = object)
    $book_template = get_field('book_template', $order_id);
    $book_template_id = $book_template && isset($book_template->ID) ? (int) $book_template->ID : 0;

    if ($book_template_id <= 0) {
        return new WP_Error(
            'book_template_not_found',
            'Não foi possível identificar o modelo de livro relacionado a este pedido.',
            array('status' => 400)
        );
    }

    $book_price = (float) get_field('book_price', $book_template_id);
    $book_price = $book_price > 0 ? round($book_price, 2) : 0.0;
    if ($book_price <= 0) {
        return new WP_Error(
            'book_price_invalid',
            'Preço do livro não configurado ou inválido.',
            array('status' => 400)
        );
    }

    // Verificar se há cupom aplicado no pedido e calcular o preço final
    $final_price = $book_price;
    $applied_coupon = trim((string) get_field('applied_coupon', $order_id));
    if (!empty($applied_coupon)) {
        // Localizar cupom por título (case-insensitive)
        $coupon_post = get_page_by_title($applied_coupon, OBJECT, 'coupons');
        if (!$coupon_post) {
            $query = new WP_Query(array(
                'post_type' => 'coupons',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                's' => $applied_coupon,
                'fields' => 'ids',
            ));
            if ($query->have_posts()) {
                foreach ($query->posts as $coupon_id) {
                    $title = get_the_title($coupon_id);
                    if (mb_strtolower($title) === mb_strtolower($applied_coupon)) {
                        $coupon_post = get_post($coupon_id);
                        break;
                    }
                }
            }
            wp_reset_postdata();
        }

        if (!$coupon_post) {
            return new WP_Error(
                'coupon_not_found',
                'Cupom aplicado ao pedido é inválido ou não encontrado.',
                array('status' => 400)
            );
        }

        $discount_type = get_field('discount_type', $coupon_post->ID);
        if ($discount_type === 'fixed') {
            $amount = (float) get_field('discount_fixed_amount', $coupon_post->ID);
            if ($amount <= 0) {
                return new WP_Error(
                    'coupon_invalid',
                    'Cupom inválido: valor de desconto fixo não configurado.',
                    array('status' => 400)
                );
            }
            $final_price = max(0.0, round($book_price - $amount, 2));
        } elseif ($discount_type === 'percent') {
            $percent = (float) get_field('discount_percentage', $coupon_post->ID);
            if ($percent <= 0 || $percent > 100) {
                return new WP_Error(
                    'coupon_invalid',
                    'Cupom inválido: percentual de desconto não configurado corretamente.',
                    array('status' => 400)
                );
            }
            $final_price = max(0.0, round($book_price * (1 - ($percent / 100)), 2));
        } else {
            return new WP_Error(
                'coupon_invalid',
                'Cupom inválido: tipo de desconto desconhecido.',
                array('status' => 400)
            );
        }
    }

    // Make request to create PIX QR Code
    $response = wp_remote_post($pix_endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'access_token' => $asaas_api_key
        ),
        'body' => json_encode(array(
            'addressKey' => $asaas_pix_key,
            'value' => $final_price,
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
        'qr_code_copypaste' => $body['payload'] ?? null,
        'price' => $final_price
    ), 200);
} 