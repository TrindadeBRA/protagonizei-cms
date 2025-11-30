<?php

/**
 * Registers the custom post type "Book Templates".
 *
 * This function registers a custom post type called "Book Templates" with custom labels and arguments.
 *
 * @since 1.0.0
 */
function register_book_templates_post_type() {
    $labels = array(
        'name'                  => _x( 'Modelos de Livros', 'Nome do tipo de post' ),
        'singular_name'         => _x( 'Modelo de Livro', 'Nome singular do tipo de post' ),
        'menu_name'             => _x( 'Livros', 'Nome do menu' ),
        'add_new'               => _x( 'Adicionar Novo', 'Novo item' ),
        'add_new_item'          => __( 'Adicionar Novo Modelo de Livro' ),
        'edit_item'             => __( 'Editar Modelo de Livro' ),
        'view_item'             => __( 'Ver Modelo de Livro' ),
        'all_items'             => __( 'Todos os Modelos de Livros' ),
        'search_items'          => __( 'Procurar Modelos de Livros' ),
        'not_found'             => __( 'Nenhum Modelo de Livro encontrado' ),
        'not_found_in_trash'    => __( 'Nenhum Modelo de Livro encontrado na lixeira' ),
        'featured_image'        => _x( 'Imagem de Destaque', 'Modelo de Livro' ),
        'set_featured_image'    => _x( 'Definir imagem de destaque', 'Modelo de Livro' ),
        'remove_featured_image' => _x( 'Remover imagem de destaque', 'Modelo de Livro' ),
        'use_featured_image'    => _x( 'Usar como imagem de destaque', 'Modelo de Livro' ),
        'archives'              => _x( 'Arquivos de Modelos de Livros', 'Modelo de Livro' ),
        'insert_into_item'      => _x( 'Inserir em Modelo de Livro', 'Modelo de Livro' ),
        'uploaded_to_this_item' => _x( 'Enviado para este Modelo de Livro', 'Modelo de Livro' ),
        'filter_items_list'     => _x( 'Filtrar lista de Modelos de Livros', 'Modelo de Livro' ),
        'items_list_navigation' => _x( 'Navegação lista de Modelos de Livros', 'Modelo de Livro' ),
        'items_list'            => _x( 'Lista de Modelos de Livros', 'Modelo de Livro' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'book-templates' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => -10,
        'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
        'menu_icon'           => 'dashicons-book-alt',
    );
    register_post_type( 'book_templates', $args );
}
add_action( 'init', 'register_book_templates_post_type' );

/**
 * Adiciona colunas personalizadas na listagem de modelos de livros
 */
function book_templates_columns( $columns ) {
    $columns['total_pages'] = 'Total de Páginas';
    unset( $columns['author'] );
    return $columns;
}
add_filter( 'manage_book_templates_posts_columns', 'book_templates_columns' );

/**
 * Preenche o conteúdo das colunas personalizadas
 */
function book_templates_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'total_pages':
            $pages = get_field( 'template_book_pages', $post_id );
            echo $pages ? count($pages) : '0';
            break;
        default:
            break;
    }
}
add_action( 'manage_book_templates_posts_custom_column', 'book_templates_column_content', 10, 2 ); 