<?php

/**
 * Registers the custom post type "Social Media".
 *
 * This function registers a custom post type called "Social Media" with custom labels and arguments.
 *
 * @since 1.0.0
 */
function register_social_media_post_type() {
    $labels = array(
        'name'                  => _x( 'Posts de Mídia Social', 'Nome do tipo de post' ),
        'singular_name'         => _x( 'Post de Mídia Social', 'Nome singular do tipo de post' ),
        'menu_name'             => _x( 'Mídia Social', 'Nome do menu' ),
        'add_new'               => _x( 'Adicionar Novo', 'Novo item' ),
        'add_new_item'          => __( 'Adicionar Novo Post de Mídia Social' ),
        'edit_item'             => __( 'Editar Post de Mídia Social' ),
        'view_item'             => __( 'Ver Post de Mídia Social' ),
        'all_items'             => __( 'Todos os Posts de Mídia Social' ),
        'search_items'          => __( 'Procurar Posts de Mídia Social' ),
        'not_found'             => __( 'Nenhum Post de Mídia Social encontrado' ),
        'not_found_in_trash'    => __( 'Nenhum Post de Mídia Social encontrado na lixeira' ),
        'featured_image'        => _x( 'Imagem de Destaque', 'Post de Mídia Social' ),
        'set_featured_image'    => _x( 'Definir imagem de destaque', 'Post de Mídia Social' ),
        'remove_featured_image' => _x( 'Remover imagem de destaque', 'Post de Mídia Social' ),
        'use_featured_image'    => _x( 'Usar como imagem de destaque', 'Post de Mídia Social' ),
        'archives'              => _x( 'Arquivos de Posts de Mídia Social', 'Post de Mídia Social' ),
        'insert_into_item'      => _x( 'Inserir em Post de Mídia Social', 'Post de Mídia Social' ),
        'uploaded_to_this_item' => _x( 'Enviado para este Post de Mídia Social', 'Post de Mídia Social' ),
        'filter_items_list'     => _x( 'Filtrar lista de Posts de Mídia Social', 'Post de Mídia Social' ),
        'items_list_navigation' => _x( 'Navegação lista de Posts de Mídia Social', 'Post de Mídia Social' ),
        'items_list'            => _x( 'Lista de Posts de Mídia Social', 'Post de Mídia Social' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'social-media' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 0,
        'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields' ),
        'taxonomies'          => array( 'post_tag' ),
        'menu_icon'           => 'dashicons-share',
    );
    register_post_type( 'social_media', $args );
}
add_action( 'init', 'register_social_media_post_type' );
