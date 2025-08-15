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
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    
    <?php if (isset($_GET['status_updated'])): ?>
        <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)">
            <i class="fas fa-check mr-2"></i>
            Status atualizado com sucesso!
        </div>
    <?php endif; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Pedido #<?php echo esc_html($order_id); ?></h1>
                    <p class="text-gray-600 mt-1">
                        <?php echo esc_html($buyer_name ?: 'Comprador não informado'); ?> - <?php echo esc_html($child_name ?: 'Criança não informada'); ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="status-badge <?php echo esc_attr($status_colors[$order_status] ?? 'bg-gray-100 text-gray-800'); ?>">
                        <?php echo esc_html($status_labels[$order_status] ?? $order_status); ?>
                    </span>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=orders')); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Voltar para Lista
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Coluna Principal -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Gerenciamento de Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" x-data="{ showStatusModal: false }">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-semibold text-gray-900">
                            <i class="fas fa-tasks mr-2 text-blue-600"></i>
                            Gerenciamento de Status
                        </h2>
                        <button @click="showStatusModal = true" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-edit mr-2"></i>
                            Alterar Status
                        </button>
                    </div>
                    
                    <!-- Timeline de Status -->
                    <div class="space-y-4">
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
                            'completed' => 'Processo Concluído'
                        );
                        
                        $current_index = array_search($order_status, array_keys($status_flow));
                        $i = 0;
                        
                        foreach ($status_flow as $status_key => $status_label) {
                            $is_completed = $i <= $current_index;
                            $is_current = $status_key === $order_status;
                            ?>
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <?php if ($is_completed): ?>
                                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                            <i class="fas fa-check text-white text-sm"></i>
                                        </div>
                                    <?php elseif ($is_current): ?>
                                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                            <i class="fas fa-clock text-white text-sm"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-gray-300 rounded-full"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium <?php echo $is_completed || $is_current ? 'text-gray-900' : 'text-gray-500'; ?>">
                                        <?php echo $status_label; ?>
                                    </p>
                                    <?php if ($is_current): ?>
                                        <p class="text-xs text-blue-600 font-medium">Status Atual</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $i++; ?>
                        <?php } ?>
                    </div>

                    <!-- Modal para Alterar Status -->
                    <div x-show="showStatusModal" x-cloak class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" style="display: none;">
                        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                            <div class="mt-3">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Alterar Status do Pedido</h3>
                                <form method="post" action="" onsubmit="return confirmStatusChange(document.getElementById('new_status').value)">
                                    <?php wp_nonce_field('update_order_status', 'order_status_nonce'); ?>
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                    
                                    <div class="mb-4">
                                        <label for="new_status" class="block text-sm font-medium text-gray-700 mb-2">Novo Status:</label>
                                        <select name="new_status" id="new_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
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
                                    
                                    <div class="flex justify-end space-x-3">
                                        <button type="button" @click="showStatusModal = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                            Cancelar
                                        </button>
                                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
                        <i class="fas fa-images mr-2 text-purple-600"></i>
                        Assets Gerados
                    </h2>
                    
                    <!-- PDF Final -->
                    <?php if ($generated_pdf_link || $generated_pdf_attachment): ?>
                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <h3 class="text-lg font-medium text-green-900 mb-2">PDF Final</h3>
                            <div class="flex items-center space-x-4 flex-wrap gap-2">
                                <?php if ($generated_pdf_link): ?>
                                    <a href="<?php echo esc_url($generated_pdf_link); ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        Abrir PDF (Link Externo)
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
                                        <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                                            <i class="fas fa-file-pdf mr-2"></i>
                                            Visualizar PDF
                                        </a>
                                        <a href="<?php echo esc_url($pdf_url); ?>" download class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
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
                            <h3 class="text-lg font-medium text-gray-900">Páginas do Livro</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($generated_book_pages as $index => $page): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <h4 class="font-medium text-gray-900 mb-2">Página <?php echo $index + 1; ?></h4>
                                        
                                        <?php if (isset($page['generated_text_content'])): ?>
                                            <div class="mb-3">
                                                <p class="text-sm text-gray-600 mb-1">Texto:</p>
                                                <p class="text-sm bg-gray-50 p-2 rounded"><?php echo wp_trim_words($page['generated_text_content'], 15); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($page['generated_illustration']) && $page['generated_illustration']): ?>
                                            <div class="mb-3">
                                                <p class="text-sm text-gray-600 mb-1">Ilustração:</p>
                                                <?php 
                                                $illustration_url = '';
                                                if (is_array($page['generated_illustration'])) {
                                                    $illustration_url = $page['generated_illustration']['sizes']['medium'] ?? $page['generated_illustration']['url'] ?? '';
                                                } elseif (is_numeric($page['generated_illustration'])) {
                                                    $illustration_url = wp_get_attachment_image_url($page['generated_illustration'], 'medium');
                                                }
                                                ?>
                                                <?php if ($illustration_url): ?>
                                                    <?php 
                                                    // Obter URL em tamanho full
                                                    $full_illustration_url = '';
                                                    if (is_array($page['generated_illustration'])) {
                                                        $full_illustration_url = $page['generated_illustration']['url'] ?? '';
                                                    } elseif (is_numeric($page['generated_illustration'])) {
                                                        $full_illustration_url = wp_get_attachment_image_url($page['generated_illustration'], 'full');
                                                    }
                                                    ?>
                                                    <a href="<?php echo esc_url($full_illustration_url ?: $illustration_url); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                        <img src="<?php echo esc_url($illustration_url); ?>" 
                                                             alt="Ilustração da Página <?php echo $index + 1; ?>" 
                                                             class="clickable-image w-full h-32 object-cover rounded border hover:border-purple-300">
                                                    </a>
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        <i class="fas fa-external-link-alt mr-1"></i>
                                                        Clique para ver em tamanho original
                                                    </p>
                                                <?php else: ?>
                                                    <div class="w-full h-32 bg-gray-100 rounded border flex items-center justify-center">
                                                        <span class="text-gray-400 text-sm">Ilustração não disponível</span>
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
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
                    
                    <!-- Debug info (remover em produção) -->
                    <?php if (current_user_can('manage_options')): ?>
                        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-xs">
                            <strong>Debug Info:</strong><br>
                            Post Content Length: <?php echo strlen($post_content); ?><br>
                            Logs Found: <?php echo count($logs); ?><br>
                            <?php if (!empty($post_content)): ?>
                                <details class="mt-2">
                                    <summary>Ver Post Content (primeiros 500 chars)</summary>
                                    <pre class="mt-2 text-xs bg-gray-100 p-2 rounded overflow-x-auto"><?php echo esc_html(substr($post_content, 0, 500)); ?></pre>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
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
            <div class="space-y-6">
                
                <!-- Informações da Criança -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
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
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">
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
                            <p class="text-gray-900 flex items-center gap-2">
                                <?php if ($buyer_phone): ?>
                                    <a href="tel:<?php echo esc_attr($buyer_phone); ?>" class="text-blue-600 hover:text-blue-800 transition-colors">
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
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
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
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            <i class="fas fa-book mr-2 text-indigo-600"></i>
                            Modelo de Livro
                        </h2>
                        
                        <div>
                            <p class="text-gray-900 font-medium"><?php echo $book_template->post_title; ?></p>
                            <p class="text-sm text-gray-600 mt-1">ID: <?php echo $book_template->ID; ?></p>
                            <a href="<?php echo get_edit_post_link($book_template->ID); ?>" class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800">
                                <i class="fas fa-external-link-alt mr-1"></i>
                                Ver Modelo
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Função para confirmar mudanças de status críticas
        function confirmStatusChange(newStatus) {
            const criticalStatuses = ['error', 'delivered', 'completed'];
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
    </script>
</body>
</html>
