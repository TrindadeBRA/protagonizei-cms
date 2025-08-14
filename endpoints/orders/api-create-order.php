<?php
/**
 * API Endpoint for creating a new order
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_create_order',
        'permission_callback' => function () {
            return true; // We'll handle authentication in the callback
        }
    ));
});

/**
 * Create a new order
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response|WP_Error
 */
function trinitykit_create_order($request) {
    // Verify API key
    // $api_key = $request->get_header('X-API-Key');
    // if (!$api_key || $api_key !== get_option('trinitykitcms_api_key')) {
    //     return new WP_Error(
    //         'invalid_api_key',
    //         'API Key inválida ou não fornecida',
    //         array('status' => 401)
    //     );
    // }

    // Get and validate required fields
    $child_name = sanitize_text_field($request->get_param('childName'));
    $child_age = intval($request->get_param('childAge'));
    $child_gender = strtolower(sanitize_text_field($request->get_param('childGender')));
    $skin_tone = strtolower(sanitize_text_field($request->get_param('skinTone')));
    $name = sanitize_text_field($request->get_param('parentName'));
    $email = sanitize_email($request->get_param('email'));
    $phone = sanitize_text_field($request->get_param('phone'));
    $photo = $request->get_file_params()['photo'] ?? null;
    $coupon = sanitize_text_field($request->get_param('coupon'));

    // Validate required fields
    if (!$child_name || !$child_age || !$child_gender || !$skin_tone || !$name || !$email || !$phone || !$photo) {
        return new WP_Error(
            'missing_fields',
            'Todos os campos são obrigatórios',
            array('status' => 400)
        );
    }

    // Validate email
    if (!is_email($email)) {
        return new WP_Error(
            'invalid_email',
            'E-mail inválido',
            array('status' => 400)
        );
    }

    // Validate phone
    if (!preg_match('/^\(\d{2}\)\s\d{5}-\d{4}$/', $phone)) {
        return new WP_Error(
            'invalid_phone',
            'Telefone inválido. Use o formato (99) 99999-9999',
            array('status' => 400)
        );
    }

    // Validate gender
    if (!in_array($child_gender, ['boy', 'girl'], true)) {
        return new WP_Error(
            'invalid_gender',
            'Gênero inválido',
            array('status' => 400)
        );
    }

    // Validate skin tone
    if (!in_array($skin_tone, ['light', 'dark'], true)) {
        return new WP_Error(
            'invalid_skin_tone',
            'Tom de pele inválido',
            array('status' => 400)
        );
    }

    // Handle photo upload
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Validate file type
    $allowed_types = array('image/jpeg', 'image/png', 'image/jpg');
    if (!in_array($photo['type'], $allowed_types)) {
        return new WP_Error(
            'invalid_file_type',
            'Tipo de arquivo não permitido. Use apenas JPG, JPEG ou PNG',
            array('status' => 400)
        );
    }

    // Validate file size (5MB max)
    if ($photo['size'] > 5 * 1024 * 1024) {
        return new WP_Error(
            'file_too_large',
            'Arquivo muito grande. Tamanho máximo permitido: 5MB',
            array('status' => 400)
        );
    }

    // Prepare upload
    $upload = wp_handle_upload($photo, array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg' => 'image/jpeg',
            'png' => 'image/png'
        )
    ));
    
    if (isset($upload['error'])) {
        return new WP_Error(
            'upload_error',
            'Erro ao fazer upload da foto: ' . $upload['error'],
            array('status' => 400)
        );
    }

    // Create the order post
    $order_data = array(
        'post_title'    => "Pedido - $child_name",
        'post_status'   => 'publish',
        'post_type'     => 'orders',
    );

    $order_id = wp_insert_post($order_data);

    if (is_wp_error($order_id)) {
        return new WP_Error(
            'order_creation_failed',
            'Erro ao criar o pedido',
            array('status' => 500)
        );
    }

    // Update post title with ID
    wp_update_post(array(
        'ID' => $order_id,
        'post_title' => "#$order_id - $name - $child_name"
    ));

    // Create attachment
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title' => "$child_name - Pedido #$order_id",
        'post_content' => 'Imagem do rosto da criança para o pedido #' . $order_id,
        'post_status' => 'inherit',
    );

    $attachment_id = wp_insert_attachment($attachment, $upload['file']);

    if (is_wp_error($attachment_id)) {
        return new WP_Error(
            'attachment_error',
            'Erro ao criar anexo: ' . $attachment_id->get_error_message(),
            array('status' => 500)
        );
    }

    // Generate metadata
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    wp_update_attachment_metadata($attachment_id, $attachment_data);

    // Add custom metadata
    update_post_meta($attachment_id, '_wp_attachment_image_alt', "$child_name - Pedido #$order_id");
    update_post_meta($attachment_id, '_order_id', $order_id);
    update_post_meta($attachment_id, '_child_name', $child_name);
    update_post_meta($attachment_id, '_upload_date', current_time('mysql'));

    // Save ACF fields
    update_field('child_name', $child_name, $order_id);
    update_field('child_age', $child_age, $order_id);
    update_field('child_gender', $child_gender, $order_id);
    update_field('child_skin_tone', $skin_tone, $order_id);
    update_field('buyer_email', $email, $order_id);
    update_field('buyer_phone', $phone, $order_id);
    update_field('buyer_name', $name, $order_id);
    update_field('child_face_photo', $attachment_id, $order_id);
    update_field('order_status', 'created', $order_id);

    if (!empty($coupon)) {
        update_field('applied_coupon', $coupon, $order_id);
        trinitykit_add_post_log(
            $order_id,
            'api-create-order',
            "Cupom informado na criação do pedido: $coupon",
            '',
            'info'
        );
    }


    // Add log entry for order creation
    trinitykit_add_post_log(
        $order_id,
        'api-create-order',
        "Pedido criado com sucesso para $child_name (#$order_id)",
        '',
        'created'
    );

    // Buscar o modelo de livro mais recente publicado
    $latest_book_template = get_posts(array(
        'post_type' => 'book_templates',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    // Se encontrou um modelo, associa ao pedido
    if (!empty($latest_book_template)) {
        update_field('book_template', $latest_book_template[0]->ID, $order_id);
        
        // Adiciona log da associação
        trinitykit_add_post_log(
            $order_id,
            'api-create-order',
            "Modelo de livro #{$latest_book_template[0]->ID} associado automaticamente",
            '',
            'info'
        );
    }

    // Return success response
    return new WP_REST_Response(array(
        'message' => 'Pedido criado com sucesso',
        'order_id' => $order_id,
        'status' => 'created',
        'applied_coupon' => !empty($coupon) ? $coupon : null
    ), 201);
}