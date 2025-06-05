<?php

// Verifica se o arquivo está sendo acessado diretamente
if (!defined('ABSPATH')) {
    exit; // Sai se acessado diretamente
}

function trinitykitcms_get_post_by_slug($request) {
    // TODO: OCULTANDO PARA USAR NA ROTA DE PREVIEW SEM EXPORTAR A API KEY
    // Valida a API key
    // $api_validation = trinitykitcms_validate_api_key($request);
    // if (is_wp_error($api_validation)) {
    //     return $api_validation;
    // }

    // Obtém o parâmetro slug da requisição
    $slug = $request->get_param('slug');
    
    if (empty($slug)) {
        return new WP_Error(
            'slug_required',
            'O parâmetro slug é obrigatório',
            array('status' => 400)
        );
    }

    // Configura os argumentos da query
    $args = array(
        'name' => $slug,
        'post_type' => 'post',
        'post_status' => array('publish', 'draft', 'private'),
        'numberposts' => 1
    );

    // Obtém o post
    $posts = get_posts($args);

    if (empty($posts)) {
        return new WP_Error(
            'post_not_found',
            'Post não encontrado',
            array('status' => 404)
        );
    }

    $post = $posts[0];
    $post_id = $post->ID;
    $featured_image_url = get_the_post_thumbnail_url($post_id, 'full');

    // Prepara os dados do post
    $post_data = array(
        'slug' => $post->post_name,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'created_at' => $post->post_date,
        'featured_image_url' => $featured_image_url ? $featured_image_url : ''
    );

    return array(
        'success' => true,
        'data' => $post_data
    );
}

/**
 * Registra o endpoint da API para buscar post por slug
 */
function trinitykitcms_register_post_by_slug_endpoint() {
    register_rest_route('trinitykitcms-api/v1', '/post/(?P<slug>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_post_by_slug',
            'permission_callback' => '__return_true',
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($param) {
                        return !empty($param);
                    }
                )
            )
        )
    ));
}

add_action('rest_api_init', 'trinitykitcms_register_post_by_slug_endpoint', 10);
