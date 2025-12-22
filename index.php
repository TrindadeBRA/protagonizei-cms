<?php
if (!is_user_logged_in()) {
    $frontend_urls = get_option('trinitykitcms_frontend_app_url');
        if ($frontend_urls) {
        $urls_array = array_map('trim', explode(',', $frontend_urls));
        $main_frontend_url = $urls_array[0];
        wp_redirect($main_frontend_url);
        exit;
    }
}

get_header();

// Buscar dados dos pedidos para o gráfico
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

// Contar pedidos por status
if ($orders_query->have_posts()) {
    while ($orders_query->have_posts()) {
        $orders_query->the_post();
        $order_status = get_field('order_status');
        if (isset($status_counts[$order_status])) {
            $status_counts[$order_status]++;
        }
    }
    wp_reset_postdata();
}

// Buscar dados dos cupons
$coupons_query = new WP_Query(array(
    'post_type' => 'coupons',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

$coupons_data = array();
if ($coupons_query->have_posts()) {
    while ($coupons_query->have_posts()) {
        $coupons_query->the_post();
        $coupon_code = get_the_title();
        $discount_type = get_field('discount_type');
        $discount_fixed_amount = get_field('discount_fixed_amount') ?: 0;
        $discount_percentage = get_field('discount_percentage') ?: 0;
        
        // Buscar pedidos que usaram este cupom
        $coupon_orders = new WP_Query(array(
            'post_type' => 'orders',
            'posts_per_page' => -1,
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
        $usage_count = 0;
        
        if ($coupon_orders->have_posts()) {
            while ($coupon_orders->have_posts()) {
                $coupon_orders->the_post();
                $payment_amount = get_field('payment_amount') ?: 0;
                $total_sales += $payment_amount;
                $usage_count++;
                
                // Calcular desconto baseado no tipo
                if ($discount_type === 'fixed') {
                    $total_discount += $discount_fixed_amount;
                } elseif ($discount_type === 'percent') {
                    $original_amount = $payment_amount / (1 - ($discount_percentage / 100));
                    $total_discount += ($original_amount - $payment_amount);
                }
            }
            wp_reset_postdata();
        }
        
        $coupons_data[] = array(
            'code' => $coupon_code,
            'type' => $discount_type,
            'discount_value' => $discount_type === 'fixed' ? $discount_fixed_amount : $discount_percentage,
            'usage_count' => $usage_count,
            'total_sales' => $total_sales,
            'total_discount' => $total_discount
        );
    }
    wp_reset_postdata();
}

// Calcular totais gerais
$total_orders = array_sum($status_counts);
$total_revenue = 0;
$orders_revenue_query = new WP_Query(array(
    'post_type' => 'orders',
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

if ($orders_revenue_query->have_posts()) {
    while ($orders_revenue_query->have_posts()) {
        $orders_revenue_query->the_post();
        $payment_amount = get_field('payment_amount') ?: 0;
        $total_revenue += $payment_amount;
    }
    wp_reset_postdata();
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="min-h-screen bg-gray-100 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header do Dashboard -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Dashboard Protagonizei</h1>
                        <p class="text-gray-600 mt-1">Visão geral dos pedidos e cupons</p>
                    </div>
                    <div class="text-right">
                        <img src="<?php echo get_template_directory_uri(); ?>/assets/thetrinityweb.webp" alt="Logo" class="h-16">
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total de Pedidos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($total_orders); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Receita Total</p>
                        <p class="text-2xl font-bold text-gray-900">R$ <?php echo number_format($total_revenue, 2, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Cupons Ativos</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($coupons_data); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráfico de Pizza e Acesso Rápido -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Gráfico de Pizza dos Status -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">
                    <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                    Status dos Pedidos
                </h2>
                <div class="relative">
                    <canvas id="statusChart" width="400" height="400"></canvas>
                </div>
                
                <!-- Legenda -->
                <div class="mt-6 grid grid-cols-2 gap-2 text-xs">
                    <?php 
                    $colors = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#6B7280', '#14B8A6', '#F97316', '#84CC16', '#6366F1'];
                    $color_index = 0;
                    foreach ($status_counts as $status => $count):
                        if ($count > 0):
                    ?>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $colors[$color_index % count($colors)]; ?>"></div>
                            <span class="text-gray-700"><?php echo esc_html($status_labels[$status]); ?> (<?php echo $count; ?>)</span>
                        </div>
                    <?php 
                        $color_index++;
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            
            <!-- Links de Acesso Rápido -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">
                    <i class="fas fa-link mr-2 text-green-600"></i>
                    Acesso Rápido
                </h2>
                <div class="space-y-4">
                    <a href="<?php echo admin_url('edit.php?post_type=orders'); ?>" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                        <i class="fas fa-shopping-cart text-blue-600 mr-3"></i>
                        <div>
                            <p class="font-medium text-blue-900">Gerenciar Pedidos</p>
                            <p class="text-sm text-blue-700">Ver todos os pedidos</p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('edit.php?post_type=coupons'); ?>" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                        <i class="fas fa-ticket-alt text-purple-600 mr-3"></i>
                        <div>
                            <p class="font-medium text-purple-900">Gerenciar Cupons</p>
                            <p class="text-sm text-purple-700">Criar e editar cupons</p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url('edit.php?post_type=book_templates'); ?>" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors">
                        <i class="fas fa-book text-green-600 mr-3"></i>
                        <div>
                            <p class="font-medium text-green-900">Templates de Livros</p>
                            <p class="text-sm text-green-700">Gerenciar modelos</p>
                        </div>
                    </a>
                    
                    <a href="<?php echo admin_url(); ?>" class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fas fa-cog text-gray-600 mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Painel Admin</p>
                            <p class="text-sm text-gray-700">WordPress Admin</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Tabela de Cupons -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">
                    <i class="fas fa-ticket-alt mr-2 text-purple-600"></i>
                    Relatório de Cupons
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Código do Cupom
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo de Desconto
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Valor do Desconto
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usos
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Vendido
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Descontado
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Economia p/ Cliente
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($coupons_data)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-ticket-alt text-4xl mb-4 text-gray-300"></i>
                                    <p>Nenhum cupom encontrado</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons_data as $coupon): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-ticket-alt text-purple-600 text-sm"></i>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900"><?php echo esc_html($coupon['code']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $coupon['type'] === 'fixed' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'; ?>">
                                            <?php echo $coupon['type'] === 'fixed' ? 'Fixo' : 'Percentual'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($coupon['type'] === 'fixed'): ?>
                                            R$ <?php echo number_format($coupon['discount_value'], 2, ',', '.'); ?>
                                        <?php else: ?>
                                            <?php echo number_format($coupon['discount_value'], 1); ?>%
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-900"><?php echo $coupon['usage_count']; ?></span>
                                            <?php if ($coupon['usage_count'] > 0): ?>
                                                <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold bg-green-100 text-green-800 rounded-full">
                                                    Ativo
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        R$ <?php echo number_format($coupon['total_sales'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                        R$ <?php echo number_format($coupon['total_discount'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($coupon['usage_count'] > 0): ?>
                                            R$ <?php echo number_format($coupon['total_discount'] / $coupon['usage_count'], 2, ',', '.'); ?>
                                            <span class="text-xs text-gray-500">por uso</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
// Configuração do gráfico de pizza
const ctx = document.getElementById('statusChart').getContext('2d');
const statusChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: [
            <?php 
            $first = true;
            foreach ($status_counts as $status => $count) {
                if ($count > 0) {
                    if (!$first) echo ', ';
                    echo '"' . esc_js($status_labels[$status]) . '"';
                    $first = false;
                }
            }
            ?>
        ],
        datasets: [{
            data: [
                <?php 
                $first = true;
                foreach ($status_counts as $count) {
                    if ($count > 0) {
                        if (!$first) echo ', ';
                        echo $count;
                        $first = false;
                    }
                }
                ?>
            ],
            backgroundColor: [
                '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', 
                '#EC4899', '#6B7280', '#14B8A6', '#F97316', '#84CC16', '#6366F1'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false // Removemos a legenda padrão para usar nossa própria
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
</script>

<?php
get_footer();
?> 