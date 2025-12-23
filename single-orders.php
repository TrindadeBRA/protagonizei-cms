<?php
/**
 * Template para exibir um pedido individual
 * 
 * Template personalizado para gerenciar pedidos com interface moderna usando Tailwind CSS
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

// Processar alteração de status ANTES de qualquer output
if (isset($_POST['action']) && $_POST['action'] === 'update_order_status' && wp_verify_nonce($_POST['order_status_nonce'], 'update_order_status')) {
    $new_status = sanitize_text_field($_POST['new_status']);
    $order_id = intval($_POST['order_id']);
    
    if ($new_status && $order_id && get_post_type($order_id) === 'orders') {
        $old_status = get_field('order_status', $order_id);
        
        // Status labels para logs
        $status_labels_for_log = array(
            'created' => 'Criado',
            'awaiting_payment' => 'Aguardando Pagamento',
            'paid' => 'Pago',
            'thanked' => 'Agradecido',
            'created_assets_text' => 'Assets de Texto Criados',
            'created_assets_illustration' => 'Assets de Ilustração Criados',
            'created_assets_merge' => 'Assets Finais Criados',
            'ready_for_delivery' => 'Pronto para Entrega',
            'delivered' => 'Entregue',
            'completed' => 'Concluído (Entregue e PDF Gerado)',
            'canceled' => 'Cancelado',
            'error' => 'Erro'
        );
        
        update_field('order_status', $new_status, $order_id);
        
        // Usar a função de log existente se disponível
        if (function_exists('trinitykit_add_post_log')) {
            trinitykit_add_post_log(
                $order_id,
                'admin-manual',
                "Status alterado de '{$status_labels_for_log[$old_status]}' para '{$status_labels_for_log[$new_status]}' manualmente pelo admin",
                $old_status,
                $new_status
            );
        }
        
        // Redirect para evitar resubmissão
        wp_redirect(get_permalink($order_id) . '?status_updated=1');
        exit;
    }
}

// Obtém o post atual
global $post;
$order_id = get_the_ID();

// Obtém todos os campos ACF do pedido
$order_status = get_field('order_status', $order_id);
$child_name = get_field('child_name', $order_id);
$child_age = get_field('child_age', $order_id);
$child_gender = get_field('child_gender', $order_id);
$child_skin_tone = get_field('child_skin_tone', $order_id);
$child_face_photo = get_field('child_face_photo', $order_id);
$buyer_name = get_field('buyer_name', $order_id);
$buyer_email = get_field('buyer_email', $order_id);
$buyer_phone = get_field('buyer_phone', $order_id);
$book_template = get_field('book_template', $order_id);
$payment_transaction_id = get_field('payment_transaction_id', $order_id);
$payment_date = get_field('payment_date', $order_id);
$payment_amount = get_field('payment_amount', $order_id);
$applied_coupon = get_field('applied_coupon', $order_id);
$generated_pdf_link = get_field('generated_pdf_link', $order_id);
$generated_pdf_attachment = get_field('generated_pdf_attachment', $order_id);
$generated_book_pages = get_field('generated_book_pages', $order_id);

// Status labels para exibição
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
    'completed' => 'Concluído (Entregue e PDF Gerado)',
    'canceled' => 'Cancelado',
    'error' => 'Erro'
);

// Cores para cada status
$status_colors = array(
    'created' => 'bg-gray-100 text-gray-800',
    'awaiting_payment' => 'bg-yellow-100 text-yellow-800',
    'paid' => 'bg-green-100 text-green-800',
    'thanked' => 'bg-blue-100 text-blue-800',
    'created_assets_text' => 'bg-purple-100 text-purple-800',
    'created_assets_illustration' => 'bg-indigo-100 text-indigo-800',
    'created_assets_merge' => 'bg-pink-100 text-pink-800',
    'ready_for_delivery' => 'bg-orange-100 text-orange-800',
    'delivered' => 'bg-teal-100 text-teal-800',
    'completed' => 'bg-green-100 text-green-800',
    'canceled' => 'bg-red-100 text-red-800',
    'error' => 'bg-red-100 text-red-800'
);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido #<?php echo esc_html($order_id); ?> - <?php echo esc_html($child_name ?: 'Sem nome'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
        .status-badge {
            @apply inline-flex items-center px-3 py-1 rounded-full text-sm font-medium;
        }
        
        .clickable-image {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .clickable-image:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        @media (min-width: 640px) {
            #pdf-viewer-container {
                height: 500px;
            }
        }
        
        @media (min-width: 1024px) {
            #pdf-viewer-container {
                height: 600px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php if (isset($_GET['status_updated'])): ?>
        <div class="fixed top-4 left-4 right-4 sm:left-auto sm:right-4 sm:w-auto z-50 bg-green-500 text-white px-4 sm:px-6 py-3 rounded-lg shadow-lg" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
            <i class="fas fa-check mr-2"></i>
            <span class="text-sm sm:text-base">Status atualizado com sucesso!</span>
        </div>
    <?php endif; ?>
    
    <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 xl:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 mb-4 sm:mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div class="flex-1 min-w-0">
                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900 break-words">Pedido #<?php echo esc_html($order_id); ?></h1>
                    <p class="text-sm sm:text-base text-gray-600 mt-1 break-words">
                        <?php echo esc_html($buyer_name ?: 'Comprador não informado'); ?> - <?php echo esc_html($child_name ?: 'Criança não informada'); ?>
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 sm:space-x-0">
                    <span class="inline-flex items-center justify-center px-3 py-1.5 rounded-full text-xs sm:text-sm font-medium <?php echo esc_attr($status_colors[$order_status] ?? 'bg-gray-100 text-gray-800'); ?>">
                        <?php echo esc_html($status_labels[$order_status] ?? $order_status); ?>
                    </span>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=orders')); ?>" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 border border-gray-300 rounded-md shadow-sm text-xs sm:text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span class="hidden sm:inline">Voltar para Lista</span>
                        <span class="sm:hidden">Voltar</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Coluna Principal -->
            <div class="lg:col-span-2 space-y-6 order-2 lg:order-1">
                
                <!-- Gerenciamento de Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6 lg:p-8" x-data="{ showStatusModal: false, isDelivering: false }">
                    <!-- Cabeçalho -->
                    <div class="mb-6">
                        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-gray-900 flex items-center">
                            <span class="w-10 h-10 lg:w-12 lg:h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-3 shadow-md hidden sm:flex">
                                <i class="fas fa-tasks text-white text-lg lg:text-xl"></i>
                            </span>
                            <span class="sm:hidden">
                                <i class="fas fa-tasks mr-2 text-blue-600"></i>
                            </span>
                            Gerenciamento de Status
                        </h2>
                        <p class="text-sm text-gray-600 mt-2 hidden lg:block ml-0 lg:ml-15">
                            Gerencie o status e ações do pedido
                        </p>
                    </div>
                    
                    <!-- Botões de Ação - Grid no Desktop -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-4 mb-6 lg:mb-8">
                        <!-- Botão Entregar Pedido -->
                        <button 
                            @click="isDelivering = true; deliverOrder($el)" 
                            :disabled="isDelivering || <?php echo $order_status !== 'ready_for_delivery' ? 'true' : 'false'; ?>"
                            :class="isDelivering || <?php echo $order_status !== 'ready_for_delivery' ? 'true' : 'false'; ?> ? 'bg-green-200 text-gray-600 cursor-not-allowed' : 'bg-gradient-to-r from-green-500 to-green-600 text-white hover:from-green-600 hover:to-green-700 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5'"
                            class="group relative flex flex-col items-center justify-center px-4 py-4 lg:py-6 rounded-xl transition-all duration-200 text-center"
                            title="<?php echo $order_status !== 'ready_for_delivery' ? 'Pedido precisa estar no status \'Pronto para Entrega\'' : 'Clique para entregar o pedido'; ?>"
                        >
                            <span x-show="!isDelivering" class="flex flex-col items-center">
                                <span class="w-12 h-12 lg:w-14 lg:h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-2 lg:mb-3 group-hover:scale-110 transition-transform hidden lg:flex">
                                    <i class="fas fa-paper-plane text-xl lg:text-2xl"></i>
                                </span>
                                <span class="font-semibold text-sm lg:text-base">Entregar Pedido</span>
                                <span class="text-xs opacity-90 mt-1 hidden lg:block">Enviar para cliente</span>
                            </span>
                            <span x-show="isDelivering" class="flex flex-col items-center">
                                <svg class="animate-spin h-8 w-8 lg:h-10 lg:w-10 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="font-medium">Entregando...</span>
                            </span>
                        </button>
                        
                        <!-- Botão Abrir Páginas Finais -->
                        <?php
                        // Verificar se há páginas finais disponíveis
                        $has_final_pages = false;
                        if ($generated_book_pages) {
                            foreach ($generated_book_pages as $page) {
                                if (!empty($page['final_page_with_text'])) {
                                    $has_final_pages = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <button 
                            onclick="openAllImages()" 
                            <?php if (!$has_final_pages): ?>disabled<?php endif; ?>
                            class="group relative flex flex-col items-center justify-center px-4 py-4 lg:py-6 rounded-xl transition-all duration-200 text-center <?php echo $has_final_pages ? 'bg-gradient-to-r from-purple-500 to-purple-600 text-white hover:from-purple-600 hover:to-purple-700 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5' : 'bg-purple-200 text-gray-600 cursor-not-allowed'; ?>"
                            title="<?php echo $has_final_pages ? 'Abrir todas as páginas finais (com texto) em abas novas' : 'Nenhuma página final disponível ainda'; ?>"
                        >
                            <span class="w-12 h-12 lg:w-14 lg:h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-2 lg:mb-3 group-hover:scale-110 transition-transform hidden lg:flex">
                                <i class="fas fa-images text-xl lg:text-2xl"></i>
                            </span>
                            <span class="font-semibold text-sm lg:text-base">Abrir Páginas Finais</span>
                            <span class="text-xs opacity-90 mt-1 hidden lg:block">
                                <?php echo $has_final_pages ? 'Visualizar em abas' : 'Ainda não disponível'; ?>
                            </span>
                        </button>
                        
                        <!-- Botão Alterar Status -->
                        <button 
                            @click="showStatusModal = true" 
                            class="group relative flex flex-col items-center justify-center px-4 py-4 lg:py-6 rounded-xl transition-all duration-200 text-center bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                        >
                            <span class="w-12 h-12 lg:w-14 lg:h-14 bg-white bg-opacity-20 rounded-full flex items-center justify-center mb-2 lg:mb-3 group-hover:scale-110 transition-transform hidden lg:flex">
                                <i class="fas fa-edit text-xl lg:text-2xl"></i>
                            </span>
                            <span class="font-semibold text-sm lg:text-base">Alterar Status</span>
                            <span class="text-xs opacity-90 mt-1 hidden lg:block">Gerenciar progresso</span>
                        </button>
                    </div>
                    
                    <!-- Timeline de Status -->
                    <div class="border-t border-gray-200 pt-6 lg:pt-8">
                        <h3 class="text-base lg:text-lg font-semibold text-gray-900 mb-4 lg:mb-6 flex items-center">
                            <i class="fas fa-stream mr-2 text-gray-600"></i>
                            Progresso do Pedido
                        </h3>
                        <div class="space-y-3 lg:space-y-4">
                            <?php
                            $status_flow = array(
                                'created' => 'Pedido Criado',
                                'awaiting_payment' => 'Aguardando Pagamento',
                                'paid' => 'Pagamento Confirmado',
                                'thanked' => 'Email de Agradecimento Enviado',
                                'created_assets_text' => 'Textos Personalizados Criados',
                                'created_assets_illustration' => 'Ilustrações Processadas',
                                'created_assets_merge' => 'Assets Finalizados',
                                'ready_for_delivery' => 'Pronto para Entrega',
                                'delivered' => 'Entregue ao Cliente',
                                'completed' => 'Processo Concluído',
                                'canceled' => 'Pedido Cancelado'
                            );
                            
                            $current_index = array_search($order_status, array_keys($status_flow));
                            $i = 0;
                            
                            foreach ($status_flow as $status_key => $status_label) {
                                $is_completed = $i <= $current_index;
                                $is_current = $status_key === $order_status;
                                ?>
                                <div class="flex items-start sm:items-center group">
                                    <div class="flex-shrink-0 relative">
                                        <?php if ($is_completed): ?>
                                            <div class="w-6 h-6 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-md lg:shadow-lg ring-4 ring-green-100">
                                                <i class="fas fa-check text-white text-xs sm:text-sm lg:text-base"></i>
                                            </div>
                                        <?php elseif ($is_current): ?>
                                            <div class="w-6 h-6 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center shadow-md lg:shadow-lg ring-4 ring-blue-100 animate-pulse">
                                                <i class="fas fa-clock text-white text-xs sm:text-sm lg:text-base"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="w-6 h-6 sm:w-8 sm:h-8 lg:w-10 lg:h-10 bg-gray-200 rounded-full ring-4 ring-gray-50"></div>
                                        <?php endif; ?>
                                        
                                        <!-- Linha conectora -->
                                        <?php if ($i < count($status_flow) - 1): ?>
                                            <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0.5 h-3 lg:h-4 <?php echo $is_completed ? 'bg-green-400' : 'bg-gray-200'; ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-3 sm:ml-4 lg:ml-6 flex-1 min-w-0 <?php echo $is_current ? 'bg-blue-50 border border-blue-200' : ($is_completed ? 'bg-white' : 'bg-gray-50'); ?> rounded-lg p-2 lg:p-3 transition-all duration-200 hover:shadow-sm">
                                        <p class="text-xs sm:text-sm lg:text-base font-medium <?php echo $is_completed || $is_current ? 'text-gray-900' : 'text-gray-500'; ?> break-words">
                                            <?php echo $status_label; ?>
                                        </p>
                                        <?php if ($is_current): ?>
                                            <div class="flex items-center mt-1 lg:mt-2">
                                                <span class="inline-flex items-center px-2 py-0.5 lg:px-2.5 lg:py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-1.5 animate-pulse"></span>
                                                    Status Atual
                                                </span>
                                            </div>
                                        <?php elseif ($is_completed): ?>
                                            <p class="text-xs text-green-600 font-medium mt-1 hidden lg:block">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                Concluído
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php $i++; ?>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Modal para Alterar Status -->
                    <div x-show="showStatusModal" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 p-4" style="display: none;" @click.away="showStatusModal = false">
                        <div class="relative top-4 sm:top-20 mx-auto p-4 sm:p-5 border w-full max-w-md sm:w-96 shadow-lg rounded-md bg-white">
                            <div class="mt-0 sm:mt-3">
                                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-4">Alterar Status do Pedido</h3>
                                <form method="post" action="" onsubmit="return confirmStatusChange(document.getElementById('new_status').value)">
                                    <?php wp_nonce_field('update_order_status', 'order_status_nonce'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                    
                                    <div class="mb-4">
                                        <label for="new_status" class="block text-sm font-medium text-gray-700 mb-2">Novo Status:</label>
                                        <select name="new_status" id="new_status" class="w-full px-3 py-2 text-sm sm:text-base border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                            <?php foreach ($status_labels as $status_key => $status_label): ?>
                                                <option value="<?php echo esc_attr($status_key); ?>" <?php selected($order_status, $status_key); ?>>
                                                    <?php echo esc_html($status_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Selecione o novo status para este pedido. Alguns status são irreversíveis.
                                        </p>
                                    </div>
                                    
                                    <div class="flex flex-col-reverse sm:flex-row justify-end gap-2 sm:gap-3 sm:space-x-0">
                                        <button type="button" @click="showStatusModal = false" class="w-full sm:w-auto px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors text-sm sm:text-base">
                                            Cancelar
                                        </button>
                                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm sm:text-base">
                                            <i class="fas fa-save mr-2"></i>
                                            Atualizar Status
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Assets Gerados -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-images mr-2 text-purple-600"></i>
                        Assets Gerados
                    </h2>
                    
                    <!-- PDF Final -->
                    <?php if ($generated_pdf_link || $generated_pdf_attachment): ?>
                        <div class="mb-4 sm:mb-6 p-3 sm:p-4 bg-green-50 border border-green-200 rounded-lg">
                            <h3 class="text-base sm:text-lg font-medium text-green-900 mb-2">PDF Final</h3>
                            
                            <!-- Visualização em iframe -->
                            <?php 
                            $pdf_url_for_iframe = '';
                            if ($generated_pdf_attachment) {
                                if (is_array($generated_pdf_attachment)) {
                                    $pdf_url_for_iframe = $generated_pdf_attachment['url'] ?? '';
                                } elseif (is_numeric($generated_pdf_attachment)) {
                                    $pdf_url_for_iframe = wp_get_attachment_url($generated_pdf_attachment);
                                }
                            } elseif ($generated_pdf_link) {
                                $pdf_url_for_iframe = $generated_pdf_link;
                            }
                            ?>
                            
                            <?php if ($pdf_url_for_iframe): ?>
                                <div class="mb-4">
                                    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="bg-gray-100 px-2 sm:px-4 py-2 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                            <span class="text-xs sm:text-sm font-medium text-gray-700">
                                                <i class="fas fa-eye mr-2"></i>
                                                Visualização do PDF
                                            </span>
                                            <div class="flex items-center gap-2 sm:space-x-2 sm:gap-0">
                                                <button onclick="toggleFullscreen()" class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors">
                                                    <i class="fas fa-expand mr-1"></i>
                                                    <span class="hidden sm:inline">Tela Cheia</span>
                                                    <span class="sm:hidden">Cheia</span>
                                                </button>
                                                <button onclick="reloadPDF()" class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors">
                                                    <i class="fas fa-redo mr-1"></i>
                                                    <span class="hidden sm:inline">Recarregar</span>
                                                    <span class="sm:hidden">Reload</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div id="pdf-viewer-container" class="relative" style="height: 300px;">
                                            <iframe 
                                                id="pdf-viewer"
                                                src="<?php echo esc_url($pdf_url_for_iframe); ?>#toolbar=1&navpanes=1&scrollbar=1" 
                                                class="w-full h-full border-0"
                                                title="Visualização do PDF Final"
                                                loading="lazy">
                                                <p>Seu navegador não suporta iframes. 
                                                <a href="<?php echo esc_url($pdf_url_for_iframe); ?>" target="_blank">Clique aqui para abrir o PDF</a></p>
                                            </iframe>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Botões de ação -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 sm:space-x-4">
                                <?php if ($generated_pdf_link): ?>
                                    <a href="<?php echo esc_url($generated_pdf_link); ?>" target="_blank" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        <span class="hidden sm:inline">Abrir PDF (Link Externo)</span>
                                        <span class="sm:hidden">Abrir PDF</span>
                                    </a>
                                <?php endif; ?>
                                <?php if ($generated_pdf_attachment): ?>
                                    <?php 
                                    $pdf_url = '';
                                    if (is_array($generated_pdf_attachment)) {
                                        $pdf_url = $generated_pdf_attachment['url'] ?? '';
                                    } elseif (is_numeric($generated_pdf_attachment)) {
                                        $pdf_url = wp_get_attachment_url($generated_pdf_attachment);
                                    }
                                    ?>
                                    <?php if ($pdf_url): ?>
                                        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                            <i class="fas fa-file-pdf mr-2"></i>
                                            Visualizar PDF
                                        </a>
                                        <a href="<?php echo esc_url($pdf_url); ?>" download class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors text-sm">
                                            <i class="fas fa-download mr-2"></i>
                                            Download PDF
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Páginas Geradas -->
                    <?php if ($generated_book_pages): ?>
                        <div class="space-y-4">
                            <h3 class="text-base sm:text-lg font-medium text-gray-900">Páginas do Livro</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                <?php foreach ($generated_book_pages as $index => $page): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-medium text-gray-900">Página <?php echo $index + 1; ?></h4>
                                            <?php 
                                            $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
                                            if ($skip_faceswap): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800" title="Esta página não precisa de processamento de API (FaceSwap ou FAL.AI)">
                                                    <i class="fas fa-ban mr-1"></i>
                                                    Sem Processamento
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php 
                                        // Mostrar informações das APIs de processamento
                                        if (!$skip_faceswap): ?>
                                            <div class="mb-3 space-y-2">
                                                <?php 
                                                $has_faceswap = isset($page['faceswap_task_id']) && !empty($page['faceswap_task_id']);
                                                $has_falai = isset($page['falai_task_id']) && !empty($page['falai_task_id']);
                                                $has_illustration = (isset($page['falai_illustration']) && !empty($page['falai_illustration'])) || 
                                                                    (isset($page['faceswap_illustration']) && !empty($page['faceswap_illustration']));
                                                
                                                // FaceSwap
                                                if ($has_faceswap): ?>
                                                    <div class="p-2 bg-blue-50 border border-blue-200 rounded text-xs">
                                                        <p class="text-blue-800 flex items-center">
                                                            <i class="fas fa-sync-alt mr-2"></i>
                                                            <strong>FaceSwap:</strong> 
                                                            <code class="bg-blue-100 px-1 rounded ml-1"><?php echo esc_html($page['faceswap_task_id']); ?></code>
                                                            <?php if ($has_illustration): ?>
                                                                <span class="ml-2 text-green-600">
                                                                    <i class="fas fa-check-circle"></i> Concluído
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="ml-2 text-yellow-600">
                                                                    <i class="fas fa-clock"></i> Processando
                                                                </span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // FAL.AI
                                                if ($has_falai): ?>
                                                    <div class="p-2 bg-purple-50 border border-purple-200 rounded text-xs">
                                                        <p class="text-purple-800 flex items-center">
                                                            <i class="fas fa-magic mr-2"></i>
                                                            <strong>FAL.AI:</strong> 
                                                            <code class="bg-purple-100 px-1 rounded ml-1"><?php echo esc_html($page['falai_task_id']); ?></code>
                                                            <?php if ($has_illustration): ?>
                                                                <span class="ml-2 text-green-600">
                                                                    <i class="fas fa-check-circle"></i> Concluído
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="ml-2 text-yellow-600">
                                                                    <i class="fas fa-clock"></i> Processando
                                                                </span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Se não tem nenhuma API mas também não tem ilustração, está pendente
                                                if (!$has_faceswap && !$has_falai && !$has_illustration): ?>
                                                    <div class="p-2 bg-yellow-50 border border-yellow-200 rounded text-xs">
                                                        <p class="text-yellow-800">
                                                            <i class="fas fa-clock mr-1"></i>
                                                            Processamento pendente (aguardando API)
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Mostrar qual API foi usada se a ilustração já foi gerada
                                                if ($has_illustration && ($has_faceswap || $has_falai)): ?>
                                                    <div class="p-2 bg-gray-50 border border-gray-200 rounded text-xs">
                                                        <p class="text-gray-600">
                                                            <i class="fas fa-info-circle mr-1"></i>
                                                            Processado com: 
                                                            <?php 
                                                            $apis_used = array();
                                                            if ($has_faceswap) $apis_used[] = 'FaceSwap';
                                                            if ($has_falai) $apis_used[] = 'FAL.AI';
                                                            echo esc_html(implode(' + ', $apis_used));
                                                            ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3 p-2 bg-green-50 border border-green-200 rounded text-xs">
                                                <p class="text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Ilustração base usada diretamente (sem processamento de API)
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($page['generated_text_content'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm text-gray-600 mb-1">Texto:</p>
                                                <p class="text-sm bg-gray-50 p-2 rounded"><?php echo wp_trim_words($page['generated_text_content'], 15); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Verificar se há ilustrações específicas de cada API
                                        $has_faceswap_illustration = isset($page['faceswap_illustration']) && !empty($page['faceswap_illustration']);
                                        $has_falai_illustration = isset($page['falai_illustration']) && !empty($page['falai_illustration']);
                                        
                                        // Mostrar ilustrações quando disponíveis
                                        if ($has_faceswap_illustration || $has_falai_illustration): ?>
                                            <div class="mb-3 space-y-3">
                                                <p class="text-sm text-gray-600 mb-2 font-medium">
                                                    Ilustrações Geradas:
                                                    <?php if ($has_faceswap_illustration && $has_falai_illustration): ?>
                                                        <span class="text-xs text-gray-500">(Ambas as APIs processadas)</span>
                                                    <?php elseif ($has_faceswap_illustration): ?>
                                                        <span class="text-xs text-blue-600">(FaceSwap)</span>
                                                    <?php elseif ($has_falai_illustration): ?>
                                                        <span class="text-xs text-purple-600">(FAL.AI)</span>
                                                    <?php endif; ?>
                                                </p>
                                                
                                                <?php 
                                                // Mostrar ilustração do FaceSwap se disponível (sempre que existir)
                                                if ($has_faceswap_illustration): 
                                                    $faceswap_thumb = '';
                                                    $faceswap_full = '';
                                                    if (is_array($page['faceswap_illustration'])) {
                                                        $faceswap_thumb = $page['faceswap_illustration']['sizes']['medium'] ?? $page['faceswap_illustration']['url'] ?? '';
                                                        $faceswap_full = $page['faceswap_illustration']['url'] ?? '';
                                                    } elseif (is_numeric($page['faceswap_illustration'])) {
                                                        $faceswap_thumb = wp_get_attachment_image_url($page['faceswap_illustration'], 'medium');
                                                        $faceswap_full = wp_get_attachment_image_url($page['faceswap_illustration'], 'full');
                                                    }
                                                ?>
                                                    <div class="border border-blue-200 rounded-lg p-3 bg-blue-50">
                                                        <p class="text-xs font-medium text-blue-800 mb-2 flex items-center">
                                                            <i class="fas fa-sync-alt mr-2"></i>
                                                            Ilustração FaceSwap
                                                        </p>
                                                        <?php if ($faceswap_thumb): ?>
                                                            <a href="<?php echo esc_url($faceswap_full ?: $faceswap_thumb); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                                <img src="<?php echo esc_url($faceswap_thumb); ?>" 
                                                                     alt="Ilustração FaceSwap - Página <?php echo $index + 1; ?>" 
                                                                     class="clickable-image w-full h-32 object-cover rounded border-2 border-blue-300 hover:border-blue-400">
                                                            </a>
                                                            <p class="text-xs text-blue-600 mt-1">
                                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                                Clique para ver em tamanho original
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Mostrar ilustração do FAL.AI se disponível
                                                if ($has_falai_illustration): 
                                                    $falai_thumb = '';
                                                    $falai_full = '';
                                                    if (is_array($page['falai_illustration'])) {
                                                        $falai_thumb = $page['falai_illustration']['sizes']['medium'] ?? $page['falai_illustration']['url'] ?? '';
                                                        $falai_full = $page['falai_illustration']['url'] ?? '';
                                                    } elseif (is_numeric($page['falai_illustration'])) {
                                                        $falai_thumb = wp_get_attachment_image_url($page['falai_illustration'], 'medium');
                                                        $falai_full = wp_get_attachment_image_url($page['falai_illustration'], 'full');
                                                    }
                                                ?>
                                                    <div class="border border-purple-200 rounded-lg p-3 bg-purple-50">
                                                        <p class="text-xs font-medium text-purple-800 mb-2 flex items-center">
                                                            <i class="fas fa-magic mr-2"></i>
                                                            Ilustração FAL.AI
                                                        </p>
                                                        <?php if ($falai_thumb): ?>
                                                            <a href="<?php echo esc_url($falai_full ?: $falai_thumb); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                                <img src="<?php echo esc_url($falai_thumb); ?>" 
                                                                     alt="Ilustração FAL.AI - Página <?php echo $index + 1; ?>" 
                                                                     class="clickable-image w-full h-32 object-cover rounded border-2 border-purple-300 hover:border-purple-400">
                                                            </a>
                                                            <p class="text-xs text-purple-600 mt-1">
                                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                                Clique para ver em tamanho original
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($page['final_page_with_text']) && $page['final_page_with_text']): ?>
                                            <div>
                                                <p class="text-sm text-gray-600 mb-1">Página Final:</p>
                                                <?php 
                                                $final_page_url = '';
                                                if (is_array($page['final_page_with_text'])) {
                                                    $final_page_url = $page['final_page_with_text']['sizes']['medium'] ?? $page['final_page_with_text']['url'] ?? '';
                                                } elseif (is_numeric($page['final_page_with_text'])) {
                                                    $final_page_url = wp_get_attachment_image_url($page['final_page_with_text'], 'medium');
                                                }
                                                ?>
                                                <?php if ($final_page_url): ?>
                                                    <?php 
                                                    // Obter URL em tamanho full
                                                    $full_final_page_url = '';
                                                    if (is_array($page['final_page_with_text'])) {
                                                        $full_final_page_url = $page['final_page_with_text']['url'] ?? '';
                                                    } elseif (is_numeric($page['final_page_with_text'])) {
                                                        $full_final_page_url = wp_get_attachment_image_url($page['final_page_with_text'], 'full');
                                                    }
                                                    ?>
                                                    <a href="<?php echo esc_url($full_final_page_url ?: $final_page_url); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                        <img src="<?php echo esc_url($final_page_url); ?>" 
                                                             alt="Página Final <?php echo $index + 1; ?>" 
                                                             class="clickable-image w-full h-32 object-cover rounded border hover:border-green-300">
                                                    </a>
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        <i class="fas fa-external-link-alt mr-1"></i>
                                                        Clique para ver em tamanho original
                                                    </p>
                                                <?php else: ?>
                                                    <div class="w-full h-32 bg-gray-100 rounded border flex items-center justify-center">
                                                        <span class="text-gray-400 text-sm">Página final não disponível</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-image text-4xl mb-4"></i>
                            <p>Nenhum asset foi gerado ainda para este pedido.</p>
                        </div>
                    <?php endif; ?>
                </div>

                                <!-- Logs do Pedido -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-history mr-2 text-gray-600"></i>
                        Histórico do Pedido
                    </h2>
                    
                    <?php
                    // Buscar logs do post_content (sistema atual)
                    $post_content = get_post_field('post_content', $order_id);
                    $logs = array();
                    
                    if (!empty($post_content)) {
                        // Extrair logs do post_content
                        $log_entries = explode('--- POST LOGGER ---', $post_content);
                        
                        foreach ($log_entries as $entry) {
                            if (empty(trim($entry))) continue;
                            
                            $log_data = array();
                            $lines = explode("\n", trim($entry));
                            
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line) || strpos($line, '------------------------') !== false) continue;
                                
                                if (strpos($line, 'Data/Hora:') === 0) {
                                    $log_data['timestamp'] = trim(str_replace('Data/Hora:', '', $line));
                                } elseif (strpos($line, 'API/Função:') === 0) {
                                    $log_data['source'] = trim(str_replace('API/Função:', '', $line));
                                } elseif (strpos($line, 'Status:') === 0) {
                                    $status_line = trim(str_replace('Status:', '', $line));
                                    if (strpos($status_line, '->') !== false) {
                                        $parts = explode('->', $status_line);
                                        $log_data['old_status'] = trim($parts[0]);
                                        $log_data['new_status'] = trim($parts[1]);
                                        $log_data['log_type'] = trim($parts[1]);
                                    } else {
                                        $log_data['log_type'] = trim($status_line);
                                    }
                                } elseif (strpos($line, 'Mensagem:') === 0) {
                                    $log_data['message'] = trim(str_replace('Mensagem:', '', $line));
                                }
                            }
                            
                            if (!empty($log_data['message'])) {
                                $logs[] = $log_data;
                            }
                        }
                    }
                    
                    // Fallback: tentar buscar de meta field também
                    if (empty($logs)) {
                        $meta_logs = get_post_meta($order_id, '_order_logs', true);
                        if (!empty($meta_logs) && is_array($meta_logs)) {
                            $logs = array_reverse($meta_logs);
                        }
                    }
                    ?>
                    
                    <?php if (!empty($logs)): ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $log_type = $log['log_type'] ?? $log['status'] ?? 'info';
                                $icon_class = 'fas fa-info text-blue-600';
                                $bg_class = 'bg-blue-50';
                                
                                switch ($log_type) {
                                    case 'created':
                                        $icon_class = 'fas fa-plus text-green-600';
                                        $bg_class = 'bg-green-50';
                                        break;
                                    case 'paid':
                                        $icon_class = 'fas fa-credit-card text-green-600';
                                        $bg_class = 'bg-green-50';
                                        break;
                                    case 'error':
                                        $icon_class = 'fas fa-exclamation-triangle text-red-600';
                                        $bg_class = 'bg-red-50';
                                        break;
                                    case 'warning':
                                        $icon_class = 'fas fa-exclamation text-yellow-600';
                                        $bg_class = 'bg-yellow-50';
                                        break;
                                }
                                ?>
                                <div class="flex items-start space-x-3 p-3 <?php echo $bg_class; ?> rounded-lg border">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-sm">
                                            <i class="<?php echo $icon_class; ?> text-sm"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-900"><?php echo esc_html($log['message'] ?? $log['description'] ?? 'Log sem mensagem'); ?></p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?php 
                                            $timestamp = $log['created_at'] ?? $log['timestamp'] ?? null;
                                            if ($timestamp) {
                                                echo esc_html(date('d/m/Y H:i:s', strtotime($timestamp)));
                                            } else {
                                                echo 'Data não disponível';
                                            }
                                            ?>
                                            <?php if (!empty($log['source']) || !empty($log['action'])): ?>
                                                • Origem: <?php echo esc_html($log['source'] ?? $log['action']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-clock text-4xl mb-4 opacity-50"></i>
                            <p>Nenhum log encontrado para este pedido.</p>
                            <p class="text-xs mt-2">Os logs aparecerão aqui conforme as ações forem executadas.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4 sm:space-y-6 order-1 lg:order-2">
                
                <!-- Informações da Criança -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-child mr-2 text-pink-600"></i>
                        Dados da Criança
                    </h2>
                    
                    <?php if ($child_face_photo): ?>
                        <div class="mb-4 text-center">
                            <?php 
                            // Tratar tanto ID quanto array do ACF
                            $photo_url = '';
                            if (is_array($child_face_photo)) {
                                $photo_url = $child_face_photo['sizes']['medium'] ?? $child_face_photo['url'] ?? '';
                            } elseif (is_numeric($child_face_photo)) {
                                $photo_url = wp_get_attachment_image_url($child_face_photo, 'medium');
                            }
                            ?>
                            <?php if ($photo_url): ?>
                                <?php 
                                // Obter URL em tamanho full
                                $full_photo_url = '';
                                if (is_array($child_face_photo)) {
                                    $full_photo_url = $child_face_photo['url'] ?? '';
                                } elseif (is_numeric($child_face_photo)) {
                                    $full_photo_url = wp_get_attachment_image_url($child_face_photo, 'full');
                                }
                                ?>
                                <a href="<?php echo esc_url($full_photo_url ?: $photo_url); ?>" target="_blank" class="inline-block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                    <img src="<?php echo esc_url($photo_url); ?>" 
                                         alt="<?php echo esc_attr($child_name ?: 'Foto da criança'); ?>" 
                                         class="clickable-image w-32 h-32 object-cover rounded-full mx-auto border-4 border-pink-200 shadow-lg hover:border-pink-300"
                                         loading="lazy">
                                </a>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-external-link-alt mr-1"></i>
                                    Clique para ver em tamanho original
                                </p>
                            <?php else: ?>
                                <div class="w-32 h-32 bg-gray-200 rounded-full mx-auto border-4 border-pink-200 shadow-lg flex items-center justify-center">
                                    <i class="fas fa-user text-gray-400 text-3xl"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Foto não disponível</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-4 text-center">
                            <div class="w-32 h-32 bg-gray-200 rounded-full mx-auto border-4 border-pink-200 shadow-lg flex items-center justify-center">
                                <i class="fas fa-user text-gray-400 text-3xl"></i>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Nenhuma foto enviada</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Nome:</label>
                            <p class="text-gray-900"><?php echo esc_html($child_name ?: 'Não informado'); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Idade:</label>
                            <p class="text-gray-900"><?php echo esc_html($child_age ? $child_age . ' anos' : 'Não informada'); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Gênero:</label>
                            <p class="text-gray-900">
                                <?php 
                                if ($child_gender === 'boy') {
                                    echo 'Menino';
                                } elseif ($child_gender === 'girl') {
                                    echo 'Menina';
                                } else {
                                    echo 'Não informado';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Tom de Pele:</label>
                            <p class="text-gray-900">
                                <?php 
                                if ($child_skin_tone === 'light') {
                                    echo 'Claro';
                                } elseif ($child_skin_tone === 'dark') {
                                    echo 'Escuro';
                                } else {
                                    echo 'Não informado';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Informações do Comprador -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-user mr-2 text-blue-600"></i>
                        Dados do Comprador
                    </h2>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Nome:</label>
                            <p class="text-gray-900"><?php echo esc_html($buyer_name ?: 'Não informado'); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Email:</label>
                            <p class="text-gray-900">
                                <?php if ($buyer_email): ?>
                                    <a href="mailto:<?php echo esc_attr($buyer_email); ?>" class="text-blue-600 hover:text-blue-800 transition-colors">
                                        <?php echo esc_html($buyer_email); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">Não informado</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">Telefone:</label>
                            <p class="text-gray-900 flex items-center gap-2 flex-wrap">
                                <?php if ($buyer_phone): ?>
                                    <a href="tel:<?php echo esc_attr($buyer_phone); ?>" class="text-blue-600 hover:text-blue-800 transition-colors break-all">
                                        <?php echo esc_html($buyer_phone); ?>
                                    </a>
                                    <?php 
                                    // Limpar número para WhatsApp (remover caracteres especiais)
                                    $whatsapp_number = preg_replace('/[^0-9]/', '', $buyer_phone);
                                    // Se começar com 0, remover o 0
                                    if (substr($whatsapp_number, 0, 1) === '0') {
                                        $whatsapp_number = substr($whatsapp_number, 1);
                                    }
                                    // Se não começar com 55 (código do Brasil), adicionar
                                    if (!str_starts_with($whatsapp_number, '55')) {
                                        $whatsapp_number = '55' . $whatsapp_number;
                                    }
                                    
                                    $whatsapp_message = urlencode("Olá! Entrando em contato sobre o pedido #{$order_id} da criança {$child_name}.");
                                    $whatsapp_url = "https://wa.me/{$whatsapp_number}?text={$whatsapp_message}";
                                    ?>
                                    <a href="<?php echo esc_url($whatsapp_url); ?>" 
                                       target="_blank" 
                                       class="inline-flex items-center justify-center w-8 h-8 bg-green-500 hover:bg-green-600 text-white rounded-full transition-colors" 
                                       title="Abrir WhatsApp com <?php echo esc_attr($buyer_name ?: 'cliente'); ?>">
                                        <i class="fab fa-whatsapp text-sm"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">Não informado</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Informações de Pagamento -->
                <?php if ($payment_transaction_id || $payment_date || $payment_amount): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                            <i class="fas fa-credit-card mr-2 text-green-600"></i>
                            Pagamento
                        </h2>
                        
                        <div class="space-y-3">
                            <?php if ($payment_transaction_id): ?>
                                <div>
                                    <label class="text-sm font-medium text-gray-600">ID da Transação:</label>
                                    <p class="text-gray-900 font-mono text-sm"><?php echo $payment_transaction_id; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment_date): ?>
                                <div>
                                    <label class="text-sm font-medium text-gray-600">Data do Pagamento:</label>
                                    <p class="text-gray-900"><?php echo date('d/m/Y H:i:s', strtotime($payment_date)); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($payment_amount): ?>
                                <div>
                                    <label class="text-sm font-medium text-gray-600">Valor Pago:</label>
                                    <p class="text-gray-900 text-lg font-semibold text-green-600">
                                        R$ <?php echo number_format($payment_amount, 2, ',', '.'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($applied_coupon): ?>
                                <div>
                                    <label class="text-sm font-medium text-gray-600">Cupom Aplicado:</label>
                                    <p class="text-gray-900">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <?php echo $applied_coupon; ?>
                                        </span>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Modelo de Livro -->
                <?php if ($book_template): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">
                            <i class="fas fa-book mr-2 text-indigo-600"></i>
                            Modelo de Livro
                        </h2>
                        
                        <div class="mb-4">
                            <p class="text-gray-900 font-medium break-words"><?php echo $book_template->post_title; ?></p>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1">ID: <?php echo $book_template->ID; ?></p>
                            <a href="<?php echo get_edit_post_link($book_template->ID); ?>" class="inline-flex items-center mt-2 text-xs sm:text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                Ver Modelo
                            </a>
                        </div>

                        <!-- Páginas do Template Original -->
                        <?php 
                        $template_pages = get_field('template_book_pages', $book_template->ID);
                        
                        if ($template_pages): ?>
                            <div class="border-t border-gray-200 pt-4">
                                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3">
                                    <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                                    Páginas do Template Original
                                </h3>
                                
                                <div class="space-y-3 sm:space-y-4">
                                    <?php foreach ($template_pages as $index => $page): ?>
                                        <div class="border border-gray-200 rounded-lg p-3 sm:p-4 bg-gray-50">
                                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-3">
                                                <h4 class="font-medium text-gray-900 flex items-center text-sm sm:text-base">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 sm:w-6 sm:h-6 bg-indigo-100 text-indigo-800 text-xs font-medium rounded-full mr-2">
                                                        <?php echo $index + 1; ?>
                                                    </span>
                                                    Página <?php echo $index + 1; ?>
                                                    <?php if ($child_gender === 'boy'): ?>
                                                        <i class="fas fa-mars text-blue-600 ml-2" title="Menino"></i>
                                                    <?php elseif ($child_gender === 'girl'): ?>
                                                        <i class="fas fa-venus text-pink-600 ml-2" title="Menina"></i>
                                                    <?php endif; ?>
                                                </h4>
                                                <?php 
                                                $template_skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
                                                if ($template_skip_faceswap): ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 self-start sm:self-auto" title="Esta página do template não precisa de processamento de API (FaceSwap ou FAL.AI)">
                                                        <i class="fas fa-ban mr-1"></i>
                                                        Sem Processamento
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 self-start sm:self-auto" title="Esta página será processada com FaceSwap ou FAL.AI">
                                                        <i class="fas fa-magic mr-1"></i>
                                                        Com Processamento
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Texto Original Correspondente -->
                                            <?php 
                                            $corresponding_text = '';
                                            $text_color = '';
                                            $text_icon = '';
                                            $text_label = '';
                                            
                                            if ($child_gender === 'boy' && !empty($page['base_text_content_boy'])) {
                                                $corresponding_text = $page['base_text_content_boy'];
                                                $text_color = 'blue';
                                                $text_icon = 'fas fa-mars';
                                                $text_label = 'Texto Original (Menino)';
                                            } elseif ($child_gender === 'girl' && !empty($page['base_text_content_girl'])) {
                                                $corresponding_text = $page['base_text_content_girl'];
                                                $text_color = 'pink';
                                                $text_icon = 'fas fa-venus';
                                                $text_label = 'Texto Original (Menina)';
                                            }
                                            
                                            if ($corresponding_text): ?>
                                                <div class="mb-4">
                                                    <h5 class="text-sm font-medium text-gray-700 mb-2">
                                                        <i class="fas fa-font mr-1"></i>
                                                        Texto Original:
                                                    </h5>
                                                    <div class="bg-<?php echo $text_color; ?>-50 border border-<?php echo $text_color; ?>-200 rounded p-3">
                                                        <div class="flex items-center mb-2">
                                                            <i class="<?php echo $text_icon; ?> text-<?php echo $text_color; ?>-600 mr-2"></i>
                                                            <span class="text-sm font-medium text-<?php echo $text_color; ?>-800"><?php echo $text_label; ?>:</span>
                                                        </div>
                                                        <div class="text-sm text-gray-700 bg-white p-2 rounded border">
                                                            <?php echo wp_strip_all_tags($corresponding_text); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Ilustração Correspondente -->
                                            <?php 
                                            $base_illustrations = $page['base_illustrations'] ?? null;
                                            $matching_illustration = null;
                                            
                                            // Encontrar a ilustração que corresponde ao pedido
                                            if ($base_illustrations && is_array($base_illustrations)) {
                                                foreach ($base_illustrations as $illustration_data) {
                                                    if (isset($illustration_data['illustration_asset']) && $illustration_data['illustration_asset']) {
                                                        $gender = $illustration_data['gender'] ?? '';
                                                        $skin_tone = $illustration_data['skin_tone'] ?? '';
                                                        
                                                        if ($gender === $child_gender && $skin_tone === $child_skin_tone) {
                                                            $matching_illustration = $illustration_data;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if ($matching_illustration): ?>
                                                <div>
                                                    <h5 class="text-sm font-medium text-gray-700 mb-2">
                                                        <i class="fas fa-image mr-1"></i>
                                                        Ilustração Original:
                                                    </h5>
                                                    <?php 
                                                    $illustration = $matching_illustration['illustration_asset'];
                                                    $gender = $matching_illustration['gender'] ?? '';
                                                    $skin_tone = $matching_illustration['skin_tone'] ?? '';
                                                    
                                                    $thumb_url = '';
                                                    $full_url = '';
                                                    if (is_array($illustration)) {
                                                        $thumb_url = $illustration['sizes']['medium'] ?? $illustration['url'] ?? '';
                                                        $full_url = $illustration['url'] ?? '';
                                                    } elseif (is_numeric($illustration)) {
                                                        $thumb_url = wp_get_attachment_image_url($illustration, 'medium');
                                                        $full_url = wp_get_attachment_image_url($illustration, 'full');
                                                    }
                                                    
                                                    // Labels para gênero e tom de pele
                                                    $gender_label = $gender === 'boy' ? 'Menino' : ($gender === 'girl' ? 'Menina' : '');
                                                    $skin_tone_label = $skin_tone === 'light' ? 'Claro' : ($skin_tone === 'dark' ? 'Escuro' : '');
                                                    
                                                    // Cores baseadas no gênero
                                                    $color_scheme = $gender === 'boy' ? 'blue' : 'pink';
                                                    ?>
                                                    <?php if ($thumb_url): ?>
                                                        <div class="bg-<?php echo $color_scheme; ?>-50 border border-<?php echo $color_scheme; ?>-200 rounded-lg p-4">
                                                            <div class="flex items-center justify-center">
                                                                <a href="<?php echo esc_url($full_url ?: $thumb_url); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                                    <img src="<?php echo esc_url($thumb_url); ?>" 
                                                                         alt="<?php echo esc_attr($gender_label . ' ' . $skin_tone_label); ?>" 
                                                                         class="w-32 h-32 object-cover rounded-lg border-2 border-<?php echo $color_scheme; ?>-300 hover:border-<?php echo $color_scheme; ?>-400 shadow-md"
                                                                         loading="lazy">
                                                                </a>
                                                            </div>
                                                            <div class="mt-3 text-center">
                                                                <p class="text-sm font-medium text-<?php echo $color_scheme; ?>-800">
                                                                    <?php if ($gender === 'boy'): ?>
                                                                        <i class="fas fa-mars mr-1"></i>
                                                                    <?php else: ?>
                                                                        <i class="fas fa-venus mr-1"></i>
                                                                    <?php endif; ?>
                                                                    <?php echo esc_html($gender_label . ' - Tom ' . $skin_tone_label); ?>
                                                                </p>
                                                                <p class="text-xs text-gray-500 mt-1">
                                                                    <i class="fas fa-external-link-alt mr-1"></i>
                                                                    Clique para ampliar
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Informações Adicionais -->
                                            <?php if (!empty($page['page_number'])): ?>
                                                <div class="mt-3 pt-3 border-t border-gray-200">
                                                    <p class="text-xs text-gray-500">
                                                        <i class="fas fa-info-circle mr-1"></i>
                                                        Número da página: <?php echo esc_html($page['page_number']); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="border-t border-gray-200 pt-4">
                                <div class="text-center py-4 text-gray-500">
                                    <i class="fas fa-file-alt text-2xl mb-2 opacity-50"></i>
                                    <p class="text-sm">Nenhuma página encontrada no template.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Função para abrir todas as páginas finais (com texto) em abas novas
        function openAllImages() {
            const imageUrls = [];
            
            // Coletar apenas as páginas finais com texto
            <?php if ($generated_book_pages): ?>
                <?php foreach ($generated_book_pages as $index => $page): ?>
                    <?php if (isset($page['final_page_with_text']) && $page['final_page_with_text']): ?>
                        <?php 
                        $final_page_url_full = '';
                        if (is_array($page['final_page_with_text'])) {
                            $final_page_url_full = $page['final_page_with_text']['url'] ?? '';
                        } elseif (is_numeric($page['final_page_with_text'])) {
                            $final_page_url_full = wp_get_attachment_image_url($page['final_page_with_text'], 'full');
                        }
                        ?>
                        <?php if ($final_page_url_full): ?>
                            imageUrls.push({
                                url: '<?php echo esc_js($final_page_url_full); ?>',
                                title: 'Página <?php echo $index + 1; ?> - Final com Texto'
                            });
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            
            // Verificar se há páginas finais para abrir
            if (imageUrls.length === 0) {
                alert('❌ Nenhuma página final disponível ainda para este pedido.');
                return;
            }
            
            // Confirmar ação
            const confirmed = confirm(`📄 Serão abertas ${imageUrls.length} página(s) final(is) em novas abas.\n\nCertifique-se de permitir pop-ups para este site.\n\nDeseja continuar?`);
            
            if (!confirmed) {
                return;
            }
            
            // Abrir cada página final em uma nova aba
            let openedCount = 0;
            imageUrls.forEach((image, index) => {
                // Pequeno delay entre aberturas para evitar bloqueio do navegador
                setTimeout(() => {
                    const newWindow = window.open(image.url, '_blank');
                    if (newWindow) {
                        newWindow.document.title = image.title;
                        openedCount++;
                    }
                    
                    // Mostrar mensagem final após abrir todas
                    if (index === imageUrls.length - 1) {
                        setTimeout(() => {
                            if (openedCount < imageUrls.length) {
                                alert(`⚠️ ${openedCount} de ${imageUrls.length} página(s) foram abertas.\n\nAlgumas podem ter sido bloqueadas pelo navegador.\nPermita pop-ups para este site e tente novamente.`);
                            }
                        }, 500);
                    }
                }, index * 100);
            });
        }
        
        // Função para confirmar mudanças de status críticas
        function confirmStatusChange(newStatus) {
            const criticalStatuses = ['error', 'delivered', 'completed', 'canceled'];
            if (criticalStatuses.includes(newStatus)) {
                return confirm('Tem certeza que deseja alterar para este status? Esta ação pode ser irreversível.');
            }
            return true;
        }
        
        // Auto-refresh da página se houver mudanças no status (polling)
        let lastStatus = '<?php echo esc_js($order_status); ?>';
        function checkStatusUpdates() {
            fetch('<?php echo esc_url(admin_url("admin-ajax.php")); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_order_status&order_id=<?php echo $order_id; ?>&nonce=<?php echo wp_create_nonce("check_order_status"); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.status !== lastStatus) {
                    location.reload();
                }
            })
            .catch(error => console.log('Status check error:', error));
        }
        
        // Verificar atualizações a cada 30 segundos
        setInterval(checkStatusUpdates, 30000);

        // Funções para controlar o iframe do PDF
        function toggleFullscreen() {
            const iframe = document.getElementById('pdf-viewer');
            if (iframe.requestFullscreen) {
                iframe.requestFullscreen();
            } else if (iframe.mozRequestFullScreen) {
                iframe.mozRequestFullScreen();
            } else if (iframe.webkitRequestFullscreen) {
                iframe.webkitRequestFullscreen();
            } else if (iframe.msRequestFullscreen) {
                iframe.msRequestFullscreen();
            }
        }

        function reloadPDF() {
            const iframe = document.getElementById('pdf-viewer');
            iframe.src = iframe.src; // Recarrega o conteúdo do iframe
        }

        // Função para entregar pedido individual
        async function deliverOrder(element) {
            const orderId = <?php echo $order_id; ?>;
            const apiKey = '<?php echo esc_js(get_option('trinitykitcms_api_key')); ?>';
            
            // Encontrar o componente Alpine.js mais próximo
            let alpineComponent = null;
            try {
                // Tentar acessar o Alpine através do elemento
                let currentElement = element;
                while (currentElement && !alpineComponent) {
                    if (currentElement._x_dataStack && currentElement._x_dataStack.length > 0) {
                        alpineComponent = currentElement._x_dataStack[0];
                        break;
                    }
                    currentElement = currentElement.parentElement;
                }
            } catch (e) {
                console.warn('Não foi possível acessar o Alpine.js:', e);
            }
            
            try {
                const response = await fetch(`<?php echo home_url('/wp-json/trinitykitcms-api/v1/orders/'); ?>${orderId}/deliver`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey
                    }
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    // Sucesso - mostrar mensagem e recarregar página
                    alert('✅ Pedido entregue com sucesso!\n\n' + 
                          'Email enviado para: ' + data.buyer_email + '\n' +
                          'Cliente: ' + data.buyer_name + '\n' +
                          'Criança: ' + data.child_name);
                    location.reload();
                } else {
                    // Erro - mostrar mensagem de erro
                    let errorMessage = '❌ Erro ao entregar pedido:\n\n';
                    
                    if (data.code === 'invalid_status') {
                        errorMessage += 'Status inválido. O pedido precisa estar no status "Pronto para Entrega".\n';
                        errorMessage += 'Status atual: ' + data.current_status;
                    } else if (data.code === 'already_delivered') {
                        errorMessage += 'Este pedido já foi entregue anteriormente.';
                    } else if (data.code === 'missing_data') {
                        errorMessage += 'Dados obrigatórios ausentes:\n';
                        if (data.missing.buyer_name) errorMessage += '• Nome do comprador\n';
                        if (data.missing.buyer_email) errorMessage += '• Email do comprador\n';
                        if (data.missing.pdf_url) errorMessage += '• Link do PDF\n';
                    } else if (data.code === 'invalid_email') {
                        errorMessage += 'Email inválido: ' + data.message;
                    } else if (data.code === 'email_send_failed') {
                        errorMessage += 'Falha ao enviar email de entrega.\n';
                        if (data.error_details) {
                            errorMessage += 'Detalhes: ' + data.error_details;
                        }
                    } else {
                        errorMessage += data.message || 'Erro desconhecido';
                    }
                    
                    alert(errorMessage);
                    
                    // Resetar estado se o Alpine estiver disponível
                    if (alpineComponent) {
                        alpineComponent.isDelivering = false;
                    }
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                alert('❌ Erro ao conectar com o servidor:\n\n' + error.message);
                
                // Resetar estado se o Alpine estiver disponível
                if (alpineComponent) {
                    alpineComponent.isDelivering = false;
                }
            }
        }
    </script>
</body>
</html>
