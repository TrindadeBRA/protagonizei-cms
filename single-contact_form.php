<?php
/**
 * Template para exibir um contato individual
 * 
 * Template personalizado para visualizar detalhes completos de um contato
 * com informações de contato, mensagem e anexos
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
$contact_id = get_the_ID();

// Obtém todos os campos ACF do contato
$name = get_field('name', $contact_id);
$email = get_field('email', $contact_id);
$phone = get_field('phone', $contact_id);
$linkedin = get_field('linkedin', $contact_id);
$attachment = get_field('attachment', $contact_id);
$message = get_post_field('post_content', $contact_id);
$date = get_the_date('d/m/Y H:i:s', $contact_id);

// Obtém as tags do contato
$tags = wp_get_post_terms($contact_id, 'contact_tags', array('fields' => 'all'));

// Processar anexo
$attachment_url = '';
$attachment_name = '';
$attachment_size = '';
if ($attachment) {
    if (is_array($attachment)) {
        $attachment_url = $attachment['url'] ?? '';
        $attachment_name = $attachment['filename'] ?? $attachment['title'] ?? 'Anexo';
        $attachment_size = isset($attachment['filesize']) ? size_format($attachment['filesize']) : '';
    } elseif (is_numeric($attachment)) {
        $attachment_url = wp_get_attachment_url($attachment);
        $attachment_name = get_the_title($attachment);
        $attachment_size = size_format(filesize(get_attached_file($attachment)));
    }
}
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contato: <?php echo esc_html($name ?: 'Sem nome'); ?> - Protagonizei</title>
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
                        <i class="fas fa-user mr-2 text-blue-600"></i>
                        <span class="break-words"><?php echo esc_html($name ?: 'Contato sem nome'); ?></span>
                    </h1>
                    <p class="text-gray-600 mt-1">
                        ID: <?php echo esc_html($contact_id); ?> | 
                        Recebido em: <?php echo esc_html($date); ?>
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=contact_form')); ?>" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span class="hidden sm:inline">Voltar para Lista</span>
                        <span class="sm:hidden">Voltar</span>
                    </a>
                    <a href="<?php echo esc_url(get_edit_post_link($contact_id)); ?>" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                        <i class="fas fa-edit mr-2"></i>
                        Editar Contato
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Coluna Principal -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Mensagem -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-comment-dots mr-2 text-blue-600"></i>
                        Mensagem
                    </h2>
                    <?php if (!empty($message)): ?>
                        <div class="prose max-w-none text-gray-700 bg-gray-50 rounded-lg p-4 sm:p-6 border border-gray-200">
                            <?php echo wp_kses_post(wpautop($message)); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-comment-slash text-4xl mb-4 opacity-50"></i>
                            <p>Nenhuma mensagem foi enviada.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-tags mr-2 text-purple-600"></i>
                            Tags
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-tag mr-1 text-xs"></i>
                                    <?php echo esc_html($tag->name); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Anexo -->
                <?php if ($attachment_url): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-paperclip mr-2 text-green-600"></i>
                            Anexo
                        </h2>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div class="flex items-center flex-1 min-w-0">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                            <?php 
                                            $file_ext = strtolower(pathinfo($attachment_name, PATHINFO_EXTENSION));
                                            $icon_class = 'fas fa-file';
                                            if (in_array($file_ext, ['pdf'])) {
                                                $icon_class = 'fas fa-file-pdf';
                                            } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                $icon_class = 'fas fa-file-word';
                                            } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                $icon_class = 'fas fa-file-excel';
                                            } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                                $icon_class = 'fas fa-file-image';
                                            }
                                            ?>
                                            <i class="<?php echo $icon_class; ?> text-green-600 text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate"><?php echo esc_html($attachment_name); ?></p>
                                        <?php if ($attachment_size): ?>
                                            <p class="text-xs text-gray-500"><?php echo esc_html($attachment_size); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        <span class="hidden sm:inline">Abrir</span>
                                        <span class="sm:hidden">Ver</span>
                                    </a>
                                    <a href="<?php echo esc_url($attachment_url); ?>" download class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                        <i class="fas fa-download mr-2"></i>
                                        <span class="hidden sm:inline">Download</span>
                                        <span class="sm:hidden">Baixar</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                
                <!-- Informações de Contato -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-address-card mr-2 text-blue-600"></i>
                        Informações de Contato
                    </h2>
                    
                    <div class="space-y-4">
                        <!-- Nome -->
                        <div>
                            <label class="text-sm font-medium text-gray-600 flex items-center mb-1">
                                <i class="fas fa-user mr-2 text-gray-400"></i>
                                Nome
                            </label>
                            <p class="text-gray-900 font-medium"><?php echo esc_html($name ?: 'Não informado'); ?></p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="text-sm font-medium text-gray-600 flex items-center mb-1">
                                <i class="fas fa-envelope mr-2 text-gray-400"></i>
                                Email
                            </label>
                            <?php if ($email): ?>
                                <p class="text-gray-900">
                                    <a href="mailto:<?php echo esc_attr($email); ?>" class="text-blue-600 hover:text-blue-800 transition-colors break-all">
                                        <?php echo esc_html($email); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500">Não informado</p>
                            <?php endif; ?>
                        </div>

                        <!-- Telefone -->
                        <div>
                            <label class="text-sm font-medium text-gray-600 flex items-center mb-1">
                                <i class="fas fa-phone mr-2 text-gray-400"></i>
                                Telefone
                            </label>
                            <?php if ($phone): ?>
                                <p class="text-gray-900 flex items-center gap-2 flex-wrap">
                                    <a href="tel:<?php echo esc_attr($phone); ?>" class="text-blue-600 hover:text-blue-800 transition-colors break-all">
                                        <?php echo esc_html($phone); ?>
                                    </a>
                                    <?php 
                                    // Limpar número para WhatsApp
                                    $whatsapp_number = preg_replace('/[^0-9]/', '', $phone);
                                    if (substr($whatsapp_number, 0, 1) === '0') {
                                        $whatsapp_number = substr($whatsapp_number, 1);
                                    }
                                    if (!str_starts_with($whatsapp_number, '55')) {
                                        $whatsapp_number = '55' . $whatsapp_number;
                                    }
                                    
                                    $whatsapp_message = urlencode("Olá! Entrando em contato sobre sua mensagem no site Protagonizei.");
                                    $whatsapp_url = "https://wa.me/{$whatsapp_number}?text={$whatsapp_message}";
                                    ?>
                                    <a href="<?php echo esc_url($whatsapp_url); ?>" 
                                       target="_blank" 
                                       class="inline-flex items-center justify-center w-8 h-8 bg-green-500 hover:bg-green-600 text-white rounded-full transition-colors" 
                                       title="Abrir WhatsApp">
                                        <i class="fab fa-whatsapp text-sm"></i>
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="text-gray-500">Não informado</p>
                            <?php endif; ?>
                        </div>

                        <!-- LinkedIn -->
                        <?php if ($linkedin): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-600 flex items-center mb-1">
                                    <i class="fab fa-linkedin mr-2 text-gray-400"></i>
                                    LinkedIn
                                </label>
                                <p class="text-gray-900">
                                    <a href="<?php echo esc_url($linkedin); ?>" target="_blank" class="text-blue-600 hover:text-blue-800 transition-colors break-all">
                                        <?php echo esc_html($linkedin); ?>
                                        <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Informações Adicionais -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-gray-600"></i>
                        Informações Adicionais
                    </h2>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-600">Data de Recebimento:</label>
                            <p class="text-gray-900"><?php echo esc_html($date); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600">ID do Contato:</label>
                            <p class="text-gray-900 font-mono text-sm">#<?php echo esc_html($contact_id); ?></p>
                        </div>
                        <?php if (get_the_author()): ?>
                            <div>
                                <label class="text-sm font-medium text-gray-600">Autor:</label>
                                <p class="text-gray-900"><?php echo esc_html(get_the_author()); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ações Rápidas -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bolt mr-2 text-yellow-600"></i>
                        Ações Rápidas
                    </h2>
                    
                    <div class="space-y-2">
                        <?php if ($email): ?>
                            <a href="mailto:<?php echo esc_attr($email); ?>?subject=Re: Contato Protagonizei" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                                <i class="fas fa-envelope mr-2"></i>
                                Responder por Email
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($phone): ?>
                            <a href="tel:<?php echo esc_attr($phone); ?>" class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                                <i class="fas fa-phone mr-2"></i>
                                Ligar
                            </a>
                        <?php endif; ?>
                        
                        <a href="<?php echo esc_url(get_edit_post_link($contact_id)); ?>" class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors text-sm">
                            <i class="fas fa-edit mr-2"></i>
                            Editar Contato
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

