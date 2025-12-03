<?php
/**
 * Template para exibir um modelo de livro individual
 * 
 * Template personalizado para visualizar detalhes completos de um modelo de livro
 * com todas as páginas e suas configurações
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
$template_id = get_the_ID();

// Obtém todos os campos ACF do template
$book_price = get_field('book_price', $template_id);
$template_pages = get_field('template_book_pages', $template_id);

// Labels para posição do texto
$text_position_labels = array(
    'top_right' => 'Direito Superior',
    'center_right' => 'Direito Centralizado',
    'bottom_right' => 'Direito Inferior',
    'top_center' => 'Superior Centralizado',
    'top_left' => 'Esquerda Superior',
    'center_left' => 'Esquerda Centralizado',
    'bottom_center' => 'Inferior Centralizado',
    'bottom_left' => 'Esquerda Inferior',
    'center_center' => 'Centro Centralizado',
);

// Labels para tamanho da fonte
$font_size_labels = array(
    'pequeno' => 'Pequeno',
    'medio' => 'Médio',
    'grande' => 'Grande',
);
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modelo de Livro: <?php echo esc_html(get_the_title()); ?> - Protagonizei</title>
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
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-book mr-2 text-indigo-600"></i>
                        <?php echo esc_html(get_the_title()); ?>
                    </h1>
                    <p class="text-gray-600 mt-1">
                        ID: <?php echo esc_html($template_id); ?> | 
                        Total de Páginas: <?php echo $template_pages ? count($template_pages) : '0'; ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($book_price): ?>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">Preço</p>
                            <p class="text-2xl font-bold text-green-600">
                                R$ <?php echo number_format($book_price, 2, ',', '.'); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=book_templates')); ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Voltar para Lista
                    </a>
                    <a href="<?php echo esc_url(get_template_directory_uri() . '/download-book-template.php?id=' . $template_id); ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        <i class="fas fa-download mr-2"></i>
                        Baixar Template
                    </a>
                    <a href="<?php echo esc_url(get_edit_post_link($template_id)); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-edit mr-2"></i>
                        Editar Modelo
                    </a>
                </div>
            </div>
        </div>

        <!-- Informações Gerais -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-indigo-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-file-alt text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total de Páginas</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $template_pages ? count($template_pages) : '0'; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Preço do Livro</p>
                        <p class="text-2xl font-bold text-gray-900">
                            R$ <?php echo $book_price ? number_format($book_price, 2, ',', '.') : '0,00'; ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-500 rounded-md flex items-center justify-center">
                            <i class="fas fa-palette text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Ilustrações Base</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php 
                            $total_illustrations = 0;
                            if ($template_pages) {
                                foreach ($template_pages as $page) {
                                    $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
                                    if ($skip_faceswap) {
                                        // Se skip_faceswap é true, conta apenas 1 ilustração
                                        $total_illustrations += 1;
                                    } else {
                                        // Se skip_faceswap é false, conta todas as ilustrações
                                        $illustrations = $page['base_illustrations'] ?? array();
                                        $total_illustrations += count($illustrations);
                                    }
                                }
                            }
                            echo $total_illustrations;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Páginas do Livro -->
        <?php if ($template_pages && count($template_pages) > 0): ?>
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">
                        <i class="fas fa-book-open mr-2 text-indigo-600"></i>
                        Páginas do Livro
                    </h2>
                </div>

                <?php foreach ($template_pages as $index => $page): ?>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <!-- Cabeçalho da Página -->
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-indigo-800 font-bold text-lg"><?php echo $index + 1; ?></span>
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900">Página <?php echo $index + 1; ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <?php if (!empty($page['page_number'])): ?>
                                            Número da página: <?php echo esc_html($page['page_number']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <?php 
                                $skip_faceswap = !empty($page['skip_faceswap']) && $page['skip_faceswap'] === true;
                                if ($skip_faceswap): ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
                                        <i class="fas fa-ban mr-2"></i>
                                        Sem Face Swap
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-user-circle mr-2"></i>
                                        Com Face Swap
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Coluna Esquerda: Textos -->
                            <div class="space-y-6">
                                <!-- Texto para Menino -->
                                <div class="border border-blue-200 rounded-lg p-4 bg-blue-50">
                                    <div class="flex items-center mb-3">
                                        <i class="fas fa-mars text-blue-600 mr-2"></i>
                                        <h4 class="text-lg font-semibold text-blue-900">Texto Base para Menino</h4>
                                    </div>
                                    <?php if (!empty($page['base_text_content_boy'])): ?>
                                        <div class="bg-white p-4 rounded border border-blue-200">
                                            <div class="prose max-w-none text-sm text-gray-700">
                                                <?php echo wp_kses_post($page['base_text_content_boy']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-500 italic">Texto não definido</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Texto para Menina -->
                                <div class="border border-pink-200 rounded-lg p-4 bg-pink-50">
                                    <div class="flex items-center mb-3">
                                        <i class="fas fa-venus text-pink-600 mr-2"></i>
                                        <h4 class="text-lg font-semibold text-pink-900">Texto Base para Menina</h4>
                                    </div>
                                    <?php if (!empty($page['base_text_content_girl'])): ?>
                                        <div class="bg-white p-4 rounded border border-pink-200">
                                            <div class="prose max-w-none text-sm text-gray-700">
                                                <?php echo wp_kses_post($page['base_text_content_girl']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-gray-500 italic">Texto não definido</p>
                                    <?php endif; ?>
                                </div>

                                <!-- Configurações do Texto -->
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4">
                                        <i class="fas fa-cog mr-2 text-gray-600"></i>
                                        Configurações do Texto
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="text-sm font-medium text-gray-600">Posição do Texto:</label>
                                            <p class="text-gray-900 font-medium">
                                                <?php 
                                                $text_position = $page['text_position'] ?? 'center_right';
                                                echo esc_html($text_position_labels[$text_position] ?? $text_position);
                                                ?>
                                            </p>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-600">Tamanho da Fonte:</label>
                                            <p class="text-gray-900 font-medium">
                                                <?php 
                                                $font_size = $page['font_size'] ?? 'medio';
                                                echo esc_html($font_size_labels[$font_size] ?? $font_size);
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Coluna Direita: Ilustrações Base -->
                            <div class="space-y-6">
                                <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4">
                                        <i class="fas fa-images mr-2 text-purple-600"></i>
                                        Ilustrações Base
                                    </h4>
                                    
                                    <?php 
                                    $base_illustrations = $page['base_illustrations'] ?? array();
                                    if (!empty($base_illustrations)): ?>
                                        <div class="grid grid-cols-2 gap-4">
                                            <?php foreach ($base_illustrations as $illustration_data): ?>
                                                <?php 
                                                $illustration = $illustration_data['illustration_asset'] ?? null;
                                                $gender = $illustration_data['gender'] ?? '';
                                                $skin_tone = $illustration_data['skin_tone'] ?? '';
                                                
                                                if ($illustration):
                                                    $thumb_url = '';
                                                    $full_url = '';
                                                    if (is_array($illustration)) {
                                                        $thumb_url = $illustration['sizes']['medium'] ?? $illustration['url'] ?? '';
                                                        $full_url = $illustration['url'] ?? '';
                                                    } elseif (is_numeric($illustration)) {
                                                        $thumb_url = wp_get_attachment_image_url($illustration, 'medium');
                                                        $full_url = wp_get_attachment_image_url($illustration, 'full');
                                                    }
                                                    
                                                    // Labels
                                                    $gender_label = $gender === 'boy' ? 'Menino' : ($gender === 'girl' ? 'Menina' : '');
                                                    $skin_tone_label = $skin_tone === 'light' ? 'Claro' : ($skin_tone === 'dark' ? 'Escuro' : '');
                                                    
                                                    // Cores baseadas no gênero
                                                    $color_scheme = $gender === 'boy' ? 'blue' : 'pink';
                                                    ?>
                                                    <div class="border border-<?php echo $color_scheme; ?>-200 rounded-lg p-4 bg-<?php echo $color_scheme; ?>-50">
                                                        <div class="text-center mb-2">
                                                            <p class="text-sm font-medium text-<?php echo $color_scheme; ?>-800">
                                                                <?php if ($gender === 'boy'): ?>
                                                                    <i class="fas fa-mars mr-1"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-venus mr-1"></i>
                                                                <?php endif; ?>
                                                                <?php echo esc_html($gender_label); ?>
                                                            </p>
                                                            <p class="text-xs text-<?php echo $color_scheme; ?>-600">
                                                                Tom: <?php echo esc_html($skin_tone_label); ?>
                                                            </p>
                                                        </div>
                                                        <?php if ($thumb_url): ?>
                                                            <a href="<?php echo esc_url($full_url ?: $thumb_url); ?>" target="_blank" class="block hover:opacity-80 transition-opacity" title="Clique para ver em tamanho original">
                                                                <img src="<?php echo esc_url($thumb_url); ?>" 
                                                                     alt="<?php echo esc_attr($gender_label . ' ' . $skin_tone_label); ?>" 
                                                                     class="clickable-image w-full h-48 object-cover rounded-lg border-2 border-<?php echo $color_scheme; ?>-300 hover:border-<?php echo $color_scheme; ?>-400 shadow-md"
                                                                     loading="lazy">
                                                            </a>
                                                            <p class="text-xs text-gray-500 mt-2 text-center">
                                                                <i class="fas fa-external-link-alt mr-1"></i>
                                                                Clique para ampliar
                                                            </p>
                                                        <?php else: ?>
                                                            <div class="w-full h-48 bg-gray-200 rounded-lg border-2 border-<?php echo $color_scheme; ?>-300 flex items-center justify-center">
                                                                <span class="text-gray-400 text-sm">Imagem não disponível</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-8 text-gray-500">
                                            <i class="fas fa-image text-4xl mb-4 opacity-50"></i>
                                            <p>Nenhuma ilustração base configurada para esta página.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhuma página configurada</h3>
                <p class="text-gray-600 mb-6">Este modelo de livro ainda não possui páginas configuradas.</p>
                <a href="<?php echo esc_url(get_edit_post_link($template_id)); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Adicionar Páginas
                </a>
            </div>
        <?php endif; ?>

        <!-- Informações Adicionais -->
        <?php if (get_the_content()): ?>
            <div class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                    Descrição
                </h2>
                <div class="prose max-w-none text-gray-700">
                    <?php echo wp_kses_post(get_the_content()); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

