<?php
/**
 * Dashboard Principal - Protagonizei CMS
 * 
 * Dashboard interativo com gráficos e estatísticas de vendas
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
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Protagonizei CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/dashboard.css">
    <style>
        [x-cloak] { display: none !important; }
        .metric-card {
            @apply bg-white rounded-lg shadow-sm border border-gray-200 p-6 transition-all duration-200 hover:shadow-md;
        }
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        .loading-spinner {
            @apply inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600;
        }
        .status-badge {
            @apply inline-flex items-center px-2 py-1 rounded-full text-xs font-medium;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen" x-data="dashboardApp()" x-init="init()">
    
    <!-- Loading Overlay -->
    <div x-show="loading" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-lg p-6 flex items-center space-x-3">
            <div class="loading-spinner"></div>
            <span class="text-gray-700">Carregando dados...</span>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard Protagonizei</h1>
                    <p class="text-gray-600 mt-1">Acompanhe suas vendas e métricas em tempo real</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="testEndpoint()" class="inline-flex items-center px-3 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors text-sm">
                        <i class="fas fa-flask mr-2"></i>
                        Testar API
                    </button>
                    <button @click="refreshData()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sync-alt mr-2" :class="{ 'animate-spin': loading }"></i>
                        Atualizar
                    </button>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=orders')); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-list mr-2"></i>
                        Ver Todos os Pedidos
                    </a>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6" x-data="{ showFilters: false }">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">Filtros</h3>
                <button @click="showFilters = !showFilters" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-filter mr-2"></i>
                    <span x-text="showFilters ? 'Ocultar' : 'Mostrar'"></span>
                </button>
            </div>
            
            <div x-show="showFilters" x-collapse class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                    <input type="date" x-model="filters.start_date" @change="applyFilters()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                    <input type="date" x-model="filters.end_date" @change="applyFilters()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select x-model="filters.status" @change="applyFilters()" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos os Status</option>
                        <option value="created">Criado</option>
                        <option value="awaiting_payment">Aguardando Pagamento</option>
                        <option value="paid">Pago</option>
                        <option value="thanked">Agradecido</option>
                        <option value="completed">Concluído</option>
                        <option value="error">Erro</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="clearFilters()" class="w-full px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>
                        Limpar
                    </button>
                </div>
            </div>
        </div>

        <!-- Métricas Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total de Vendas -->
            <div class="metric-card">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-shopping-cart text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600">Total de Vendas</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.total_orders || 0"></p>
                        <p class="text-xs text-gray-500 mt-1">Pedidos criados</p>
                    </div>
                </div>
            </div>

            <!-- Receita Total -->
            <div class="metric-card">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600">Receita Total</p>
                        <p class="text-2xl font-bold text-green-600" x-text="formatCurrency(stats.total_revenue || 0)"></p>
                        <p class="text-xs text-gray-500 mt-1">Apenas pedidos pagos</p>
                    </div>
                </div>
            </div>

            <!-- Cupons Utilizados -->
            <div class="metric-card">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600">Cupons Utilizados</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.total_coupons_used || 0"></p>
                        <p class="text-xs text-gray-500 mt-1">Total de usos</p>
                    </div>
                </div>
            </div>

            <!-- Valor Médio do Pedido -->
            <div class="metric-card">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-600">Ticket Médio</p>
                        <p class="text-2xl font-bold text-purple-600" x-text="formatCurrency(stats.average_order_value || 0)"></p>
                        <p class="text-xs text-gray-500 mt-1">Valor médio por pedido</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Gráfico de Vendas por Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                    Pedidos por Status
                </h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Gráfico de Receita Mensal -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-chart-line mr-2 text-green-600"></i>
                    Receita por Mês
                </h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Top Cupons -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                    Top Cupons Utilizados
                </h3>
                <div class="space-y-3">
                    <template x-for="(coupon, index) in stats.top_coupons?.slice(0, 5) || []" :key="coupon.code">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="inline-flex items-center justify-center w-6 h-6 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full mr-3" x-text="index + 1"></span>
                                <div>
                                    <p class="font-medium text-gray-900" x-text="coupon.code"></p>
                                    <p class="text-xs text-gray-500" x-text="coupon.count + ' usos • ' + formatCurrency(coupon.revenue)"></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-medium text-gray-900" x-text="coupon.count + 'x'"></p>
                            </div>
                        </div>
                    </template>
                    <div x-show="!stats.top_coupons || stats.top_coupons.length === 0" class="text-center py-8 text-gray-500">
                        <i class="fas fa-ticket-alt text-4xl mb-4 opacity-50"></i>
                        <p>Nenhum cupom utilizado ainda</p>
                    </div>
                </div>
            </div>

            <!-- Funil de Conversão -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-funnel-dollar mr-2 text-purple-600"></i>
                    Funil de Conversão
                </h3>
                <div class="space-y-4">
                    <template x-for="(stage, index) in stats.conversion_funnel || []" :key="stage.stage">
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700" x-text="stage.stage"></span>
                                <span class="text-sm text-gray-500" x-text="stage.count"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-gradient-to-r from-purple-500 to-blue-500 h-3 rounded-full transition-all duration-500" 
                                     :style="'width: ' + calculateFunnelPercentage(stage.count, index) + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Pedidos Recentes -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-clock mr-2 text-gray-600"></i>
                Pedidos Recentes
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pedido</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cupom</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="order in stats.recent_orders || []" :key="order.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900" x-text="'#' + order.id"></div>
                                    <div class="text-sm text-gray-500" x-text="order.child_name"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="order.buyer_name"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge" :class="getStatusBadgeClass(order.status)" x-text="order.status_label"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatCurrency(order.amount)"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span x-show="order.coupon" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800" x-text="order.coupon"></span>
                                    <span x-show="!order.coupon" class="text-sm text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(order.date)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a :href="order.url" class="text-blue-600 hover:text-blue-900">Ver</a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <div x-show="!stats.recent_orders || stats.recent_orders.length === 0" class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-4 opacity-50"></i>
                    <p>Nenhum pedido encontrado</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configurar nonce do WordPress para API
        window.wpApiSettings = {
            root: '<?php echo esc_url_raw(rest_url()); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
        };
    </script>
    
    <!-- Carregar scripts em ordem -->
    <script src="<?php echo get_template_directory_uri(); ?>/assets/dashboard.js"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
