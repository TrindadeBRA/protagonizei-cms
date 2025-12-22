<?php

/**
 * Widget: Últimos Contatos
 * Exibe os últimos 5 contatos recebidos com data, nome e tags
 */
class Protagonizei_Recent_Contacts_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'protagonizei_recent_contacts',
            'Últimos Contatos',
            array(
                'description' => 'Exibe os últimos 5 contatos recebidos com data, nome e tags'
            )
        );
    }

    public function widget($args, $instance) {
        // Carregar Tailwind CSS se ainda não foi carregado
        protagonizei_load_tailwind();
        
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        // Buscar últimos 5 contatos
        $contacts = get_posts(array(
            'post_type' => 'contact_form',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ));

        if (empty($contacts)) {
            echo '<p class="text-gray-500 text-sm">Nenhum contato encontrado.</p>';
        } else {
            echo '<div class="space-y-3">';
            foreach ($contacts as $contact) {
                $name = get_field('name', $contact->ID);
                $email = get_field('email', $contact->ID);
                $date = get_the_date('d/m/Y H:i', $contact->ID);
                $tags = wp_get_post_terms($contact->ID, 'contact_tags', array('fields' => 'names'));
                
                echo '<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<div class="flex-1">';
                echo '<h4 class="font-semibold text-gray-900 text-sm">' . esc_html($name ?: 'Sem nome') . '</h4>';
                if ($email) {
                    echo '<p class="text-xs text-gray-500 mt-1">' . esc_html($email) . '</p>';
                }
                echo '</div>';
                echo '<span class="text-xs text-gray-400 ml-2 whitespace-nowrap">' . esc_html($date) . '</span>';
                echo '</div>';
                
                if (!empty($tags)) {
                    echo '<div class="flex flex-wrap gap-1 mt-2">';
                    foreach ($tags as $tag) {
                        echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">' . esc_html($tag) . '</span>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Últimos Contatos';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Título:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
}

/**
 * Widget: Pedidos Recebidos
 * Exibe os pedidos recebidos com seus status
 */
class Protagonizei_Recent_Orders_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'protagonizei_recent_orders',
            'Pedidos Recebidos',
            array(
                'description' => 'Exibe os pedidos recebidos com seus status'
            )
        );
    }

    public function widget($args, $instance) {
        // Carregar Tailwind CSS se ainda não foi carregado
        protagonizei_load_tailwind();
        
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $posts_per_page = !empty($instance['posts_per_page']) ? intval($instance['posts_per_page']) : 10;

        // Buscar pedidos
        $orders = get_posts(array(
            'post_type' => 'orders',
            'posts_per_page' => $posts_per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ));

        if (empty($orders)) {
            echo '<p class="text-gray-500 text-sm">Nenhum pedido encontrado.</p>';
        } else {
            // Labels de status
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
                'canceled' => 'Cancelado',
                'error' => 'Erro'
            );

            // Cores por status
            $status_colors = array(
                'created' => 'bg-gray-100 text-gray-800',
                'awaiting_payment' => 'bg-yellow-100 text-yellow-800',
                'paid' => 'bg-green-100 text-green-800',
                'thanked' => 'bg-blue-100 text-blue-800',
                'created_assets_text' => 'bg-purple-100 text-purple-800',
                'created_assets_illustration' => 'bg-indigo-100 text-indigo-800',
                'created_assets_merge' => 'bg-pink-100 text-pink-800',
                'ready_for_delivery' => 'bg-teal-100 text-teal-800',
                'delivered' => 'bg-emerald-100 text-emerald-800',
                'completed' => 'bg-green-200 text-green-900',
                'canceled' => 'bg-red-100 text-red-800',
                'error' => 'bg-red-100 text-red-800'
            );

            echo '<div class="space-y-3">';
            foreach ($orders as $order) {
                $status = get_field('order_status', $order->ID);
                $child_name = get_field('child_name', $order->ID);
                $buyer_email = get_field('buyer_email', $order->ID);
                $date = get_the_date('d/m/Y H:i', $order->ID);
                $payment_date = get_field('payment_date', $order->ID);
                
                $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
                $status_color = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';
                
                echo '<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<div class="flex-1">';
                echo '<h4 class="font-semibold text-gray-900 text-sm">Pedido #' . esc_html($order->ID) . '</h4>';
                if ($child_name) {
                    echo '<p class="text-xs text-gray-600 mt-1">Criança: ' . esc_html($child_name) . '</p>';
                }
                if ($buyer_email) {
                    echo '<p class="text-xs text-gray-500 mt-1">' . esc_html($buyer_email) . '</p>';
                }
                echo '</div>';
                echo '<span class="text-xs text-gray-400 ml-2 whitespace-nowrap">' . esc_html($date) . '</span>';
                echo '</div>';
                
                echo '<div class="flex items-center justify-between mt-2">';
                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . esc_attr($status_color) . '">' . esc_html($status_label) . '</span>';
                if ($payment_date) {
                    $payment_formatted = date('d/m/Y H:i', strtotime($payment_date));
                    echo '<span class="text-xs text-gray-500">Pago: ' . esc_html($payment_formatted) . '</span>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'Pedidos Recebidos';
        $posts_per_page = !empty($instance['posts_per_page']) ? $instance['posts_per_page'] : 5;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Título:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('posts_per_page')); ?>">Quantidade de pedidos:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('posts_per_page')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('posts_per_page')); ?>" 
                   type="number" min="1" max="50" value="<?php echo esc_attr($posts_per_page); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['posts_per_page'] = (!empty($new_instance['posts_per_page'])) ? intval($new_instance['posts_per_page']) : 5;
        return $instance;
    }
}

/**
 * Registrar os widgets
 */
function protagonizei_register_widgets() {
    register_widget('Protagonizei_Recent_Contacts_Widget');
    register_widget('Protagonizei_Recent_Orders_Widget');
}
add_action('widgets_init', 'protagonizei_register_widgets');

/**
 * Carregar Tailwind CSS apenas uma vez
 */
function protagonizei_load_tailwind() {
    static $tailwind_loaded = false;
    
    if (!$tailwind_loaded) {
        echo '<script src="https://cdn.tailwindcss.com"></script>';
        $tailwind_loaded = true;
    }
}

/**
 * Adicionar suporte a Tailwind CSS e Chart.js nos widgets do admin e dashboard
 */
function protagonizei_widget_styles() {
    // Verificar se estamos na página do dashboard ou widgets
    $screen = get_current_screen();
    if ($screen && ($screen->id === 'dashboard' || $screen->id === 'widgets')) {
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            .widget-content {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            /* Garantir que os widgets do dashboard usem Tailwind */
            #protagonizei_recent_contacts_dashboard .inside,
            #protagonizei_recent_orders_dashboard .inside,
            #protagonizei_stats_dashboard .inside,
            #protagonizei_api_balances_dashboard .inside {
                padding: 12px;
            }
            /* Ajustar altura dos gráficos */
            #protagonizei_stats_dashboard canvas {
                max-height: 250px;
            }
            /* Responsividade para telas pequenas */
            @media (max-width: 640px) {
                #protagonizei_stats_dashboard .inside {
                    padding: 8px;
                }
                #protagonizei_stats_dashboard canvas {
                    max-height: 200px;
                }
            }
            /* Garantir que os grids sejam responsivos */
            #protagonizei_stats_dashboard .grid {
                display: grid;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'protagonizei_widget_styles');

/**
 * ============================================
 * WIDGETS DO DASHBOARD (HOME)
 * ============================================
 */

/**
 * Widget do Dashboard: Últimos Contatos
 */
function protagonizei_dashboard_recent_contacts_widget() {
    // Buscar últimos 5 contatos
    $contacts = get_posts(array(
        'post_type' => 'contact_form',
        'posts_per_page' => 5,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'publish'
    ));

    if (empty($contacts)) {
        echo '<p class="text-gray-500 text-sm">Nenhum contato encontrado.</p>';
    } else {
        echo '<div class="space-y-2 sm:space-y-3">';
        foreach ($contacts as $contact) {
            $name = get_field('name', $contact->ID);
            $email = get_field('email', $contact->ID);
            $phone = get_field('phone', $contact->ID);
            $date = get_the_date('d/m/Y H:i', $contact->ID);
            $tags = wp_get_post_terms($contact->ID, 'contact_tags', array('fields' => 'names'));
            $view_link = get_permalink($contact->ID);
            
            echo '<div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4 hover:shadow-md transition-shadow">';
            echo '<div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-2">';
            echo '<div class="flex-1 min-w-0">';
            if ($view_link) {
                echo '<h4 class="font-semibold text-gray-900 text-sm truncate"><a href="' . esc_url($view_link) . '" class="hover:text-blue-600">' . esc_html($name ?: 'Sem nome') . '</a></h4>';
            } else {
                echo '<h4 class="font-semibold text-gray-900 text-sm truncate">' . esc_html($name ?: 'Sem nome') . '</h4>';
            }
            if ($email) {
                echo '<p class="text-xs text-gray-500 mt-1 truncate">' . esc_html($email) . '</p>';
            }
            if ($phone) {
                echo '<p class="text-xs text-gray-500 truncate">' . esc_html($phone) . '</p>';
            }
            echo '</div>';
            echo '<span class="text-xs text-gray-400 sm:ml-2 sm:whitespace-nowrap flex-shrink-0">' . esc_html($date) . '</span>';
            echo '</div>';
            
            if (!empty($tags)) {
                echo '<div class="flex flex-wrap gap-1 mt-2">';
                foreach ($tags as $tag) {
                    echo '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">' . esc_html($tag) . '</span>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Link para ver todos os contatos
        $all_contacts_link = admin_url('edit.php?post_type=contact_form');
        echo '<div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200">';
        echo '<a href="' . esc_url($all_contacts_link) . '" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todos os contatos →</a>';
        echo '</div>';
    }
}

/**
 * Widget do Dashboard: Pedidos Recebidos
 */
function protagonizei_dashboard_recent_orders_widget() {
    // Buscar pedidos
    $orders = get_posts(array(
        'post_type' => 'orders',
        'posts_per_page' => 5,
        'orderby' => 'date',
        'order' => 'DESC',
        'post_status' => 'publish'
    ));

    if (empty($orders)) {
        echo '<p class="text-gray-500 text-sm">Nenhum pedido encontrado.</p>';
    } else {
        // Labels de status
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

        // Cores por status
        $status_colors = array(
            'created' => 'bg-gray-100 text-gray-800',
            'awaiting_payment' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'thanked' => 'bg-blue-100 text-blue-800',
            'created_assets_text' => 'bg-purple-100 text-purple-800',
            'created_assets_illustration' => 'bg-indigo-100 text-indigo-800',
            'created_assets_merge' => 'bg-pink-100 text-pink-800',
            'ready_for_delivery' => 'bg-teal-100 text-teal-800',
            'delivered' => 'bg-emerald-100 text-emerald-800',
            'completed' => 'bg-green-200 text-green-900',
            'error' => 'bg-red-100 text-red-800'
        );

        echo '<div class="space-y-2 sm:space-y-3">';
        foreach ($orders as $order) {
            $status = get_field('order_status', $order->ID);
            $child_name = get_field('child_name', $order->ID);
            $buyer_email = get_field('buyer_email', $order->ID);
            $date = get_the_date('d/m/Y H:i', $order->ID);
            $payment_date = get_field('payment_date', $order->ID);
            $view_link = get_permalink($order->ID);
            
            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
            $status_color = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';
            
            echo '<div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4 hover:shadow-md transition-shadow">';
            echo '<div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-2">';
            echo '<div class="flex-1 min-w-0">';
            if ($view_link) {
                echo '<h4 class="font-semibold text-gray-900 text-sm truncate"><a href="' . esc_url($view_link) . '" class="hover:text-blue-600">Pedido #' . esc_html($order->ID) . '</a></h4>';
            } else {
                echo '<h4 class="font-semibold text-gray-900 text-sm truncate">Pedido #' . esc_html($order->ID) . '</h4>';
            }
            if ($child_name) {
                echo '<p class="text-xs text-gray-600 mt-1 truncate">Criança: ' . esc_html($child_name) . '</p>';
            }
            if ($buyer_email) {
                echo '<p class="text-xs text-gray-500 mt-1 truncate">' . esc_html($buyer_email) . '</p>';
            }
            echo '</div>';
            echo '<span class="text-xs text-gray-400 sm:ml-2 sm:whitespace-nowrap flex-shrink-0">' . esc_html($date) . '</span>';
            echo '</div>';
            
            echo '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-2">';
            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . esc_attr($status_color) . '">' . esc_html($status_label) . '</span>';
            if ($payment_date) {
                $payment_formatted = date('d/m/Y H:i', strtotime($payment_date));
                echo '<span class="text-xs text-gray-500 sm:text-right">Pago: ' . esc_html($payment_formatted) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        
        // Link para ver todos os pedidos
        $all_orders_link = admin_url('edit.php?post_type=orders');
        echo '<div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200">';
        echo '<a href="' . esc_url($all_orders_link) . '" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todos os pedidos →</a>';
        echo '</div>';
    }
}

/**
 * Widget do Dashboard: Estatísticas e Gráficos
 */
function protagonizei_dashboard_stats_widget() {
    // Buscar dados dos pedidos
    $orders_query = new WP_Query(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));

    $status_counts = array();
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
        'canceled' => 'Cancelado',
        'error' => 'Erro'
    );

    // Inicializar contadores
    foreach ($status_labels as $key => $label) {
        $status_counts[$key] = 0;
    }

    $total_revenue = 0;
    $paid_orders = 0;
    $pending_orders = 0;

    // Contar pedidos por status e calcular receita
    if ($orders_query->have_posts()) {
        while ($orders_query->have_posts()) {
            $orders_query->the_post();
            $order_status = get_field('order_status');
            $payment_amount = get_field('payment_amount') ?: 0;
            
            if (isset($status_counts[$order_status])) {
                $status_counts[$order_status]++;
            }
            
            if ($order_status === 'paid' || $order_status === 'thanked' || $order_status === 'delivered' || $order_status === 'completed') {
                $total_revenue += $payment_amount;
                $paid_orders++;
            }
            
            if ($order_status === 'awaiting_payment' || $order_status === 'created') {
                $pending_orders++;
            }
        }
        wp_reset_postdata();
    }

    $total_orders = array_sum($status_counts);

    // Buscar dados dos cupons
    $coupons_query = new WP_Query(array(
        'post_type' => 'coupons',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    $total_coupons = $coupons_query->found_posts;
    wp_reset_postdata();

    // Preparar dados para o gráfico
    $chart_labels = array();
    $chart_data = array();
    $chart_colors = array('#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#6B7280', '#14B8A6', '#F97316', '#84CC16', '#6366F1');
    $color_index = 0;
    
    foreach ($status_counts as $status => $count) {
        if ($count > 0) {
            $chart_labels[] = $status_labels[$status];
            $chart_data[] = $count;
        }
    }

    // Gerar ID único para o canvas
    $chart_id = 'statsChart_' . uniqid();
    ?>
    
    <!-- Cards de Métricas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-blue-50 rounded-lg p-3 sm:p-4 border border-blue-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-blue-600 uppercase truncate">Total Pedidos</p>
                    <p class="text-xl sm:text-2xl font-bold text-blue-900 mt-1 break-words"><?php echo number_format($total_orders); ?></p>
                </div>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-green-50 rounded-lg p-3 sm:p-4 border border-green-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-green-600 uppercase truncate">Receita Total</p>
                    <p class="text-xl sm:text-2xl font-bold text-green-900 mt-1 break-words">R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?></p>
                </div>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-purple-50 rounded-lg p-3 sm:p-4 border border-purple-200 sm:col-span-2">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-purple-600 uppercase truncate">Cupons</p>
                    <p class="text-xl sm:text-2xl font-bold text-purple-900 mt-1 break-words"><?php echo number_format($total_coupons); ?></p>
                </div>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 002 2h3a2 2 0 002-2V7a2 2 0 00-2-2H5zM5 13a2 2 0 00-2 2v3a2 2 0 002 2h3a2 2 0 002-2v-3a2 2 0 00-2-2H5zM19 5a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2V7a2 2 0 012-2h3zM19 13a2 2 0 012 2v3a2 2 0 01-2 2h-3a2 2 0 01-2-2v-3a2 2 0 012-2h3z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas Adicionais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-yellow-50 rounded-lg p-3 border border-yellow-200">
            <p class="text-xs font-medium text-yellow-600 uppercase">Pedidos Pagos</p>
            <p class="text-lg sm:text-xl font-bold text-yellow-900 mt-1"><?php echo number_format($paid_orders); ?></p>
        </div>
        <div class="bg-orange-50 rounded-lg p-3 border border-orange-200">
            <p class="text-xs font-medium text-orange-600 uppercase">Aguardando Pagamento</p>
            <p class="text-lg sm:text-xl font-bold text-orange-900 mt-1"><?php echo number_format($pending_orders); ?></p>
        </div>
    </div>

    <?php if (!empty($chart_data)): ?>
    <!-- Gráfico de Pizza -->
    <div class="mt-4 sm:mt-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3 sm:mb-4">Status dos Pedidos</h3>
        <div class="relative w-full" style="height: 200px; min-height: 200px;">
            <canvas id="<?php echo esc_attr($chart_id); ?>"></canvas>
        </div>
        
        <!-- Legenda -->
        <div class="mt-3 sm:mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <?php 
            $color_index = 0;
            foreach ($status_counts as $status => $count):
                if ($count > 0):
            ?>
                <div class="flex items-center truncate">
                    <div class="w-3 h-3 rounded-full mr-2 flex-shrink-0" style="background-color: <?php echo $chart_colors[$color_index % count($chart_colors)]; ?>"></div>
                    <span class="text-gray-700 truncate"><?php echo esc_html($status_labels[$status]); ?> (<?php echo $count; ?>)</span>
                </div>
            <?php 
                $color_index++;
                endif;
            endforeach; 
            ?>
        </div>
    </div>

    <script>
    (function() {
        if (typeof Chart !== 'undefined') {
            const ctx = document.getElementById('<?php echo esc_js($chart_id); ?>');
            if (ctx) {
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($chart_data); ?>,
                            backgroundColor: <?php echo json_encode(array_slice($chart_colors, 0, count($chart_data))); ?>,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 1,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    })();
    </script>
    <?php endif; ?>

    <!-- Links Rápidos -->
    <div class="mt-4 sm:mt-6 pt-4 sm:pt-6 border-t border-gray-200">
        <div class="flex flex-col sm:flex-row gap-2">
            <a href="<?php echo admin_url('edit.php?post_type=orders'); ?>" class="flex-1 text-center px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-sm font-medium transition-colors">
                Ver Pedidos
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=coupons'); ?>" class="flex-1 text-center px-3 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-sm font-medium transition-colors">
                Ver Cupons
            </a>
        </div>
    </div>
    <?php
}

/**
 * ============================================
 * FUNÇÕES PARA BUSCAR SALDOS DAS APIs
 * ============================================
 */

/**
 * Buscar saldo do Deepseek
 * @return array|false Array com informações de saldo ou false em caso de erro
 */
function protagonizei_get_deepseek_balance() {
    // Verificar cache (5 minutos)
    $cache_key = 'protagonizei_deepseek_balance';
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $cached;
    }

    $api_key = get_option('trinitykitcms_deepseek_api_key');
    $base_url = get_option('trinitykitcms_deepseek_base_url');

    if (empty($api_key)) {
        return array(
            'success' => false,
            'error' => 'API Key não configurada',
            'balance' => null
        );
    }

    // Endpoint para buscar saldo
    $balance_url = 'https://api.deepseek.com/user/balance';
    
    $headers = array(
        'Authorization: Bearer ' . trim($api_key),
        'Content-Type: application/json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $balance_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        $result = array(
            'success' => false,
            'error' => 'Erro cURL: ' . $error,
            'balance' => null
        );
        set_transient($cache_key, $result, 60); // Cache de erro por 1 minuto
        return $result;
    }
    
    curl_close($ch);

    if ($http_code !== 200) {
        $result = array(
            'success' => false,
            'error' => 'Erro HTTP ' . $http_code,
            'balance' => null
        );
        set_transient($cache_key, $result, 60); // Cache de erro por 1 minuto
        return $result;
    }

    $response_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $result = array(
            'success' => false,
            'error' => 'Resposta JSON inválida',
            'balance' => null
        );
        set_transient($cache_key, $result, 60);
        return $result;
    }

    // Processar resposta do Deepseek
    $balance_info = array(
        'success' => true,
        'error' => null,
        'balance' => null,
        'currency' => null,
        'granted_balance' => null,
        'topped_up_balance' => null
    );

    if (isset($response_data['balance_infos']) && is_array($response_data['balance_infos']) && !empty($response_data['balance_infos'])) {
        $balance_data = $response_data['balance_infos'][0];
        $balance_info['balance'] = isset($balance_data['total_balance']) ? floatval($balance_data['total_balance']) : 0;
        $balance_info['currency'] = isset($balance_data['currency']) ? $balance_data['currency'] : 'CNY';
        $balance_info['granted_balance'] = isset($balance_data['granted_balance']) ? floatval($balance_data['granted_balance']) : 0;
        $balance_info['topped_up_balance'] = isset($balance_data['topped_up_balance']) ? floatval($balance_data['topped_up_balance']) : 0;
    } else {
        $balance_info['success'] = false;
        $balance_info['error'] = 'Formato de resposta inválido';
    }

    // Cache por 5 minutos
    set_transient($cache_key, $balance_info, 300);
    
    return $balance_info;
}


/**
 * Widget do Dashboard: Saldos das APIs
 */
function protagonizei_dashboard_api_balances_widget() {
    // Buscar saldo do Deepseek
    $deepseek_balance = protagonizei_get_deepseek_balance();
    
    ?>
    <div class="space-y-3 sm:space-y-4">
        <!-- Deepseek -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Deepseek</h3>
                        <a href="https://platform.deepseek.com/usage" target="_blank" class="text-xs text-blue-600 hover:text-blue-800">Ver uso →</a>
                    </div>
                </div>
                <?php if ($deepseek_balance['success']): ?>
                    <div class="text-right">
                        <p class="text-lg sm:text-xl font-bold text-blue-900">
                            <?php echo number_format($deepseek_balance['balance'], 2, ',', '.'); ?>
                            <span class="text-xs font-normal text-gray-600"><?php echo esc_html($deepseek_balance['currency'] ?? 'CNY'); ?></span>
                        </p>
                        <?php if ($deepseek_balance['granted_balance'] > 0 || $deepseek_balance['topped_up_balance'] > 0): ?>
                            <p class="text-xs text-gray-600 mt-1">
                                <?php if ($deepseek_balance['granted_balance'] > 0): ?>
                                    <span>Concedido: <?php echo number_format($deepseek_balance['granted_balance'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                                <?php if ($deepseek_balance['topped_up_balance'] > 0): ?>
                                    <span class="ml-2">Recarregado: <?php echo number_format($deepseek_balance['topped_up_balance'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-right">
                        <p class="text-sm font-medium text-red-600">Erro</p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo esc_html($deepseek_balance['error'] ?? 'Erro desconhecido'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- FAL.AI -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-4 border border-purple-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">FAL.AI</h3>
                        <a href="https://fal.ai/dashboard/usage-billing/credits" target="_blank" class="text-xs text-purple-600 hover:text-purple-800 font-medium">Ver uso →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão para atualizar -->
    <div class="mt-4 pt-4 border-t border-gray-200">
        <form method="post" action="">
            <?php wp_nonce_field('protagonizei_refresh_balances', 'protagonizei_balances_nonce'); ?>
            <button type="submit" name="refresh_balances" class="w-full px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                🔄 Atualizar Saldos
            </button>
        </form>
    </div>

    <?php
    // Processar atualização manual
    if (isset($_POST['refresh_balances']) && check_admin_referer('protagonizei_refresh_balances', 'protagonizei_balances_nonce')) {
        // Limpar cache do Deepseek
        delete_transient('protagonizei_deepseek_balance');
        
        // Redirecionar para evitar reenvio do formulário
        wp_redirect(admin_url('index.php'));
        exit;
    }
}

/**
 * Adicionar widgets ao dashboard
 */
function protagonizei_add_dashboard_widgets() {
    wp_add_dashboard_widget(
        'protagonizei_recent_contacts_dashboard',
        'Últimos Contatos',
        'protagonizei_dashboard_recent_contacts_widget'
    );
    
    wp_add_dashboard_widget(
        'protagonizei_recent_orders_dashboard',
        'Pedidos Recebidos',
        'protagonizei_dashboard_recent_orders_widget'
    );
    
    wp_add_dashboard_widget(
        'protagonizei_stats_dashboard',
        'Estatísticas e Gráficos',
        'protagonizei_dashboard_stats_widget'
    );
    
    wp_add_dashboard_widget(
        'protagonizei_api_balances_dashboard',
        'Saldos das APIs',
        'protagonizei_dashboard_api_balances_widget'
    );
}
add_action('wp_dashboard_setup', 'protagonizei_add_dashboard_widgets');

