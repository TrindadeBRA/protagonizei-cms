<?php
/**
 * Template para exibir um cupom individual
 * 
 * Template personalizado para visualizar detalhes completos de um cupom
 * com estatísticas de uso e informações de desconto
 * 
 * @package TrinityKit
 */

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica se o usuário tem permissão para ver este conteúdo
if (!current_user_can('edit_posts')) {
    wp_die(__('Você não tem permissão para acessar esta página.'));
}

// Obtém o post atual
global $post;
$coupon_id = get_the_ID();
$coupon_code = get_the_title();

// Obtém todos os campos ACF do cupom
$discount_type = get_field('discount_type', $coupon_id);
$discount_fixed_amount = get_field('discount_fixed_amount', $coupon_id) ?: 0;
$discount_percentage = get_field('discount_percentage', $coupon_id) ?: 0;

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
$orders_list = array();

if ($coupon_orders->have_posts()) {
    while ($coupon_orders->have_posts()) {
        $coupon_orders->the_post();
        $order_id = get_the_ID();
        $payment_amount = get_field('payment_amount', $order_id) ?: 0;
        $order_status = get_field('order_status', $order_id);
        $child_name = get_field('child_name', $order_id);
        $buyer_email = get_field('buyer_email', $order_id);
        $payment_date = get_field('payment_date', $order_id);
        
        $total_sales += $payment_amount;
        $usage_count++;
        
        // Calcular desconto baseado no tipo
        $order_discount = 0;
        if ($discount_type === 'fixed') {
            $order_discount = $discount_fixed_amount;
        } elseif ($discount_type === 'percent') {
            $original_amount = $payment_amount / (1 - ($discount_percentage / 100));
            $order_discount = $original_amount - $payment_amount;
        }
        $total_discount += $order_discount;
        
        $orders_list[] = array(
            'id' => $order_id,
            'child_name' => $child_name,
            'buyer_email' => $buyer_email,
            'payment_amount' => $payment_amount,
            'order_discount' => $order_discount,
            'order_status' => $order_status,
            'payment_date' => $payment_date,
            'date' => get_the_date('d/m/Y H:i', $order_id)
        );
    }
    wp_reset_postdata();
}

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
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupom: <?php echo esc_html($coupon_code); ?> - Protagonizei</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .clickable-image {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .clickable-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-ticket-alt mr-2 text-purple-600"></i>
                        <span class="break-words"><?php echo esc_html($coupon_code); ?></span>
                    </h1>
                    <p class="text-gray-600 mt-1">
                        ID: <?php echo esc_html($coupon_id); ?> | 
                        <?php echo $usage_count > 0 ? $usage_count . ' uso(s)' : 'Nenhum uso'; ?>
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=coupons')); ?>" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span class="hidden sm:inline">Voltar para Lista</span>
                        <span class="sm:hidden">Voltar</span>
                    </a>
                    <a href="<?php echo esc_url(get_edit_post_link($coupon_id)); ?>" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                        <i class="fas fa-edit mr-2"></i>
                        Editar Cupom
                    </a>
                </div>
            </div>
        </div>

        <!-- Cards de Informações -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
            <!-- Tipo de Desconto -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-tag text-white text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-600">Tipo de Desconto</p>
                        <p class="text-lg sm:text-xl font-bold text-gray-900 truncate">
                            <?php 
                            if ($discount_type === 'fixed') {
                                echo 'Desconto Fixo';
                            } elseif ($discount_type === 'percent') {
                                echo 'Desconto Percentual';
                            } else {
                                echo 'Não definido';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Valor do Desconto -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-600">Valor do Desconto</p>
                        <p class="text-lg sm:text-xl font-bold text-gray-900">
                            <?php 
                            if ($discount_type === 'fixed') {
                                echo 'R$ ' . number_format($discount_fixed_amount, 2, ',', '.');
                            } elseif ($discount_type === 'percent') {
                                echo number_format($discount_percentage, 2, ',', '.') . '%';
                            } else {
                                echo '-';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Total Vendido -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-600">Total Vendido</p>
                        <p class="text-lg sm:text-xl font-bold text-gray-900">
                            R$ <?php echo number_format($total_sales, 2, ',', '.'); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Total Descontado -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-red-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-percent text-white text-lg sm:text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-600">Total Descontado</p>
                        <p class="text-lg sm:text-xl font-bold text-red-600">
                            R$ <?php echo number_format($total_discount, 2, ',', '.'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas Adicionais -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-600 mb-2">Número de Usos</p>
                    <p class="text-3xl font-bold text-purple-600"><?php echo number_format($usage_count); ?></p>
                    <?php if ($usage_count > 0): ?>
                        <p class="text-xs text-gray-500 mt-2">
                            Média: R$ <?php echo number_format($total_discount / $usage_count, 2, ',', '.'); ?> por uso
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-600 mb-2">Economia Média</p>
                    <p class="text-3xl font-bold text-green-600">
                        R$ <?php echo $usage_count > 0 ? number_format($total_discount / $usage_count, 2, ',', '.') : '0,00'; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-2">por pedido</p>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-600 mb-2">Ticket Médio</p>
                    <p class="text-3xl font-bold text-blue-600">
                        R$ <?php echo $usage_count > 0 ? number_format($total_sales / $usage_count, 2, ',', '.') : '0,00'; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-2">por pedido</p>
                </div>
            </div>
        </div>

        <!-- Tabela de Pedidos que Usaram o Cupom -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-list mr-2 text-purple-600"></i>
                    Pedidos que Usaram este Cupom
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if (empty($orders_list)): ?>
                    <div class="px-4 sm:px-6 py-12 text-center">
                        <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhum pedido encontrado</h3>
                        <p class="text-gray-600">Este cupom ainda não foi utilizado em nenhum pedido.</p>
                    </div>
                <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pedido
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cliente
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Valor Pago
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Desconto
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Data
                                </th>
                                <th class="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($orders_list as $order): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                                <span class="text-purple-600 font-medium text-sm">#</span>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <a href="<?php echo esc_url(get_permalink($order['id'])); ?>" class="text-blue-600 hover:text-blue-800">
                                                        Pedido #<?php echo esc_html($order['id']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php echo esc_html($order['child_name'] ?: 'Sem nome'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 truncate max-w-xs">
                                            <?php echo esc_html($order['buyer_email'] ?: '-'); ?>
                                        </div>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo esc_attr($status_colors[$order['order_status']] ?? 'bg-gray-100 text-gray-800'); ?>">
                                            <?php echo esc_html($status_labels[$order['order_status']] ?? $order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        R$ <?php echo number_format($order['payment_amount'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                        R$ <?php echo number_format($order['order_discount'], 2, ',', '.'); ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo esc_html($order['date']); ?>
                                        <?php if ($order['payment_date']): ?>
                                            <div class="text-xs text-gray-400">
                                                Pago: <?php echo date('d/m/Y H:i', strtotime($order['payment_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="<?php echo esc_url(get_permalink($order['id'])); ?>" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye mr-1"></i>
                                            <span class="hidden sm:inline">Ver</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 sm:px-6 py-4 text-sm font-semibold text-gray-900 text-right">
                                    Totais:
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                    R$ <?php echo number_format($total_sales, 2, ',', '.'); ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-semibold text-red-600">
                                    R$ <?php echo number_format($total_discount, 2, ',', '.'); ?>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

