<?php
/**
 * API Endpoint for checking and applying a coupon to an order's base price
 *
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoints
add_action('rest_api_init', function () {
	// New route using book_id as query param
	register_rest_route('trinitykitcms-api/v1', '/orders/check-coupon', array(
		'methods' => 'GET',
		'callback' => 'trinitykit_check_coupon',
		'permission_callback' => function () { return true; }
	));

	// Backwards compatibility: legacy route using order_id in path
	register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/check-coupon', array(
		'methods' => 'GET',
		'callback' => 'trinitykit_check_coupon',
		'permission_callback' => function () { return true; }
	));
});

/**
 * Checks a coupon for an order and returns the updated price
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function trinitykit_check_coupon($request) {
    $coupon_input = trim((string) $request->get_param('coupon'));
    $book_template_id = (int) $request->get_param('book_id');

    if ($coupon_input === '') {
        return new WP_Error(
            'missing_coupon',
            'Cupom é obrigatório.',
            array('status' => 400)
        );
    }

    // Fallback: derive book_id from legacy order_id route
    if ($book_template_id <= 0) {
        $order_id = (int) $request->get_param('order_id');
        if ($order_id > 0) {
            $order = get_post($order_id);
            if ($order && $order->post_type === 'orders') {
                $book_template = get_field('book_template', $order_id);
                if ($book_template && isset($book_template->ID)) {
                    $book_template_id = (int) $book_template->ID;
                }
            }
        }
    }

    if ($book_template_id <= 0) {
        return new WP_Error(
            'book_template_not_found',
            'Livro não encontrado. Informe um book_id válido.',
            array('status' => 404)
        );
    }

    $book_post = get_post($book_template_id);
    if (!$book_post || $book_post->post_type !== 'book_templates') {
        return new WP_Error(
            'book_template_not_found',
            'Livro não encontrado. Informe um book_id válido.',
            array('status' => 404)
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

    // Find coupon by title (case-insensitive)
    $coupon_post = get_page_by_title($coupon_input, OBJECT, 'coupons');
    if (!$coupon_post) {
        // Fallback: search and compare case-insensitively
        $query = new WP_Query(array(
            'post_type' => 'coupons',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $coupon_input,
            'fields' => 'ids',
        ));
        if ($query->have_posts()) {
            foreach ($query->posts as $coupon_id) {
                $title = get_the_title($coupon_id);
                if (mb_strtolower($title) === mb_strtolower($coupon_input)) {
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
            'Cupom inválido ou não encontrado.',
            array('status' => 404)
        );
    }

    // Read coupon fields
    $discount_type = get_field('discount_type', $coupon_post->ID); // 'fixed' | 'percent'
    $final_price = $book_price;

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

    return new WP_REST_Response(array(
        'price' => $final_price,
    ), 200);
}


