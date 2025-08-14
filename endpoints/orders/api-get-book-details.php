<?php

/**
 * Endpoint para buscar detalhes do livro mais recente publicado
 * 
 * Este endpoint retorna informações básicas do livro mais recente
 * publicado no sistema, incluindo ID, nome e preço.
 * 
 * @since 1.0.0
 */

// Verifica se o arquivo está sendo acessado diretamente
if (!defined('ABSPATH')) {
    exit; // Sai se acessado diretamente
}

/**
 * Callback para obter detalhes do livro mais recente
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function trinitykitcms_get_book_details($request) {
    try {
        // Busca o livro mais recente publicado
        $args = array(
            'post_type' => 'book_templates',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $query->the_post();
            
            $post_id = get_the_ID();
            $book_price = get_field('book_price', $post_id);
            
            // Prepara a resposta
            $response_data = array(
                'id' => $post_id,
                'nome' => get_the_title(),
                'preco' => $book_price ? floatval($book_price) : 0.00
            );
            
            // Restaura os dados do post
            wp_reset_postdata();
            
            return array(
                'success' => true,
                'data' => $response_data,
                'message' => 'Detalhes do livro encontrados com sucesso'
            );
            
        } else {
            // Nenhum livro encontrado
            return new WP_Error(
                'no_book_found',
                'Nenhum livro publicado encontrado',
                array('status' => 404)
            );
        }
        
    } catch (Exception $e) {
        // Erro interno do servidor
        return new WP_Error(
            'internal_server_error',
            'Erro interno do servidor: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}

/**
 * Registra o endpoint da API para detalhes do livro
 */
function trinitykitcms_register_book_details_endpoint() {
    register_rest_route('trinitykitcms-api/v1', '/orders/book-details', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_book_details',
            'permission_callback' => '__return_true'
        )
    ));
}
add_action('rest_api_init', 'trinitykitcms_register_book_details_endpoint', 10);
