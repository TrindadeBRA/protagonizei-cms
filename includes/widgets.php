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
 * Função auxiliar: Contar pedidos por email
 * @param string $email Email do comprador
 * @return int Número de pedidos com esse email
 */
function protagonizei_count_orders_by_email($email) {
    if (empty($email)) {
        return 0;
    }
    
    // Normalizar o email (trim e lowercase para comparação precisa)
    $email_normalized = strtolower(trim($email));
    
    // Usar query SQL direta para melhor performance e precisão
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID) 
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'orders'
         AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'trash')
         AND pm.meta_key = 'buyer_email'
         AND LOWER(TRIM(pm.meta_value)) = %s",
        $email_normalized
    ));
    
    return (int) $count;
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

            // Cores por status (cores semânticas alinhadas com o significado)
            $status_colors = array(
                'created' => 'bg-gray-100 text-gray-800',
                'awaiting_payment' => 'bg-amber-100 text-amber-800',
                'paid' => 'bg-green-100 text-green-800',
                'thanked' => 'bg-blue-100 text-blue-800',
                'created_assets_text' => 'bg-purple-100 text-purple-800',
                'created_assets_illustration' => 'bg-indigo-100 text-indigo-800',
                'created_assets_merge' => 'bg-pink-100 text-pink-800',
                'ready_for_delivery' => 'bg-teal-100 text-teal-800',
                'delivered' => 'bg-emerald-200 text-emerald-900',
                'completed' => 'bg-green-700 text-white',
                'canceled' => 'bg-red-500 text-white',
                'error' => 'bg-red-600 text-white'
            );

            echo '<div class="space-y-3">';
            foreach ($orders as $order) {
                $status = get_field('order_status', $order->ID);
                // Normalizar o status (trim e lowercase para garantir correspondência)
                $status = $status ? strtolower(trim($status)) : '';
                $child_name = get_field('child_name', $order->ID);
                $buyer_email = get_field('buyer_email', $order->ID);
                $date = get_the_date('d/m/Y H:i', $order->ID);
                $payment_date = get_field('payment_date', $order->ID);
                
                $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
                $status_color = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';
                
                // Contar pedidos por email
                $orders_count = 0;
                $email_filter_link = '';
                if ($buyer_email) {
                    $orders_count = protagonizei_count_orders_by_email($buyer_email);
                    $email_filter_link = admin_url('edit.php?s=' . urlencode($buyer_email) . '&post_type=orders&post_status=all');
                }
                
                echo '<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<div class="flex-1">';
            echo '<h4 class="font-semibold text-gray-900 text-sm">Pedido #' . esc_html($order->ID) . '</h4>';
            if ($child_name) {
                echo '<p class="text-xs text-gray-600 mt-1">Criança: ' . esc_html($child_name) . '</p>';
            }
                if ($buyer_email) {
                    echo '<div class="flex items-center gap-2 mt-1">';
                    echo '<p class="text-xs text-gray-500">' . esc_html($buyer_email) . '</p>';
                    if ($orders_count > 0) {
                        echo '<span class="text-xs text-gray-400">•</span>';
                        if ($email_filter_link) {
                            echo '<a href="' . esc_url($email_filter_link) . '" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium">' . esc_html($orders_count) . ' pedido(s)</a>';
                        } else {
                            echo '<span class="text-xs text-gray-600 font-medium">' . esc_html($orders_count) . ' pedido(s)</span>';
                        }
                    }
                    echo '</div>';
                }
                echo '</div>';
                echo '<span class="text-xs text-gray-400 ml-2 whitespace-nowrap">' . esc_html($date) . '</span>';
                echo '</div>';
                
                echo '<div class="flex items-center justify-between mt-2">';
                echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . esc_attr($status_color) . '">' . esc_html($status_label) . '</span>';
                if ($payment_date) {
                    $payment_formatted = mysql2date('d/m/Y H:i', $payment_date);
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
            #protagonizei_coupons_dashboard .inside,
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
    // Calcular contatos do dia atual (00:00:00 às 23:59:59)
    $today_start = current_time('Y-m-d') . ' 00:00:00';
    $today_end = current_time('Y-m-d') . ' 23:59:59';
    
    $contacts_today_query = new WP_Query(array(
        'post_type' => 'contact_form',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'date_query' => array(
            array(
                'after' => $today_start,
                'before' => $today_end,
                'inclusive' => true
            )
        )
    ));
    
    $contacts_today_count = $contacts_today_query->found_posts;
    wp_reset_postdata();
    
    // Card de contador
    echo '<div class="bg-blue-50 rounded-lg p-3 sm:p-4 border border-blue-200 mb-4">';
    echo '<div class="flex items-center justify-between">';
    echo '<div class="flex-1 min-w-0">';
    echo '<p class="text-xs font-medium text-blue-600 uppercase truncate">Contatos (Hoje)</p>';
    echo '<p class="text-xl sm:text-2xl font-bold text-blue-900 mt-1 break-words">' . number_format($contacts_today_count) . '</p>';
    echo '</div>';
    echo '<div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">';
    echo '<svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>';
    echo '</svg>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Buscar últimos 4 contatos
    $contacts = get_posts(array(
        'post_type' => 'contact_form',
        'posts_per_page' => 4,
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
    // Calcular pedidos do dia atual (00:00:00 às 23:59:59)
    $today_start = current_time('Y-m-d') . ' 00:00:00';
    $today_end = current_time('Y-m-d') . ' 23:59:59';
    
    $orders_today_query = new WP_Query(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'date_query' => array(
            array(
                'after' => $today_start,
                'before' => $today_end,
                'inclusive' => true
            )
        )
    ));
    
    $orders_today_count = $orders_today_query->found_posts;
    wp_reset_postdata();
    
    // Card de contador
    echo '<div class="bg-purple-50 rounded-lg p-3 sm:p-4 border border-purple-200 mb-4">';
    echo '<div class="flex items-center justify-between">';
    echo '<div class="flex-1 min-w-0">';
    echo '<p class="text-xs font-medium text-purple-600 uppercase truncate">Pedidos (Hoje)</p>';
    echo '<p class="text-xl sm:text-2xl font-bold text-purple-900 mt-1 break-words">' . number_format($orders_today_count) . '</p>';
    echo '</div>';
    echo '<div class="w-8 h-8 sm:w-10 sm:h-10 bg-purple-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">';
    echo '<svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>';
    echo '</svg>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Buscar últimos 4 pedidos
    $orders = get_posts(array(
        'post_type' => 'orders',
        'posts_per_page' => 4,
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

        // Cores por status (cores semânticas alinhadas com o significado)
        $status_colors = array(
            'created' => 'bg-gray-100 text-gray-800',
            'awaiting_payment' => 'bg-amber-100 text-amber-800',
            'paid' => 'bg-green-100 text-green-800',
            'thanked' => 'bg-blue-100 text-blue-800',
            'created_assets_text' => 'bg-purple-100 text-purple-800',
            'created_assets_illustration' => 'bg-indigo-100 text-indigo-800',
            'created_assets_merge' => 'bg-pink-100 text-pink-800',
            'ready_for_delivery' => 'bg-teal-100 text-teal-800',
            'delivered' => 'bg-emerald-200 text-emerald-900',
            'completed' => 'bg-green-700 text-white',
            'canceled' => 'bg-red-500 text-white',
            'error' => 'bg-red-600 text-white'
        );

        echo '<div class="space-y-2 sm:space-y-3">';
        foreach ($orders as $order) {
            $status = get_field('order_status', $order->ID);
            // Normalizar o status (trim e lowercase para garantir correspondência)
            $status = $status ? strtolower(trim($status)) : '';
            $child_name = get_field('child_name', $order->ID);
            $buyer_email = get_field('buyer_email', $order->ID);
            $date = get_the_date('d/m/Y H:i', $order->ID);
            $payment_date = get_field('payment_date', $order->ID);
            $view_link = get_permalink($order->ID);
            
            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
            $status_color = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';
            
            // Contar pedidos por email
            $orders_count = 0;
            $email_filter_link = '';
            if ($buyer_email) {
                $orders_count = protagonizei_count_orders_by_email($buyer_email);
                $email_filter_link = admin_url('edit.php?s=' . urlencode($buyer_email) . '&post_type=orders&post_status=all');
            }
            
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
                echo '<div class="flex items-center gap-2 mt-1 flex-wrap">';
                echo '<p class="text-xs text-gray-500 truncate">' . esc_html($buyer_email) . '</p>';
                if ($orders_count > 0) {
                    echo '<span class="text-xs text-gray-400">•</span>';
                    if ($email_filter_link) {
                        echo '<a href="' . esc_url($email_filter_link) . '" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium whitespace-nowrap">' . esc_html($orders_count) . ' pedido(s)</a>';
                    } else {
                        echo '<span class="text-xs text-gray-600 font-medium whitespace-nowrap">' . esc_html($orders_count) . ' pedido(s)</span>';
                    }
                }
                echo '</div>';
            }
            echo '</div>';
            echo '<span class="text-xs text-gray-400 sm:ml-2 sm:whitespace-nowrap flex-shrink-0">' . esc_html($date) . '</span>';
            echo '</div>';
            
            echo '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-2">';
            echo '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . esc_attr($status_color) . '">' . esc_html($status_label) . '</span>';
            if ($payment_date) {
                $payment_formatted = mysql2date('d/m/Y H:i', $payment_date);
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
            
            // Contar todos os status, incluindo cancelados
            if (isset($status_counts[$order_status])) {
                $status_counts[$order_status]++;
            } else {
                // Se o status não estiver no array, adicionar dinamicamente
                $status_counts[$order_status] = 1;
                if (!isset($status_labels[$order_status])) {
                    $status_labels[$order_status] = ucfirst(str_replace('_', ' ', $order_status));
                }
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

    // Calcular vendas do dia (00:00:00 às 23:59:59)
    $today_start = current_time('Y-m-d') . ' 00:00:00';
    $today_end = current_time('Y-m-d') . ' 23:59:59';
    
    $today_sales_query = new WP_Query(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'date_query' => array(
            array(
                'after' => $today_start,
                'before' => $today_end,
                'inclusive' => true
            )
        ),
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => array('paid', 'thanked', 'delivered', 'completed'),
                'compare' => 'IN'
            )
        )
    ));
    
    $today_sales_amount = 0;
    $today_sales_count = 0;
    
    if ($today_sales_query->have_posts()) {
        while ($today_sales_query->have_posts()) {
            $today_sales_query->the_post();
            $payment_amount = get_field('payment_amount') ?: 0;
            $today_sales_amount += $payment_amount;
            $today_sales_count++;
        }
        wp_reset_postdata();
    }
    
    // Calcular sem pagamentos + cancelados do dia atual (00:00:00 às 23:59:59)
    $no_payment_canceled_query = new WP_Query(array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'date_query' => array(
            array(
                'after' => $today_start,
                'before' => $today_end,
                'inclusive' => true
            )
        ),
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => array('awaiting_payment', 'created', 'canceled'),
                'compare' => 'IN'
            )
        )
    ));
    
    $no_payment_canceled_count = $no_payment_canceled_query->found_posts;
    wp_reset_postdata();

    // Mapeamento de cores semânticas para cada status (mesmas cores dos badges)
    $status_chart_colors = array(
        'created' => '#9CA3AF',                    // gray-400 (neutro)
        'awaiting_payment' => '#F59E0B',           // amber-500 (atenção)
        'paid' => '#10B981',                       // green-500 (sucesso - pago)
        'thanked' => '#3B82F6',                    // blue-500 (comunicação)
        'created_assets_text' => '#8B5CF6',        // purple-500 (processamento)
        'created_assets_illustration' => '#6366F1', // indigo-500 (processamento)
        'created_assets_merge' => '#EC4899',       // pink-500 (processamento)
        'ready_for_delivery' => '#14B8A6',         // teal-500 (pronto)
        'delivered' => '#059669',                  // emerald-600 (entregue - verde esmeralda)
        'completed' => '#15803D',                  // green-700 (concluído - verde escuro)
        'canceled' => '#EF4444',                   // red-500 (cancelado - vermelho)
        'error' => '#DC2626'                       // red-600 (erro - vermelho escuro)
    );

    // Preparar dados para o gráfico com cores semânticas
    $chart_labels = array();
    $chart_data = array();
    $chart_colors = array();
    
    foreach ($status_counts as $status => $count) {
        if ($count > 0) {
            $chart_labels[] = $status_labels[$status];
            $chart_data[] = $count;
            // Usar cor específica do status ou fallback para cinza
            $chart_colors[] = isset($status_chart_colors[$status]) ? $status_chart_colors[$status] : '#9CA3AF';
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
    </div>

    <!-- Estatísticas Adicionais -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-green-50 rounded-lg p-3 sm:p-4 border border-green-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-green-600 uppercase truncate">Vendas no Dia</p>
                    <p class="text-lg sm:text-xl font-bold text-green-900 mt-1 break-words">R$ <?php echo number_format($today_sales_amount, 2, ',', '.'); ?></p>
                    <p class="text-xs text-green-700 mt-1"><?php echo number_format($today_sales_count); ?> pedido(s)</p>
                </div>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <div class="bg-red-50 rounded-lg p-3 sm:p-4 border border-red-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-red-600 uppercase truncate">Sem Pagamento + Cancelados</p>
                    <p class="text-lg sm:text-xl font-bold text-red-900 mt-1 break-words"><?php echo number_format($no_payment_canceled_count); ?></p>
                    <p class="text-xs text-red-700 mt-1">Hoje</p>
                </div>
                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-red-500 rounded-lg flex items-center justify-center flex-shrink-0 ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
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
            foreach ($status_counts as $status => $count):
                if ($count > 0):
                    // Usar cor específica do status para a legenda (mesma cor do gráfico)
                    $legend_color = isset($status_chart_colors[$status]) ? $status_chart_colors[$status] : '#9CA3AF';
            ?>
                <div class="flex items-center truncate">
                    <div class="w-3 h-3 rounded-full mr-2 flex-shrink-0" style="background-color: <?php echo esc_attr($legend_color); ?>"></div>
                    <span class="text-gray-700 truncate"><?php echo esc_html($status_labels[$status]); ?> (<?php echo $count; ?>)</span>
                </div>
            <?php 
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
                            backgroundColor: <?php echo json_encode($chart_colors); ?>,
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
    <?php
}

/**
 * Widget do Dashboard: Cupons e Vendas
 */
function protagonizei_dashboard_coupons_widget() {
    // Buscar todos os cupons (publicados e rascunho)
    $coupons_query = new WP_Query(array(
        'post_type' => 'coupons',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft')
    ));

    if (empty($coupons_query->posts)) {
        echo '<p class="text-gray-500 text-sm">Nenhum cupom encontrado.</p>';
        
        // Link para ver todos os cupons
        $all_coupons_link = admin_url('edit.php?post_type=coupons');
        echo '<div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200">';
        echo '<a href="' . esc_url($all_coupons_link) . '" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todos os cupons →</a>';
        echo '</div>';
        
        wp_reset_postdata();
        return;
    }

    $coupons_data = array();
    
    // Processar cada cupom
    foreach ($coupons_query->posts as $coupon_post) {
        $coupon_id = $coupon_post->ID;
        $coupon_code = get_the_title($coupon_id);
        $discount_type = get_field('discount_type', $coupon_id);
        $discount_fixed_amount = get_field('discount_fixed_amount', $coupon_id) ?: 0;
        $discount_percentage = get_field('discount_percentage', $coupon_id) ?: 0;
        $post_status = $coupon_post->post_status;
        
        // Buscar pedidos que usaram este cupom
        $coupon_orders = new WP_Query(array(
            'post_type' => 'orders',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'applied_coupon',
                    'value' => $coupon_code,
                    'compare' => '='
                )
            )
        ));
        
        $total_sales = 0;
        $total_discount = 0;
        $total_orders = 0; // Todos os pedidos (incluindo não pagos)
        $paid_orders_count = 0; // Apenas pedidos pagos/aprovados
        
        // Status considerados como pagos/aprovados
        $paid_statuses = array('paid', 'thanked', 'delivered', 'completed');
        
        if ($coupon_orders->have_posts()) {
            while ($coupon_orders->have_posts()) {
                $coupon_orders->the_post();
                $order_status = get_field('order_status');
                $payment_amount = get_field('payment_amount') ?: 0;
                
                // Contar todos os pedidos
                $total_orders++;
                
                // Verificar se o pedido está pago/aprovado
                $is_paid = in_array(strtolower(trim($order_status)), $paid_statuses);
                
                if ($is_paid) {
                    $paid_orders_count++;
                    $total_sales += $payment_amount;
                    
                    // Calcular desconto baseado no tipo (apenas para pedidos pagos)
                    if ($discount_type === 'fixed') {
                        $total_discount += $discount_fixed_amount;
                    } elseif ($discount_type === 'percent') {
                        $original_amount = $payment_amount / (1 - ($discount_percentage / 100));
                        $total_discount += ($original_amount - $payment_amount);
                    }
                }
            }
            wp_reset_postdata();
        }
        
        $coupons_data[] = array(
            'id' => $coupon_id,
            'code' => $coupon_code,
            'type' => $discount_type,
            'discount_value' => $discount_type === 'fixed' ? $discount_fixed_amount : $discount_percentage,
            'total_orders' => $total_orders,
            'paid_orders_count' => $paid_orders_count,
            'total_sales' => $total_sales,
            'total_discount' => $total_discount,
            'status' => $post_status
        );
    }
    wp_reset_postdata();
    
    // Ordenar por total de pedidos (mais usado primeiro)
    usort($coupons_data, function($a, $b) {
        return $b['total_orders'] - $a['total_orders'];
    });
    
    echo '<div class="space-y-3 sm:space-y-4">';
    foreach ($coupons_data as $coupon) {
        $edit_link = get_edit_post_link($coupon['id']);
        $status_badge = $coupon['status'] === 'publish' 
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 whitespace-nowrap">Publicado</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 whitespace-nowrap">Rascunho</span>';
        
        echo '<div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4 hover:shadow-md transition-shadow">';
        
        // Cabeçalho do cupom
        echo '<div class="mb-3 sm:mb-4">';
        if ($edit_link) {
            echo '<h4 class="font-bold text-gray-900 text-base sm:text-lg mb-2 break-words"><a href="' . esc_url($edit_link) . '" class="hover:text-blue-600">' . esc_html($coupon['code']) . '</a></h4>';
        } else {
            echo '<h4 class="font-bold text-gray-900 text-base sm:text-lg mb-2 break-words">' . esc_html($coupon['code']) . '</h4>';
        }
        
        // Badges e informações do desconto
        echo '<div class="flex flex-wrap items-center gap-2">';
        echo $status_badge;
        echo '<span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full whitespace-nowrap ' . ($coupon['type'] === 'fixed' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') . '">';
        echo $coupon['type'] === 'fixed' ? 'Fixo' : 'Percentual';
        echo '</span>';
        if ($coupon['type'] === 'fixed') {
            echo '<span class="text-xs sm:text-sm text-gray-700 font-medium whitespace-nowrap">R$ ' . number_format($coupon['discount_value'], 2, ',', '.') . '</span>';
        } else {
            echo '<span class="text-xs sm:text-sm text-gray-700 font-medium whitespace-nowrap">' . number_format($coupon['discount_value'], 1) . '%</span>';
        }
        echo '</div>';
        echo '</div>';
        
        // Estatísticas do cupom - Grid 2x2 em mobile, 4 colunas em desktop
        echo '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 pt-3 border-t border-gray-200">';
        
        // Pedidos
        echo '<div class="text-center sm:text-left">';
        echo '<p class="text-xs text-gray-500 mb-1">Pedidos</p>';
        echo '<p class="text-base sm:text-lg font-bold text-gray-900">' . number_format($coupon['total_orders']) . '</p>';
        echo '</div>';
        
        // Vendas
        echo '<div class="text-center sm:text-left">';
        echo '<p class="text-xs text-gray-500 mb-1">Vendas</p>';
        echo '<p class="text-base sm:text-lg font-bold text-blue-600">' . number_format($coupon['paid_orders_count']) . '</p>';
        echo '</div>';
        
        // Total Vendido
        echo '<div class="text-center sm:text-left">';
        echo '<p class="text-xs text-gray-500 mb-1">Total Vendido</p>';
        echo '<p class="text-sm sm:text-base font-bold text-green-600 break-words">R$ ' . number_format($coupon['total_sales'], 2, ',', '.') . '</p>';
        echo '</div>';
        
        // Descontado
        echo '<div class="text-center sm:text-left">';
        echo '<p class="text-xs text-gray-500 mb-1">Descontado</p>';
        echo '<p class="text-sm sm:text-base font-bold text-red-600 break-words">R$ ' . number_format($coupon['total_discount'], 2, ',', '.') . '</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    // Link para ver todos os cupons
    $all_coupons_link = admin_url('edit.php?post_type=coupons');
    echo '<div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-gray-200">';
    echo '<a href="' . esc_url($all_coupons_link) . '" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todos os cupons →</a>';
    echo '</div>';
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
 * Widget do Dashboard: Hub de Serviços Externos
 */
function protagonizei_dashboard_api_balances_widget() {
    // Limpar cache ao carregar para sempre buscar dados atualizados
    delete_transient('protagonizei_deepseek_balance');
    
    // Buscar saldo do Deepseek
    $deepseek_balance = protagonizei_get_deepseek_balance();
    
    ?>
    <div class="space-y-3 sm:space-y-4">
        <!-- Deepseek -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-4 border border-blue-200 hover:shadow-md transition-shadow">
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
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-4 border border-purple-200 hover:shadow-md transition-shadow">
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

        <!-- N8N -->
        <a href="https://n8n.srv1238427.hstgr.cloud/home/workflows" target="_blank" class="block">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg p-4 border border-green-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">N8N</h3>
                            <p class="text-xs text-green-600 hover:text-green-800 font-medium">Abrir workflows →</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>
        </a>

        <!-- SendGrid -->
        <a href="https://app.sendgrid.com/" target="_blank" class="block">
            <div class="bg-gradient-to-r from-orange-50 to-amber-50 rounded-lg p-4 border border-orange-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">SendGrid</h3>
                            <p class="text-xs text-orange-600 hover:text-orange-800 font-medium">Abrir dashboard →</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </div>
            </div>
        </a>
    </div>
    <?php
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
        'protagonizei_coupons_dashboard',
        'Cupons e Vendas',
        'protagonizei_dashboard_coupons_widget'
    );
    
    wp_add_dashboard_widget(
        'protagonizei_api_balances_dashboard',
        'Hub de Serviços Externos',
        'protagonizei_dashboard_api_balances_widget'
    );
}
add_action('wp_dashboard_setup', 'protagonizei_add_dashboard_widgets');

