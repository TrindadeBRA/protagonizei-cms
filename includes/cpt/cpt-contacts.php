<?php

/**
 * Registers the custom post type "Contact Form".
 *
 * This function registers a custom post type called "Contact Form" with custom labels and arguments.
 *
 * @since 1.0.0
 */
function register_contact_form_post_type() {
    $labels = array(
        'name'                  => _x( 'Formulários de Contato', 'Nome do tipo de post' ),
        'singular_name'         => _x( 'Formulário de Contato', 'Nome singular do tipo de post' ),
        'menu_name'             => _x( 'Contatos', 'Nome do menu' ),
        'add_new'               => _x( 'Adicionar Novo', 'Novo item' ),
        'add_new_item'          => __( 'Adicionar Novo Formulário de Contato' ),
        'edit_item'             => __( 'Editar Formulário de Contato' ),
        'view_item'             => __( 'Ver Formulário de Contato' ),
        'all_items'             => __( 'Todos os Formulários de Contato' ),
        'search_items'          => __( 'Procurar Formulários de Contato' ),
        'not_found'             => __( 'Nenhum Formulário de Contato encontrado' ),
        'not_found_in_trash'    => __( 'Nenhum Formulário de Contato encontrado na lixeira' ),
        'featured_image'        => _x( 'Imagem de Destaque', 'Formulário de Contato' ),
        'set_featured_image'    => _x( 'Definir imagem de destaque', 'Formulário de Contato' ),
        'remove_featured_image' => _x( 'Remover imagem de destaque', 'Formulário de Contato' ),
        'use_featured_image'    => _x( 'Usar como imagem de destaque', 'Formulário de Contato' ),
        'archives'              => _x( 'Arquivos de Formulários de Contato', 'Formulário de Contato' ),
        'insert_into_item'      => _x( 'Inserir em Formulário de Contato', 'Formulário de Contato' ),
        'uploaded_to_this_item' => _x( 'Enviado para este Formulário de Contato', 'Formulário de Contato' ),
        'filter_items_list'     => _x( 'Filtrar lista de Formulários de Contato', 'Formulário de Contato' ),
        'items_list_navigation' => _x( 'Navegação lista de Formulários de Contato', 'Formulário de Contato' ),
        'items_list'            => _x( 'Lista de Formulários de Contato', 'Formulário de Contato' ),
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'contact_form' ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 0,
        'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
        'taxonomies'          => array('contact_tags'),
        'menu_icon'           => 'dashicons-email-alt',
    );
    register_post_type( 'contact_form', $args );
}
add_action( 'init', 'register_contact_form_post_type' );

function contact_form_columns( $columns ) {
    $columns['name'] = 'Nome';
    $columns['email'] = 'Email';
    $columns['phone'] = 'Telefone';
    $columns['message'] = 'Mensagem';
    unset( $columns['author'] );
    return $columns;
}
add_filter( 'manage_contact_form_posts_columns', 'contact_form_columns' );

function contact_form_column_content( $column, $post_id ) {
    switch ( $column ) {
        case 'name':
            echo get_field( 'name', $post_id );
            break;
        case 'email':
            echo get_field( 'email', $post_id );
            break;
        case 'phone':
            echo get_field( 'phone', $post_id );
            break;
        case 'message':
            echo get_post_field( 'post_content', $post_id );
            break;
        default:
            // Lidar com outras colunas, se necessário
            break;
    }
}
add_action( 'manage_contact_form_posts_custom_column', 'contact_form_column_content', 10, 2 );

/**
 * Adiciona funcionalidade de exportação para o Custom Post Type "Contact Form"
 * 
 * @since 1.0.0
 */

// Adiciona ação em massa para exportar leads
function add_export_bulk_action($bulk_actions) {
    $bulk_actions['export_leads'] = 'Exportar para CSV';
    return $bulk_actions;
}
add_filter('bulk_actions-edit-contact_form', 'add_export_bulk_action');

// Processa a ação em massa de exportação
function handle_export_bulk_action($redirect_to, $action, $post_ids) {
    if ($action !== 'export_leads') {
        return $redirect_to;
    }

    // Se não houver posts selecionados, retorna
    if (empty($post_ids)) {
        return $redirect_to;
    }

    // Configura o cabeçalho para download de CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=leads-exportados-' . date('Y-m-d') . '.csv');
    
    // Cria um arquivo PHP de saída
    $output = fopen('php://output', 'w');
    
    // Adiciona BOM para suporte UTF-8 em Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Cabeçalhos do CSV
    fputcsv($output, array(
        'ID', 
        'Data', 
        'Nome', 
        'Email',
        'Telefone',
        'Mensagem', 
        'Tags',
        'URL do Anexo'
    ));
    
    // Popula o CSV com os dados dos leads selecionados
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        
        if ($post->post_type !== 'contact_form') {
            continue;
        }
        
        $name = get_field('name', $post_id);
        $email = get_field('email', $post_id);
        $phone = get_field('phone', $post_id);
        $message = wp_strip_all_tags($post->post_content);
        
        // Obtém as tags do lead
        $tags = wp_get_post_terms($post_id, 'contact_tags', array('fields' => 'names'));
        $tags_string = !empty($tags) ? implode(', ', $tags) : '';
        
        // Obtém a URL do anexo
        $attachment_id = get_field('attachment', $post_id);
        $attachment_url = '';
        if ($attachment_id) {
            if (is_array($attachment_id)) {
                $attachment_url = $attachment_id['url'];
            } else {
                $attachment_url = wp_get_attachment_url($attachment_id);
            }
        }
        
        // Formata a data
        $post_date = get_the_date('d/m/Y H:i:s', $post_id);
        
        // Adiciona a linha no CSV
        fputcsv($output, array(
            $post_id,
            $post_date,
            $name,
            $email,
            $phone,
            $message,
            $tags_string,
            $attachment_url
        ));
    }
    
    fclose($output);
    exit();
}
add_filter('handle_bulk_actions-edit-contact_form', 'handle_export_bulk_action', 10, 3);

/**
 * Adiciona botão de exportar todos os leads na página de listagem
 */
function add_export_all_button() {
    global $current_screen;
    
    // Verifica se estamos na tela de listagem de contact_form
    if ($current_screen->post_type !== 'contact_form') {
        return;
    }
    
    // Obtém os parâmetros atuais da URL
    $current_url = add_query_arg(array(
        'post_type' => 'contact_form',
        'action' => 'export_all_leads'
    ));

    // Adiciona os filtros atuais à URL
    if (!empty($_GET['contact_tags'])) {
        $current_url = add_query_arg('contact_tags', $_GET['contact_tags'], $current_url);
    }
    if (!empty($_GET['m'])) {
        $current_url = add_query_arg('m', $_GET['m'], $current_url);
    }
    if (!empty($_GET['s'])) {
        $current_url = add_query_arg('s', $_GET['s'], $current_url);
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Adiciona o botão de exportar todos após o filtro de datas
        $('.tablenav.top .actions:last').append('<a href="<?php echo esc_url($current_url); ?>" class="button button-primary" style="margin-left: 5px;">Exportar Todos</a>');
    });
    </script>
    <?php
}
add_action('admin_footer', 'add_export_all_button');

/**
 * Processa a exportação de todos os leads
 */
function process_export_all_leads() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'export_all_leads' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'contact_form') {
        return;
    }
    
    // Verifica permissões
    if (!current_user_can('edit_posts')) {
        wp_die(__('Você não tem permissão para fazer isso.'));
    }
    
    // Configura o cabeçalho para download de CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=todos-leads-' . date('Y-m-d') . '.csv');
    
    // Cria um arquivo PHP de saída
    $output = fopen('php://output', 'w');
    
    // Adiciona BOM para suporte UTF-8 em Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Cabeçalhos do CSV
    fputcsv($output, array(
        'ID', 
        'Data', 
        'Nome', 
        'Email',
        'Telefone',
        'Mensagem', 
        'Tags',
        'URL do Anexo'
    ));
    
    // Prepara os argumentos da query com os filtros atuais
    $args = array(
        'post_type' => 'contact_form',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    // Adiciona filtro por tag se existir
    if (!empty($_GET['contact_tags'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'contact_tags',
                'field'    => 'slug',
                'terms'    => $_GET['contact_tags']
            )
        );
    }

    // Adiciona filtro por data se existir
    if (!empty($_GET['m'])) {
        $args['m'] = $_GET['m'];
    }

    // Adiciona filtro por busca se existir
    if (!empty($_GET['s'])) {
        $args['s'] = $_GET['s'];
    }
    
    $leads_query = new WP_Query($args);
    
    if ($leads_query->have_posts()) {
        while ($leads_query->have_posts()) {
            $leads_query->the_post();
            $post_id = get_the_ID();
            
            $name = get_field('name', $post_id);
            $email = get_field('email', $post_id);
            $phone = get_field('phone', $post_id);
            $message = wp_strip_all_tags(get_the_content());
            
            // Obtém as tags do lead
            $tags = wp_get_post_terms($post_id, 'contact_tags', array('fields' => 'names'));
            $tags_string = !empty($tags) ? implode(', ', $tags) : '';
            
            // Obtém a URL do anexo
            $attachment_id = get_field('attachment', $post_id);
            $attachment_url = '';
            if ($attachment_id) {
                if (is_array($attachment_id)) {
                    $attachment_url = $attachment_id['url'];
                } else {
                    $attachment_url = wp_get_attachment_url($attachment_id);
                }
            }
            
            // Formata a data
            $post_date = get_the_date('d/m/Y H:i:s');
            
            // Adiciona a linha no CSV
            fputcsv($output, array(
                $post_id,
                $post_date,
                $name,
                $email,
                $phone,
                $message,
                $tags_string,
                $attachment_url
            ));
        }
    }
    
    wp_reset_postdata();
    fclose($output);
    exit();
}
add_action('admin_init', 'process_export_all_leads');

/**
 * Adiciona filtro por tags na listagem de leads
 */
function add_tag_filter_to_contact_form() {
    global $typenow;
    
    // Verifica se estamos na página de listagem de contact_form
    if ($typenow === 'contact_form') {
        $taxonomy = 'contact_tags';
        $selected = isset($_GET['contact_tags']) ? $_GET['contact_tags'] : '';
        
        // Obtém todas as tags associadas ao post type
        $tags = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ));
        
        // Se existirem tags, mostra o select
        if (!empty($tags)) {
            echo '<select name="contact_tags" id="contact_tags" class="postform">';
            echo '<option value="">Todas as tags</option>';
            
            foreach ($tags as $tag) {
                printf(
                    '<option value="%s" %s>%s (%s)</option>',
                    $tag->slug,
                    $selected === $tag->slug ? ' selected="selected"' : '',
                    $tag->name,
                    $tag->count
                );
            }
            
            echo '</select>';
        }
    }
}
add_action('restrict_manage_posts', 'add_tag_filter_to_contact_form');

// Registra a taxonomia Contact Tags
function register_contact_tags_taxonomy() {
    $labels = array(
        'name'              => 'Tags de Contato',
        'singular_name'     => 'Tag de Contato',
        'search_items'      => 'Buscar Tags',
        'all_items'         => 'Todas as Tags',
        'edit_item'         => 'Editar Tag',
        'update_item'       => 'Atualizar Tag',
        'add_new_item'      => 'Adicionar Nova Tag',
        'new_item_name'     => 'Nova Tag de Contato',
        'menu_name'         => 'Tags de Contato'
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'contact-tags'),
        'show_in_rest'      => true
    );

    register_taxonomy('contact_tags', 'contact_form', $args);
}
add_action('init', 'register_contact_tags_taxonomy');