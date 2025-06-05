<?php

// Verifica se o arquivo está sendo acessado diretamente
if (!defined('ABSPATH')) {
    exit;
}

function decode_html_entities($data) {
    if (is_array($data)) {
        return array_map('decode_html_entities', $data);
    } elseif (is_string($data)) {
        return html_entity_decode($data);
    }
    return $data;
}

function trinitykitcms_get_products($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    $args = array(
        'post_type' => 'products',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $products = get_posts($args);
    $formatted_products = array();

    foreach ($products as $product) {
        // Obtém as taxonomias
        $segments = wp_get_post_terms($product->ID, 'segments', array('fields' => 'all'));
        $product_lines = wp_get_post_terms($product->ID, 'product_lines', array('fields' => 'all'));

        // Obtém os campos ACF
        $cas_number = get_field('cas_number', $product->ID);

        // Função auxiliar para formatar taxonomia com hierarquia
        $format_taxonomy_with_hierarchy = function($terms) {
            $formatted_terms = array();
            $terms_by_id = array();
            
            // Primeiro, organiza todos os termos por ID
            foreach ($terms as $term) {
                $terms_by_id[$term->term_id] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'children' => array()
                );
            }
            
            // Depois, organiza a hierarquia
            foreach ($terms as $term) {
                if ($term->parent == 0) {
                    // É um termo raiz
                    $formatted_terms[] = &$terms_by_id[$term->term_id];
                } else {
                    // É um termo filho
                    $terms_by_id[$term->parent]['children'][] = &$terms_by_id[$term->term_id];
                }
            }
            
            return $formatted_terms;
        };

        $formatted_products[] = array(
            'id' => $product->ID,
            'title' => decode_html_entities($product->post_title),
            'cas_number' => decode_html_entities($cas_number),
            'segments' => decode_html_entities($format_taxonomy_with_hierarchy($segments)),
            'product_lines' => decode_html_entities($format_taxonomy_with_hierarchy($product_lines))
        );
    }

    return array(
        'success' => true,
        'data' => $formatted_products,
        'items' => count($formatted_products)
    );
}

/**
 * Registra os endpoints da API para produtos
 */
function trinitykitcms_register_products_endpoints() {
    register_rest_route('trinitykitcms-api/v1', '/products', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_products',
            'permission_callback' => '__return_true'
        )
    ));
}
add_action('rest_api_init', 'trinitykitcms_register_products_endpoints');