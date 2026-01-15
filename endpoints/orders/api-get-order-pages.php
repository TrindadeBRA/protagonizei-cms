<?php
/**
 * API Endpoint para obter páginas do livro de um pedido
 * 
 * Este endpoint retorna as páginas finais do livro personalizado no formato
 * usado pelo frontend para exibição no flipbook.
 * 
 * Requisitos:
 * - Pedido deve existir
 * - Pedido deve estar nos status: 'ready_for_delivery', 'delivered' ou 'completed'
 * - Páginas devem ter sido geradas (campo final_page_with_text)
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/pages', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_get_order_pages',
        'permission_callback' => '__return_true', // Não requer autenticação
        'args' => array(
            'order_id' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param) && intval($param) > 0;
                },
                'sanitize_callback' => 'absint'
            )
        )
    ));
});

/**
 * Obtém as páginas do livro de um pedido
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response|WP_Error
 */
function trinitykit_get_order_pages($request) {
    $order_id = intval($request['order_id']);
    
    try {
        // Verificar se o pedido existe
        $order = get_post($order_id);
        if (!$order || $order->post_type !== 'orders') {
            return new WP_Error(
                'order_not_found',
                'Pedido não encontrado',
                array('status' => 404)
            );
        }

        // Verificar status do pedido
        $order_status = get_field('order_status', $order_id);
        $allowed_statuses = array('ready_for_delivery', 'delivered', 'completed');
        
        if (!in_array($order_status, $allowed_statuses)) {
            return new WP_Error(
                'invalid_order_status',
                'Pedido não está disponível para visualização. Status atual: ' . $order_status,
                array(
                    'status' => 400,
                    'current_status' => $order_status,
                    'allowed_statuses' => $allowed_statuses
                )
            );
        }

        // Buscar páginas geradas do livro
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        if (empty($generated_pages)) {
            return new WP_Error(
                'no_pages_found',
                'Nenhuma página gerada encontrada para este pedido',
                array('status' => 404)
            );
        }

        // Montar array de páginas no formato esperado
        $pages = array();
        
        foreach ($generated_pages as $index => $page) {
            $final_page = $page['final_page_with_text'] ?? null;
            
            // Pular páginas sem imagem final
            if (empty($final_page)) {
                continue;
            }

            // Extrair URL da imagem
            $image_url = '';
            
            if (is_array($final_page)) {
                // Campo retorna array com 'url'
                $image_url = $final_page['url'] ?? '';
            } elseif (is_numeric($final_page)) {
                // Campo retorna ID do attachment
                $image_url = wp_get_attachment_image_url($final_page, 'full');
            }
            
            // Se ainda não tem URL, tentar obter de outra forma
            if (empty($image_url) && is_numeric($final_page)) {
                $attachment = get_post($final_page);
                if ($attachment) {
                    $image_url = wp_get_attachment_url($final_page);
                }
            }
            
            // Pular se não conseguiu obter URL
            if (empty($image_url)) {
                continue;
            }

            // Determinar ID da página
            // Primeira página é 'cover', depois 'page1', 'page2', etc.
            if ($index === 0) {
                $page_id = 'cover';
            } else {
                $page_id = 'page' . $index;
            }

            // Priority: true para todas as páginas
            $priority = true;

            $pages[] = array(
                'id' => $page_id,
                'src' => $image_url,
                'priority' => $priority
            );
        }

        if (empty($pages)) {
            return new WP_Error(
                'no_pages_available',
                'Nenhuma página final disponível para este pedido',
                array('status' => 404)
            );
        }

        // Retornar sucesso
        return new WP_REST_Response($pages, 200);

    } catch (Exception $e) {
        error_log("[TrinityKit] Erro ao obter páginas do pedido #$order_id: " . $e->getMessage());
        
        return new WP_Error(
            'internal_server_error',
            'Erro interno do servidor ao obter páginas do pedido',
            array('status' => 500)
        );
    }
}

