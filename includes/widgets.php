<?php

/**
 * Widget: Últimos Contatos
 * Exibe os últimos 10 contatos recebidos com data, nome e tags
 */
class Protagonizei_Recent_Contacts_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'protagonizei_recent_contacts',
            'Últimos Contatos',
            array(
                'description' => 'Exibe os últimos 10 contatos recebidos com data, nome e tags'
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

        // Buscar últimos 10 contatos
        $contacts = get_posts(array(
            'post_type' => 'contact_form',
            'posts_per_page' => 10,
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
        $posts_per_page = !empty($instance['posts_per_page']) ? $instance['posts_per_page'] : 10;
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
        $instance['posts_per_page'] = (!empty($new_instance['posts_per_page'])) ? intval($new_instance['posts_per_page']) : 10;
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
 * Adicionar suporte a Tailwind CSS nos widgets do admin e dashboard
 */
function protagonizei_widget_styles() {
    // Verificar se estamos na página do dashboard ou widgets
    $screen = get_current_screen();
    if ($screen && ($screen->id === 'dashboard' || $screen->id === 'widgets')) {
        ?>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            .widget-content {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            /* Garantir que os widgets do dashboard usem Tailwind */
            #protagonizei_recent_contacts_dashboard .inside,
            #protagonizei_recent_orders_dashboard .inside {
                padding: 12px;
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
    // Buscar últimos 10 contatos
    $contacts = get_posts(array(
        'post_type' => 'contact_form',
        'posts_per_page' => 10,
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
            $phone = get_field('phone', $contact->ID);
            $date = get_the_date('d/m/Y H:i', $contact->ID);
            $tags = wp_get_post_terms($contact->ID, 'contact_tags', array('fields' => 'names'));
            $edit_link = get_edit_post_link($contact->ID);
            
            echo '<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">';
            echo '<div class="flex justify-between items-start mb-2">';
            echo '<div class="flex-1">';
            if ($edit_link) {
                echo '<h4 class="font-semibold text-gray-900 text-sm"><a href="' . esc_url($edit_link) . '" class="hover:text-blue-600">' . esc_html($name ?: 'Sem nome') . '</a></h4>';
            } else {
                echo '<h4 class="font-semibold text-gray-900 text-sm">' . esc_html($name ?: 'Sem nome') . '</h4>';
            }
            if ($email) {
                echo '<p class="text-xs text-gray-500 mt-1">' . esc_html($email) . '</p>';
            }
            if ($phone) {
                echo '<p class="text-xs text-gray-500">' . esc_html($phone) . '</p>';
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
        
        // Link para ver todos os contatos
        $all_contacts_link = admin_url('edit.php?post_type=contact_form');
        echo '<div class="mt-4 pt-4 border-t border-gray-200">';
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
        'posts_per_page' => 10,
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

        echo '<div class="space-y-3">';
        foreach ($orders as $order) {
            $status = get_field('order_status', $order->ID);
            $child_name = get_field('child_name', $order->ID);
            $buyer_email = get_field('buyer_email', $order->ID);
            $date = get_the_date('d/m/Y H:i', $order->ID);
            $payment_date = get_field('payment_date', $order->ID);
            $edit_link = get_edit_post_link($order->ID);
            
            $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
            $status_color = isset($status_colors[$status]) ? $status_colors[$status] : 'bg-gray-100 text-gray-800';
            
            echo '<div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">';
            echo '<div class="flex justify-between items-start mb-2">';
            echo '<div class="flex-1">';
            if ($edit_link) {
                echo '<h4 class="font-semibold text-gray-900 text-sm"><a href="' . esc_url($edit_link) . '" class="hover:text-blue-600">Pedido #' . esc_html($order->ID) . '</a></h4>';
            } else {
                echo '<h4 class="font-semibold text-gray-900 text-sm">Pedido #' . esc_html($order->ID) . '</h4>';
            }
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
        
        // Link para ver todos os pedidos
        $all_orders_link = admin_url('edit.php?post_type=orders');
        echo '<div class="mt-4 pt-4 border-t border-gray-200">';
        echo '<a href="' . esc_url($all_orders_link) . '" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Ver todos os pedidos →</a>';
        echo '</div>';
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
}
add_action('wp_dashboard_setup', 'protagonizei_add_dashboard_widgets');

