<?php

/**
 * Registers the custom post type "Orders".
 *
 * This function registers a custom post type called "Orders" with custom labels and arguments.
 *
 * @since 1.0.0
 */
function register_orders_post_type() {
    $labels = array(
        'name'                  => _x( 'Pedidos', 'Nome do tipo de post' ),
        'singular_name'         => _x( 'Pedido', 'Nome singular do tipo de post' ),
        'menu_name'             => _x( 'Pedidos', 'Nome do menu' ),
        'add_new'               => _x( 'Adicionar Novo', 'Novo item' ),
        'add_new_item'          => __( 'Adicionar Novo Pedido' ),
        'edit_item'             => __( 'Editar Pedido' ),
        'view_item'             => __( 'Ver Pedido' ),
        'all_items'             => __( 'Todos os Pedidos' ),
        'search_items'          => __( 'Procurar Pedidos' ),
        'not_found'             => __( 'Nenhum Pedido encontrado' ),
        'not_found_in_trash'    => __( 'Nenhum Pedido encontrado na lixeira' ),
        'featured_image'        => _x( 'Imagem de Destaque', 'Pedido' ),
        'set_featured_image'    => _x( 'Definir imagem de destaque', 'Pedido' ),
        'remove_featured_image' => _x( 'Remover imagem de destaque', 'Pedido' ),
        'use_featured_image'    => _x( 'Usar como imagem de destaque', 'Pedido' ),
        'archives'              => _x( 'Arquivos de Pedidos', 'Pedido' ),
        'insert_into_item'      => _x( 'Inserir em Pedido', 'Pedido' ),
        'uploaded_to_this_item' => _x( 'Enviado para este Pedido', 'Pedido' ),
        'filter_items_list'     => _x( 'Filtrar lista de Pedidos', 'Pedido' ),
        'items_list_navigation' => _x( 'Navegação lista de Pedidos', 'Pedido' ),
        'items_list'            => _x( 'Lista de Pedidos', 'Pedido' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'orders' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => -1,
        'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
        'menu_icon'           => 'dashicons-cart',
    );
    register_post_type( 'orders', $args );
}
add_action( 'init', 'register_orders_post_type' );

/**
 * Adiciona colunas personalizadas na listagem de pedidos
 */
function orders_columns( $columns ) {
    $columns['order_status'] = 'Status';
    $columns['buyer_email'] = 'Email do Comprador';
    $columns['applied_coupon'] = 'Cupom Usado';
    $columns['child_name'] = 'Nome da Criança';
    $columns['payment_date'] = 'Data do Pagamento';
    unset( $columns['author'] );
    return $columns;
}
add_filter( 'manage_orders_posts_columns', 'orders_columns' );

/**
 * Preenche o conteúdo das colunas personalizadas
 */
function orders_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'order_status':
            $status = get_field( 'order_status', $post_id );
            $status_labels = array(
                'created' => 'Criado',
                'awaiting_payment' => 'Aguardando Pagamento',
                'paid' => 'Pago',
                'thanked' => 'Agradecido',
                'created_assets_text' => 'Assets de Texto Criados',
                'created_assets_illustration' => 'Assets de Ilustração Criados',
                'created_assets_merge' => 'Assets Finais Criados',
                'ready_for_delivery' => 'Pronto para Entrega',
                'delivered' => 'Entregue',
                'completed' => 'Concluído (Entregue e PDF Gerado)',
                'canceled' => 'Cancelado',
                'error' => 'Erro'
            );
            echo isset($status_labels[$status]) ? $status_labels[$status] : $status;
            break;
        case 'child_name':
            echo get_field( 'child_name', $post_id );
            break;
        case 'buyer_email':
            echo get_field( 'buyer_email', $post_id );
            break;
        case 'applied_coupon':
            $coupon = get_field( 'applied_coupon', $post_id );
            echo $coupon ? esc_html($coupon) : '-';
            break;
        case 'payment_date':
            $date = get_field( 'payment_date', $post_id );
            echo $date ? date('d/m/Y H:i', strtotime($date)) : '-';
            break;
        default:
            break;
    }
}
add_action( 'manage_orders_posts_custom_column', 'orders_column_content', 10, 2 );

/**
 * Adiciona filtro por status na listagem de pedidos
 */
function add_status_filter_to_orders() {
    global $typenow;
    
    if ($typenow === 'orders') {
        $current_status = isset($_GET['order_status']) ? $_GET['order_status'] : '';
        
        $statuses = array(
            'created' => 'Criado',
            'awaiting_payment' => 'Aguardando Pagamento',
            'paid' => 'Pago',
            'thanked' => 'Agradecido',
            'created_assets_text' => 'Assets de Texto Criados',
            'created_assets_illustration' => 'Assets de Ilustração Criados',
            'created_assets_merge' => 'Assets Finais Criados',
            'ready_for_delivery' => 'Pronto para Entrega',
            'delivered' => 'Entregue',
            'completed' => 'Concluído (Entregue e PDF Gerado)',
            'canceled' => 'Cancelado',
            'error' => 'Erro'
        );
        
        echo '<select name="order_status" id="order_status" class="postform">';
        echo '<option value="">Todos os status</option>';
        
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                $value,
                $current_status === $value ? ' selected="selected"' : '',
                $label
            );
        }
        
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'add_status_filter_to_orders');