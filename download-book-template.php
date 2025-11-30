<?php
/**
 * Script para download de template de livro em formato ZIP
 * 
 * Este script gera um arquivo ZIP contendo:
 * - Páginas separadas por pastas (page-1, page-2, etc)
 * - Arquivos TXT para cada texto (text-boy.txt, text-girl.txt)
 * - Imagens JPG com formato: page-{número}-{gênero}-{tom}.jpg
 * 
 * @since 1.0.0
 */

// Carrega o WordPress
// Tenta encontrar wp-load.php subindo os diretórios
$wp_load_path = dirname(__FILE__);
$found = false;
for ($i = 0; $i < 4; $i++) {
    $wp_load_path = dirname($wp_load_path);
    $wp_load_file = $wp_load_path . '/wp-load.php';
    if (file_exists($wp_load_file)) {
        require_once($wp_load_file);
        $found = true;
        break;
    }
}

// Se não encontrou, tenta o caminho padrão relativo
if (!$found && !defined('ABSPATH')) {
    $default_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
    if (file_exists($default_path)) {
        require_once($default_path);
    } else {
        die('Erro: Não foi possível carregar o WordPress.');
    }
}

// Verifica se o usuário tem permissão
if (!current_user_can('edit_posts')) {
    wp_die(__('Você não tem permissão para acessar este recurso.'));
}

// Obtém o ID do template
$template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$template_id) {
    wp_die(__('ID do template não fornecido.'));
}

// Verifica se o post existe
$post = get_post($template_id);
if (!$post || $post->post_type !== 'book_templates') {
    wp_die(__('Template de livro não encontrado.'));
}

// Obtém os dados do template
$template_pages = get_field('template_book_pages', $template_id);

if (!$template_pages || empty($template_pages)) {
    wp_die(__('O template não possui páginas configuradas.'));
}

try {
    // Cria um arquivo ZIP temporário
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/temp-downloads';
    
    // Cria o diretório temporário se não existir
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }

    // Nome do arquivo ZIP
    $sanitized_title = sanitize_file_name(get_the_title($template_id));
    $zip_filename = 'template-' . $sanitized_title . '-' . $template_id . '-' . time() . '.zip';
    $zip_path = $temp_dir . '/' . $zip_filename;

    // Cria o arquivo ZIP
    if (!class_exists('ZipArchive')) {
        wp_die(__('A extensão ZipArchive não está disponível no servidor.'));
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        wp_die(__('Não foi possível criar o arquivo ZIP.'));
    }

    // Array para rastrear arquivos temporários
    $temp_files_to_clean = array();

    // Processa cada página
    foreach ($template_pages as $index => $page) {
        $page_number = $index + 1;
        $page_folder = "page-{$page_number}";

        // Adiciona textos
        $text_boy = $page['base_text_content_boy'] ?? '';
        $text_girl = $page['base_text_content_girl'] ?? '';

        // Remove tags HTML dos textos e mantém apenas o texto
        $text_boy_clean = wp_strip_all_tags($text_boy);
        $text_girl_clean = wp_strip_all_tags($text_girl);

        // Adiciona arquivos de texto
        if (!empty($text_boy_clean)) {
            $zip->addFromString("{$page_folder}/text-boy.txt", $text_boy_clean);
        }
        if (!empty($text_girl_clean)) {
            $zip->addFromString("{$page_folder}/text-girl.txt", $text_girl_clean);
        }

        // Processa ilustrações
        $base_illustrations = $page['base_illustrations'] ?? array();
        
        if (!empty($base_illustrations)) {
            foreach ($base_illustrations as $illustration_data) {
                $illustration = $illustration_data['illustration_asset'] ?? null;
                $gender = $illustration_data['gender'] ?? '';
                $skin_tone = $illustration_data['skin_tone'] ?? '';

                if (!$illustration || !$gender || !$skin_tone) {
                    continue;
                }

                // Obtém o caminho do arquivo
                $image_path = '';
                if (is_array($illustration)) {
                    $image_path = $illustration['url'] ?? '';
                    if ($image_path) {
                        // Converte URL para caminho do sistema
                        $upload_dir = wp_upload_dir();
                        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_path);
                    }
                } elseif (is_numeric($illustration)) {
                    $image_path = get_attached_file($illustration);
                }

                if (!$image_path || !file_exists($image_path)) {
                    continue;
                }

                // Mapeia tom de pele para nome do arquivo
                $skin_tone_name = ($skin_tone === 'dark') ? 'black' : 'light';

                // Nome do arquivo: page-{número}-{gênero}-{tom}.jpg
                $image_filename = "page-{$page_number}-{$gender}-{$skin_tone_name}.jpg";
                
                // Verifica se já é JPG
                $image_ext = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
                $temp_jpg = null;
                
                if ($image_ext === 'jpg' || $image_ext === 'jpeg') {
                    // Já é JPG, pode adicionar diretamente
                    $zip->addFile($image_path, "{$page_folder}/{$image_filename}");
                } else {
                    // Converte para JPG
                    $image_info = wp_get_image_editor($image_path);
                    if (is_wp_error($image_info)) {
                        continue;
                    }

                    // Cria uma cópia temporária em JPG
                    $temp_jpg = $temp_dir . '/temp-' . uniqid() . '.jpg';
                    $saved = $image_info->save($temp_jpg, 'image/jpeg');

                    if (is_wp_error($saved) || !file_exists($temp_jpg)) {
                        continue;
                    }

                    // Adiciona à lista de arquivos temporários para limpeza
                    $temp_files_to_clean[] = $temp_jpg;

                    // Adiciona ao ZIP
                    $zip->addFile($temp_jpg, "{$page_folder}/{$image_filename}");
                }
            }
        }
    }

    // Fecha o ZIP
    $zip->close();

    // Verifica se o arquivo foi criado
    if (!file_exists($zip_path)) {
        wp_die(__('O arquivo ZIP não foi criado corretamente.'));
    }

    // Limpa arquivos temporários JPG
    foreach ($temp_files_to_clean as $temp_file) {
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
    }

    // Define headers para download
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    header('Pragma: no-cache');
    header('Expires: 0');

    // Envia o arquivo
    readfile($zip_path);

    // Remove o arquivo ZIP após o download
    @unlink($zip_path);

    exit;

} catch (Exception $e) {
    // Limpa arquivos temporários em caso de erro
    if (isset($temp_files_to_clean)) {
        foreach ($temp_files_to_clean as $temp_file) {
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }
    }
    if (isset($temp_dir)) {
        $temp_files = glob($temp_dir . '/temp-*.jpg');
        foreach ($temp_files as $temp_file) {
            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }
        }
    }
    if (isset($zip_path) && file_exists($zip_path)) {
        @unlink($zip_path);
    }

    wp_die(__('Erro ao gerar o arquivo ZIP: ') . $e->getMessage());
}

