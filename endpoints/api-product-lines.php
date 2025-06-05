<?php

if (!defined('ABSPATH')) {
    exit;
}

function trinitykitcms_get_products_by_product_line($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    // Obtém o slug da linha de produtos da URL
    $line_slug = $request['slug'];

    // Verifica se a linha de produtos existe
    $line = get_term_by('slug', $line_slug, 'product_lines');
    if (!$line) {
        return new WP_Error(
            'line_not_found',
            'Linha de produtos não encontrada',
            array('status' => 404)
        );
    }

    // Argumentos para buscar produtos da linha específica
    $args = array(
        'post_type' => 'products',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'product_lines',
                'field' => 'slug',
                'terms' => $line_slug
            )
        )
    );

    $products = get_posts($args);
    $organized_products = array();

    // Obter subcategorias da linha de produtos
    $subcategories = get_terms(array(
        'taxonomy' => 'product_lines',
        'parent' => $line->term_id,
        'hide_empty' => true
    ));

    // Organizar a estrutura base
    foreach ($subcategories as $subcategory) {
        $organized_products[$subcategory->term_id] = array(
            'name' => $subcategory->name,
            'slug' => $subcategory->slug,
            'products' => array() // Garantir que a chave 'products' exista
        );
    }

    // Obter imagens e descrição do product_lines pai
    $parent_images = array_map(function($image) {
        return $image['url'];
    }, get_field('product_line_banner', 'product_lines_' . $line->term_id) ?: []);

    $organized_products['parent'] = array(
        'name' => $line->name,
        'slug' => $line->slug,
        'description' => term_description($line->term_id, 'product_lines'),
        'images' => $parent_images,
    );

    // Distribuir os produtos nas subcategorias
    foreach ($products as $product) {
        $product_lines = wp_get_post_terms($product->ID, 'product_lines', array(
            'fields' => 'all'
        ));

        $product_data = array(
            'id' => $product->ID,
            'title' => $product->post_title,
            'cas_number' => get_field('cas_number', $product->ID)
        );

        foreach ($product_lines as $line) {
            if ($line->parent > 0) { // É uma subcategoria
                // Adiciona o produto à subcategoria correta
                $organized_products[$line->term_id]['products'][] = $product_data;
            }
        }
    }

    // Adiciona as subcategorias ao retorno
    $final_data = array(
        'parent' => $organized_products['parent'],
        'subcategories' => array_values(array_filter($organized_products, function($key) {
            return $key !== 'parent'; // Exclui a categoria pai
        }, ARRAY_FILTER_USE_KEY))
    );

    return array(
        'success' => true,
        'data' => $final_data,
        'items' => count($products)
    );
}

/**
 * Registra o endpoint da API para produtos por linha
 */
function trinitykitcms_register_product_line_endpoints() {
    register_rest_route('trinitykitcms-api/v1', '/product-line/(?P<slug>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_products_by_product_line',
            'permission_callback' => '__return_true'
        )
    ));
}
add_action('rest_api_init', 'trinitykitcms_register_product_line_endpoints'); 