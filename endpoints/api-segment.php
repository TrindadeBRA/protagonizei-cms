<?php

if (!defined('ABSPATH')) {
    exit;
}

function trinitykitcms_get_products_by_segment($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    // Obtém o slug do segmento da URL
    $segment_slug = $request['slug'];

    // Verifica se o segmento existe
    $segment = get_term_by('slug', $segment_slug, 'segments');
    if (!$segment) {
        return new WP_Error(
            'segment_not_found',
            'Segmento não encontrado',
            array('status' => 404)
        );
    }

    // Argumentos para buscar produtos do segmento específico
    $args = array(
        'post_type' => 'products',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'tax_query' => array(
            array(
                'taxonomy' => 'segments',
                'field' => 'slug',
                'terms' => $segment_slug
            )
        )
    );

    $products = get_posts($args);
    $organized_products = array();

    // Primeiro, vamos buscar todas as categorias principais
    $main_categories = get_terms(array(
        'taxonomy' => 'product_lines',
        'parent' => 0,
        'hide_empty' => true
    ));

    // Organizar a estrutura base
    foreach ($main_categories as $main_category) {
        $organized_products[$main_category->term_id] = array(
            'name' => $main_category->name,
            'slug' => $main_category->slug,
            'description' => term_description($main_category->term_id, 'product_lines'),
            'images' => array_map(function($image) {
                return $image['url'];
            }, get_field('product_line_banner', 'product_lines_' . $main_category->term_id) ?: []),
            'categories' => array()
        );

        // Buscar subcategorias
        $categories = get_terms(array(
            'taxonomy' => 'product_lines',
            'parent' => $main_category->term_id,
            'hide_empty' => true
        ));

        foreach ($categories as $subcategory) {
            $organized_products[$main_category->term_id]['categories'][$subcategory->term_id] = array(
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
                'products' => array()
            );
        }
    }

    // Agora distribuir os produtos
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
                $parent_id = $line->parent;
                if (isset($organized_products[$parent_id])) {
                    $organized_products[$parent_id]['categories'][$line->term_id]['products'][] = $product_data;
                }
            }
        }
    }

    // Limpar categorias vazias e transformar em array
    $final_products = array();
    foreach ($organized_products as $category) {
        if (!empty($category['categories'])) {
            $categories = array();
            foreach ($category['categories'] as $subcategory) {
                if (!empty($subcategory['products'])) {
                    $categories[] = $subcategory;
                }
            }
            if (!empty($categories)) {
                $category['categories'] = $categories;
                $final_products[] = $category;
            }
        }
    }

    return array(
        'success' => true,
        'data' => $final_products,
        'items' => count($products)
    );
}

/**
 * Registra o endpoint da API para produtos por segmento
 */
function trinitykitcms_register_segment_endpoints() {
    register_rest_route('trinitykitcms-api/v1', '/segment/(?P<slug>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_products_by_segment',
            'permission_callback' => '__return_true'
        )
    ));
}
add_action('rest_api_init', 'trinitykitcms_register_segment_endpoints');