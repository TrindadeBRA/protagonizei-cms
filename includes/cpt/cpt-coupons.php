<?php

/**
 * Registers the custom post type "Coupons".
 *
 * The coupon title is the code itself (case-insensitive matching handled at usage time).
 *
 * @since 1.0.0
 */
function register_coupons_post_type() {
	$labels = array(
		'name'                  => _x( 'Cupons', 'Nome do tipo de post' ),
		'singular_name'         => _x( 'Cupom', 'Nome singular do tipo de post' ),
		'menu_name'             => _x( 'Cupons', 'Nome do menu' ),
		'add_new'               => _x( 'Adicionar Novo', 'Novo item' ),
		'add_new_item'          => __( 'Adicionar Novo Cupom' ),
		'edit_item'             => __( 'Editar Cupom' ),
		'view_item'             => __( 'Ver Cupom' ),
		'all_items'             => __( 'Todos os Cupons' ),
		'search_items'          => __( 'Procurar Cupons' ),
		'not_found'             => __( 'Nenhum Cupom encontrado' ),
		'not_found_in_trash'    => __( 'Nenhum Cupom encontrado na lixeira' ),
		'archives'              => _x( 'Arquivos de Cupons', 'Cupom' )
	);

	$args = array(
		'labels'              => $labels,
		'public'              => false,
		'publicly_queryable'  => true, // Permite URLs públicas, mas controlado por template
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => true,
		'rewrite'             => array( 'slug' => 'coupons' ),
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => -0,
		'supports'            => array( 'title' ),
		'menu_icon'           => 'dashicons-tickets-alt',
	);

	register_post_type( 'coupons', $args );
}
add_action( 'init', 'register_coupons_post_type' );

/**
 * Restringe acesso aos cupons apenas para usuários autenticados com permissão
 */
function restrict_coupons_access() {
	if (is_singular('coupons') && !current_user_can('edit_posts')) {
		wp_die(__('Você não tem permissão para acessar esta página.'), 'Acesso Negado', array('response' => 403));
	}
}
add_action('template_redirect', 'restrict_coupons_access');

/**
 * Adiciona colunas personalizadas na listagem de cupons
 */
function coupons_columns( $columns ) {
	$columns['discount_type'] = 'Tipo';
	$columns['discount_value'] = 'Valor';
	unset( $columns['author'] );
	return $columns;
}
add_filter( 'manage_coupons_posts_columns', 'coupons_columns' );

/**
 * Preenche o conteúdo das colunas personalizadas
 */
function coupons_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'discount_type':
			$type = get_field( 'discount_type', $post_id );
			$label = $type === 'fixed' ? 'Desconto fixo' : ($type === 'percent' ? 'Desconto percentual' : '-');
			echo $label;
			break;
		case 'discount_value':
			$type = get_field( 'discount_type', $post_id );
			if ($type === 'fixed') {
				$value = get_field( 'discount_fixed_amount', $post_id );
				echo $value !== '' ? 'R$ ' . number_format((float)$value, 2, ',', '.') : '-';
			} elseif ($type === 'percent') {
				$value = get_field( 'discount_percentage', $post_id );
				echo $value !== '' ? number_format((float)$value, 2, ',', '.') . '%' : '-';
			} else {
				echo '-';
			}
			break;
		default:
			break;
	}
}
add_action( 'manage_coupons_posts_custom_column', 'coupons_column_content', 10, 2 );


