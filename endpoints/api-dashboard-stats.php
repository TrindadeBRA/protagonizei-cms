<?php
/**
 * API Endpoint para estatísticas do dashboard
 * Fornece dados para gráficos e métricas de vendas
 */

// Registrar endpoint REST
add_action('rest_api_init', function () {
    register_rest_route('protagonizei/v1', '/dashboard/stats', array(
        'methods' => 'GET',
        'callback' => 'get_dashboard_stats',
        'permission_callback' => 'dashboard_permission_check',
        'args' => array(
            'start_date' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'end_date' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'status' => array(
                'required' => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
});

function dashboard_permission_check() {
    // TEMPORÁRIO: Para debug, permitir acesso se estiver em desenvolvimento
    // Remova esta linha em produção
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return true;
    }
    
    // Verifica se o usuário está logado e tem permissão para editar posts
    if (!is_user_logged_in()) {
        return new WP_Error('rest_not_logged_in', 'Você precisa estar logado para acessar este endpoint.', array('status' => 401));
    }
    
    // Verifica se tem permissão para editar posts ou é admin
    if (!current_user_can('edit_posts') && !current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', 'Você não tem permissão para acessar este endpoint.', array('status' => 403));
    }
    
    return true;
}

function get_dashboard_stats(WP_REST_Request $request) {
    try {
        // Parâmetros de filtro
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        $status_filter = $request->get_param('status');
        
        // Query básica para pedidos
        $args = array(
            'post_type' => 'orders',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(),
        );
        
        // Filtro por data se fornecido
        if ($start_date || $end_date) {
            $date_query = array();
            if ($start_date) {
                $date_query['after'] = $start_date;
            }
            if ($end_date) {
                $date_query['before'] = $end_date . ' 23:59:59';
            }
            $args['date_query'] = array($date_query);
        }
        
        // Filtro por status se fornecido
        if ($status_filter) {
            $args['meta_query'][] = array(
                'key' => 'order_status',
                'value' => $status_filter,
                'compare' => '='
            );
        }
        
        $orders = get_posts($args);
        
        // Inicializar contadores
        $stats = array(
            'total_orders' => 0,
            'total_revenue' => 0,
            'total_coupons_used' => 0,
            'orders_by_status' => array(),
            'revenue_by_month' => array(),
            'orders_by_month' => array(),
            'coupon_usage' => array(),
            'top_coupons' => array(),
            'recent_orders' => array(),
            'conversion_funnel' => array(),
            'average_order_value' => 0,
            'orders_by_template' => array(),
        );
        
        // Status labels
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
            'completed' => 'Concluído',
            'error' => 'Erro'
        );
        
        // Inicializar arrays de status
        foreach ($status_labels as $status_key => $status_label) {
            $stats['orders_by_status'][$status_key] = array(
                'label' => $status_label,
                'count' => 0,
                'value' => 0
            );
        }
        
        $coupon_counts = array();
        $total_paid_amount = 0;
        $paid_orders_count = 0;
        
        // Processar cada pedido
        foreach ($orders as $order) {
            $order_id = $order->ID;
            $order_status = get_field('order_status', $order_id);
            $payment_amount = floatval(get_field('payment_amount', $order_id));
            $applied_coupon = get_field('applied_coupon', $order_id);
            $payment_date = get_field('payment_date', $order_id);
            $book_template = get_field('book_template', $order_id);
            $buyer_name = get_field('buyer_name', $order_id);
            $child_name = get_field('child_name', $order_id);
            
            $stats['total_orders']++;
            
            // Contabilizar por status
            if (isset($stats['orders_by_status'][$order_status])) {
                $stats['orders_by_status'][$order_status]['count']++;
                $stats['orders_by_status'][$order_status]['value'] += $payment_amount;
            }
            
            // Contabilizar receita apenas para pedidos pagos
            if (in_array($order_status, ['paid', 'thanked', 'created_assets_text', 'created_assets_illustration', 'created_assets_merge', 'ready_for_delivery', 'delivered', 'completed'])) {
                $stats['total_revenue'] += $payment_amount;
                $total_paid_amount += $payment_amount;
                $paid_orders_count++;
            }
            
            // Contabilizar cupons
            if (!empty($applied_coupon)) {
                $stats['total_coupons_used']++;
                if (!isset($coupon_counts[$applied_coupon])) {
                    $coupon_counts[$applied_coupon] = array(
                        'code' => $applied_coupon,
                        'count' => 0,
                        'revenue' => 0
                    );
                }
                $coupon_counts[$applied_coupon]['count']++;
                if (in_array($order_status, ['paid', 'thanked', 'created_assets_text', 'created_assets_illustration', 'created_assets_merge', 'ready_for_delivery', 'delivered', 'completed'])) {
                    $coupon_counts[$applied_coupon]['revenue'] += $payment_amount;
                }
            }
            
            // Agrupar por mês (usando data de criação do pedido)
            $order_date = $order->post_date;
            $month_key = date('Y-m', strtotime($order_date));
            $month_label = date('M/Y', strtotime($order_date));
            
            if (!isset($stats['orders_by_month'][$month_key])) {
                $stats['orders_by_month'][$month_key] = array(
                    'label' => $month_label,
                    'count' => 0,
                    'revenue' => 0
                );
            }
            $stats['orders_by_month'][$month_key]['count']++;
            
            // Receita por mês (apenas pedidos pagos)
            if (in_array($order_status, ['paid', 'thanked', 'created_assets_text', 'created_assets_illustration', 'created_assets_merge', 'ready_for_delivery', 'delivered', 'completed'])) {
                $stats['orders_by_month'][$month_key]['revenue'] += $payment_amount;
            }
            
            // Agrupar por template
            if ($book_template) {
                $template_title = $book_template->post_title;
                if (!isset($stats['orders_by_template'][$template_title])) {
                    $stats['orders_by_template'][$template_title] = array(
                        'title' => $template_title,
                        'count' => 0,
                        'revenue' => 0
                    );
                }
                $stats['orders_by_template'][$template_title]['count']++;
                if (in_array($order_status, ['paid', 'thanked', 'created_assets_text', 'created_assets_illustration', 'created_assets_merge', 'ready_for_delivery', 'delivered', 'completed'])) {
                    $stats['orders_by_template'][$template_title]['revenue'] += $payment_amount;
                }
            }
            
            // Adicionar aos pedidos recentes (últimos 10)
            if (count($stats['recent_orders']) < 10) {
                $stats['recent_orders'][] = array(
                    'id' => $order_id,
                    'title' => $order->post_title,
                    'buyer_name' => $buyer_name,
                    'child_name' => $child_name,
                    'status' => $order_status,
                    'status_label' => $status_labels[$order_status] ?? $order_status,
                    'amount' => $payment_amount,
                    'date' => $order->post_date,
                    'coupon' => $applied_coupon,
                    'url' => get_permalink($order_id)
                );
            }
        }
        
        // Calcular valor médio do pedido
        if ($paid_orders_count > 0) {
            $stats['average_order_value'] = $total_paid_amount / $paid_orders_count;
        }
        
        // Ordenar cupons por uso
        uasort($coupon_counts, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $stats['top_coupons'] = array_slice($coupon_counts, 0, 10, true);
        
        // Converter arrays associativos em arrays indexados para facilitar uso no frontend
        $stats['orders_by_status'] = array_values($stats['orders_by_status']);
        $stats['orders_by_month'] = array_values($stats['orders_by_month']);
        $stats['orders_by_template'] = array_values($stats['orders_by_template']);
        $stats['top_coupons'] = array_values($stats['top_coupons']);
        
        // Ordenar por data
        usort($stats['orders_by_month'], function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        // Funil de conversão
        $stats['conversion_funnel'] = array(
            array('stage' => 'Criados', 'count' => $stats['orders_by_status'][0]['count']), // created
            array('stage' => 'Aguardando Pagamento', 'count' => $stats['orders_by_status'][1]['count']), // awaiting_payment
            array('stage' => 'Pagos', 'count' => $stats['orders_by_status'][2]['count']), // paid
            array('stage' => 'Concluídos', 'count' => $stats['orders_by_status'][9]['count']), // completed
        );
        
        return new WP_REST_Response($stats, 200);
        
    } catch (Exception $e) {
        return new WP_Error('dashboard_error', 'Erro ao buscar estatísticas: ' . $e->getMessage(), array('status' => 500));
    }
}
