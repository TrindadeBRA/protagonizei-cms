<?php

// Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Registra o Custom Post Type Produtos
function register_products_post_type() {
    $labels = array(
        'name'               => 'Produtos',
        'singular_name'      => 'Produto',
        'menu_name'         => 'Produtos',
        'add_new'           => 'Adicionar Novo',
        'add_new_item'      => 'Adicionar Novo Produto',
        'edit_item'         => 'Editar Produto',
        'new_item'          => 'Novo Produto',
        'view_item'         => 'Ver Produto',
        'search_items'      => 'Buscar Produtos',
        'not_found'         => 'Nenhum produto encontrado',
        'not_found_in_trash'=> 'Nenhum produto encontrado na lixeira'
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'has_archive'         => true,
        'publicly_queryable'  => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'products'),
        'capability_type'     => 'post',
        'hierarchical'        => false,
        'supports'            => array('title', 'excerpt'),
        'menu_position'       => -1,
        'menu_icon'           => 'dashicons-feedback',
        'show_in_rest'        => true
    );

    register_post_type('products', $args);
}
add_action('init', 'register_products_post_type');

// Registra a taxonomia Segmentos
function register_segments_taxonomy() {
    $labels = array(
        'name'              => 'Segmentos',
        'singular_name'     => 'Segmento',
        'search_items'      => 'Buscar Segmentos',
        'all_items'         => 'Todos os Segmentos',
        'edit_item'         => 'Editar Segmento',
        'update_item'       => 'Atualizar Segmento',
        'add_new_item'      => 'Adicionar Novo Segmento',
        'new_item_name'     => 'Novo Nome de Segmento',
        'menu_name'         => 'Segmentos'
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'segments'),
        'show_in_rest'      => true
    );

    register_taxonomy('segments', 'products', $args);
}
add_action('init', 'register_segments_taxonomy');

// Registra a taxonomia Linha de Produtos 
function register_product_lines_taxonomy() {
    $labels = array(
        'name'              => 'Linhas de Produtos',
        'singular_name'     => 'Linha de Produto',
        'search_items'      => 'Buscar Linhas',
        'all_items'         => 'Todas as Linhas',
        'parent_item'       => 'Linha Principal',
        'parent_item_colon' => 'Linha Principal:',
        'edit_item'         => 'Editar Linha',
        'update_item'       => 'Atualizar Linha',
        'add_new_item'      => 'Adicionar Nova Linha',
        'new_item_name'     => 'Nova Linha de Produto',
        'menu_name'         => 'Linhas de Produtos',
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'product-lines'),
        'show_in_rest'      => true,
        'sort'              => true,
    );

    register_taxonomy('product_lines', 'products', $args);
}
add_action('init', 'register_product_lines_taxonomy');

// Modify terms for product_lines taxonomy in metabox
// function modify_product_lines_metabox_terms($args, $taxonomy) {
//     if ($taxonomy === 'product_lines') {
//         // Force ordering and hierarchy in metabox
//         $args['orderby'] = 'parent';
//         $args['order'] = 'ASC';
//         $args['hierarchical'] = true;
        
//         // Remove any term limit
//         $args['number'] = 0;
        
//         // Get all terms, not just popular ones
//         $args['hide_empty'] = false;
        
//         // Disable accordion mode
//         $args['checked_ontop'] = false;
//     }
//     return $args;
// }
// add_filter('wp_terms_checklist_args', 'modify_product_lines_metabox_terms', 10, 2);