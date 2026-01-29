<?php
/**
 * API Endpoint for creating a credit card payment for an order
 *
 * This endpoint creates a credit card payment for a specific order using the Asaas API.
 *
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/credit-card', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_create_credit_card_payment',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Creates a credit card payment for an order using the Asaas API
 *
 * @param WP_REST_Request $request The request object containing the order_id parameter
 * @return WP_REST_Response|WP_Error Response object with payment data or error object
 */
function trinitykit_create_credit_card_payment($request) {
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
    $asaas_api_url = get_option('trinitykitcms_asaas_api_url');
    $customers_endpoint = $asaas_api_url . '/customers';
    $payments_endpoint = $asaas_api_url . '/lean/payments';

    if (empty($asaas_api_key) || empty($asaas_api_url)) {
        return new WP_Error(
            'asaas_settings_missing',
            'Configurações do Asaas ausentes. Verifique API URL e API Key nas Integrações.',
            array('status' => 500)
        );
    }

    // Normalize and validate payment fields
    $holder_name = sanitize_text_field($request->get_param('holderName'));
    $card_number = preg_replace('/\D/', '', (string) $request->get_param('cardNumber'));
    $cvc = preg_replace('/\D/', '', (string) $request->get_param('cvc'));
    $document = preg_replace('/\D/', '', (string) $request->get_param('document'));
    $installments = intval($request->get_param('installments'));

    $expiry_month = preg_replace('/\D/', '', (string) $request->get_param('expiryMonth'));
    $expiry_year = preg_replace('/\D/', '', (string) $request->get_param('expiryYear'));
    $expiry = preg_replace('/\D/', '', (string) $request->get_param('expiry'));

    if (empty($expiry_month) || empty($expiry_year)) {
        if (strlen($expiry) === 4) {
            $expiry_month = substr($expiry, 0, 2);
            $expiry_year = '20' . substr($expiry, 2, 2);
        } elseif (strlen($expiry) === 6) {
            $expiry_month = substr($expiry, 0, 2);
            $expiry_year = substr($expiry, 2, 4);
        }
    }

    if (!$holder_name || !$card_number || !$cvc || !$document || !$expiry_month || !$expiry_year) {
        return new WP_Error(
            'missing_fields',
            'Todos os campos do cartão são obrigatórios',
            array('status' => 400)
        );
    }

    if (!preg_match('/^\d{13,19}$/', $card_number)) {
        return new WP_Error(
            'invalid_card_number',
            'Número do cartão inválido',
            array('status' => 400)
        );
    }

    $month_int = intval($expiry_month);
    if ($month_int < 1 || $month_int > 12 || strlen($expiry_year) !== 4) {
        return new WP_Error(
            'invalid_expiry',
            'Validade do cartão inválida',
            array('status' => 400)
        );
    }

    if (!preg_match('/^\d{3,4}$/', $cvc)) {
        return new WP_Error(
            'invalid_cvc',
            'CVV inválido',
            array('status' => 400)
        );
    }

    if (!preg_match('/^\d{11}$/', $document)) {
        return new WP_Error(
            'invalid_document',
            'CPF inválido',
            array('status' => 400)
        );
    }

    if ($installments < 1 || $installments > 3) {
        return new WP_Error(
            'invalid_installments',
            'Parcelamento inválido. Selecione entre 1 e 3 parcelas.',
            array('status' => 400)
        );
    }

    // Buyer data (from request or order)
    $buyer_name = sanitize_text_field($request->get_param('buyerName'));
    $buyer_email = sanitize_email($request->get_param('buyerEmail'));
    $buyer_phone = sanitize_text_field($request->get_param('buyerPhone'));

    if (empty($buyer_name)) {
        $buyer_name = (string) get_field('buyer_name', $order_id);
    }
    if (empty($buyer_email)) {
        $buyer_email = (string) get_field('buyer_email', $order_id);
    }
    if (empty($buyer_phone)) {
        $buyer_phone = (string) get_field('buyer_phone', $order_id);
    }

    if (!$buyer_name || !$buyer_email || !$buyer_phone || !is_email($buyer_email)) {
        return new WP_Error(
            'invalid_buyer',
            'Dados do comprador inválidos',
            array('status' => 400)
        );
    }

    $buyer_phone_digits = preg_replace('/\D/', '', (string) $buyer_phone);

    // Book price and coupon logic (same as PIX)
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

    $final_price = $book_price;
    $applied_coupon = trim((string) get_field('applied_coupon', $order_id));
    if (!empty($applied_coupon)) {
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


    $rate_config = array(
        // juros aplicado por parcela adicional (ex.: 2x, 3x)
        'jurosPorParcelaPercent' => 1.5,
    );

    $calculate_total = function($amount, $installments, $rate_config) {
        $additional = max($installments - 1, 0);
        $total = $amount * (1 + ($rate_config['jurosPorParcelaPercent'] / 100) * $additional);
        return round($total, 2);
    };

    // Create or reuse Asaas customer
    $customer_id = (string) get_field('customer_id', $order_id);
    if (empty($customer_id)) {
        $customer_payload = array(
            'name' => $buyer_name,
            'cpfCnpj' => $document,
            'email' => $buyer_email,
            'mobilePhone' => $buyer_phone_digits,
        );

        $customer_response = wp_remote_post($customers_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'access_token' => $asaas_api_key
            ),
            'body' => json_encode($customer_payload),
        ));

        if (is_wp_error($customer_response)) {
            $error_message = $customer_response->get_error_message();
            error_log("[TrinityKit] Erro ao criar cliente Asaas: $error_message");
            return new WP_Error(
                'asaas_customer_error',
                'Erro ao criar cliente Asaas: ' . $error_message,
                array('status' => 500)
            );
        }

        $customer_body = json_decode(wp_remote_retrieve_body($customer_response), true);
        $customer_status = wp_remote_retrieve_response_code($customer_response);

        if ($customer_status !== 200 && $customer_status !== 201) {
            $error_description = isset($customer_body['errors']) ? $customer_body['errors'][0]['description'] : 'Erro desconhecido';
            error_log("[TrinityKit] Erro ao criar cliente Asaas: $error_description");
            return new WP_Error(
                'asaas_customer_error',
                'Erro ao criar cliente Asaas: ' . $error_description,
                array('status' => $customer_status)
            );
        }

        $customer_id = $customer_body['id'] ?? '';
        if (empty($customer_id)) {
            return new WP_Error(
                'asaas_customer_error',
                'Erro ao criar cliente Asaas: resposta inválida',
                array('status' => 500)
            );
        }

        update_field('customer_id', $customer_id, $order_id);
    }

    $payment_payload = array(
        'customer' => $customer_id,
        'billingType' => 'CREDIT_CARD',
        'value' => $final_price,
        'dueDate' => date('Y-m-d'),
        'description' => 'Pagamento do pedido #' . $order_id,
        'externalReference' => (string) $order_id,
        'creditCard' => array(
            'holderName' => $holder_name,
            'number' => $card_number,
            'expiryMonth' => $expiry_month,
            'expiryYear' => $expiry_year,
            'ccv' => $cvc,
        ),
        'creditCardHolderInfo' => array(
            'name' => $buyer_name,
            'email' => $buyer_email,
            'cpfCnpj' => $document,
            'phone' => $buyer_phone_digits,
            'mobilePhone' => $buyer_phone_digits,
        ),
    );

    $total_value = $calculate_total($final_price, $installments, $rate_config);
    $installment_value = round($total_value / $installments, 2);
    $last_installment_value = round($total_value - ($installment_value * ($installments - 1)), 2);

    if ($installments > 1) {
        $payment_payload['installmentCount'] = $installments;
        $payment_payload['installmentValue'] = $installment_value;
        $payment_payload['totalValue'] = $total_value;
    }

    $remote_ip = $request->get_header('X-Forwarded-For');
    if (!empty($remote_ip)) {
        $remote_ip = explode(',', $remote_ip)[0];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $remote_ip = $_SERVER['REMOTE_ADDR'];
    }
    if (!empty($remote_ip)) {
        $payment_payload['remoteIp'] = $remote_ip;
    }

    $payment_response = wp_remote_post($payments_endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'access_token' => $asaas_api_key
        ),
        'body' => json_encode($payment_payload),
    ));

    if (is_wp_error($payment_response)) {
        $error_message = $payment_response->get_error_message();
        error_log("[TrinityKit] Erro ao criar pagamento cartão: $error_message");
        return new WP_Error(
            'asaas_credit_card_error',
            'Erro ao criar pagamento cartão: ' . $error_message,
            array('status' => 500)
        );
    }

    $payment_body = json_decode(wp_remote_retrieve_body($payment_response), true);
    $payment_status = wp_remote_retrieve_response_code($payment_response);

    if ($payment_status !== 200 && $payment_status !== 201) {
        $error_description = isset($payment_body['errors']) ? $payment_body['errors'][0]['description'] : 'Erro desconhecido';
        error_log("[TrinityKit] Erro ao criar pagamento cartão: $error_description");
        return new WP_Error(
            'asaas_credit_card_error',
            'Erro ao criar pagamento cartão: ' . $error_description,
            array('status' => $payment_status)
        );
    }

    $payment_id = $payment_body['id'] ?? null;
    $payment_status_value = $payment_body['status'] ?? null;

    if ($payment_id) {
        update_field('payment_transaction_id', $payment_id, $order_id);
    }
    update_field('payment_amount', $final_price, $order_id);
    update_field('order_status', 'awaiting_payment', $order_id);

    trinitykit_add_post_log(
        $order_id,
        'api-create-credit-card-payment',
        "Pagamento cartão criado com sucesso para pedido #$order_id",
        'created',
        'awaiting_payment'
    );

    return new WP_REST_Response(array(
        'message' => 'Pagamento cartão criado com sucesso',
        'payment_id' => $payment_id,
        'status' => $payment_status_value,
        'customer_id' => $customer_id,
        'price' => $final_price,
        'total_value' => $total_value ?? $final_price,
        'installment_value' => $installment_value ?? $final_price,
        'last_installment_value' => $last_installment_value ?? $final_price
    ), 200);
}
