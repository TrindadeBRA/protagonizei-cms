<?php
/**
 * Webhook endpoint for merging text and image assets
 * 
 * This endpoint checks for orders with created illustration assets and merges
 * the generated text with the illustrations to create final pages
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/merge-assets', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_merge_assets_webhook',
    ));
});

/**
 * Calculate text area coordinates based on position
 * 
 * @param int $image_width Width of the image
 * @param int $image_height Height of the image  
 * @param string $position Text position (top_right, center_left, etc.)
 * @return array Array with x, y, width, height of text area
 */
function calculate_text_area($image_width, $image_height, $position) {
    // Responsive padding based on image size
    $padding = max(30, min($image_width * 0.03, $image_height * 0.03)); // 3% of smallest dimension, min 30px
    
    // Calculate area dimensions based on position
    $is_left = strpos($position, 'left') !== false;
    $is_right = strpos($position, 'right') !== false;
    
    if ($is_left || $is_right) {
        // Left or right positions use half width
        $area_width = ($image_width / 2) - ($padding * 2);
    } else {
        // Full width positions (if any custom positions are added later)
        $area_width = $image_width - ($padding * 2);
    }
    
    // Height calculation based on vertical position  
    if (strpos($position, 'top') !== false || strpos($position, 'bottom') !== false) {
        // Top or bottom positions use partial height for better text distribution
        $area_height = ($image_height / 2.5) - $padding; // Reduced area for top/bottom
    } else {
        // Center positions use more height
        $area_height = ($image_height / 1.8) - $padding; // Larger area for center
    }
    
    switch ($position) {
        case 'top_right':
            return array(
                'x' => $image_width / 2 + $padding,
                'y' => $padding,
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'center_right':
            return array(
                'x' => $image_width / 2 + $padding,
                'y' => ($image_height - $area_height) / 2, // Vertically centered
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'bottom_right':
            return array(
                'x' => $image_width / 2 + $padding,
                'y' => $image_height - $area_height - $padding,
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'top_center':
            // Full-width area centered horizontally, aligned to top
            return array(
                'x' => $padding,
                'y' => $padding,
                'width' => $area_width,
                'height' => $area_height
            );

        case 'top_left':
            return array(
                'x' => $padding,
                'y' => $padding,
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'center_left':
            return array(
                'x' => $padding,
                'y' => ($image_height - $area_height) / 2, // Vertically centered
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'bottom_left':
            return array(
                'x' => $padding,
                'y' => $image_height - $area_height - $padding,
                'width' => $area_width,
                'height' => $area_height
            );
        
        case 'bottom_center':
            // Full-width area centered horizontally, aligned to bottom
            return array(
                'x' => $padding,
                'y' => $image_height - $area_height - $padding,
                'width' => $area_width,
                'height' => $area_height
            );
            
        case 'center_center':
            // Full-width area centered both horizontally and vertically
            return array(
                'x' => $padding,
                'y' => ($image_height - $area_height) / 2, // Vertically centered
                'width' => $area_width,
                'height' => $area_height
            );
            
        default: // fallback to center_right
            return array(
                'x' => $image_width / 2 + $padding,
                'y' => ($image_height - $area_height) / 2,
                'width' => $area_width,
                'height' => $area_height
            );
    }
}

/**
 * Add text overlay to image
 * 
 * @param string $image_path Path to the base image
 * @param string $text Text to overlay
 * @param string $text_position Position where text should be placed
 * @param string $font_size Tamanho da fonte (pequeno, medio, grande)
 * @return string|false Path to the new image with text overlay or false on error
 */
function add_text_overlay_to_image($image_path, $text, $text_position = 'center_right', $font_size = 'medio') {
    // Check if GD extension is loaded
    if (!extension_loaded('gd')) {
        error_log("[TrinityKit] Extensão GD não encontrada");
        return false;
    }
    
    // Limpar e preparar o texto
    $text = strip_tags($text); // Remove tags HTML
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Decodifica entidades HTML
    $text = trim($text); // Remove espaços extras
    
    // Corrigir problemas comuns de encoding (mapeamento mais extensivo)
    $encoding_fixes = [
        'â€™' => "'", 'â€œ' => '"', 'â€' => '"', 'â€"' => '-', 'â€"' => '--',
        'Ã¡' => 'á', 'Ã©' => 'é', 'Ã­' => 'í', 'Ã³' => 'ó', 'Ãº' => 'ú',
        'Ã ' => 'à', 'Ãª' => 'ê', 'Ã§' => 'ç', 'Ã±' => 'ñ', 'Ã¢' => 'â',
        'Ã´' => 'ô', 'Ãµ' => 'õ', 'Ã¼' => 'ü', 'Ã£' => 'ã', 'ÃŸ' => 'ß',
        'Ã‡' => 'Ç', 'Ã€' => 'À', 'ÃŠ' => 'Ê', 'Ã"' => 'Ó'
    ];
    
    foreach ($encoding_fixes as $bad => $good) {
        $text = str_replace($bad, $good, $text);
    }
    
    // Garantir encoding UTF-8
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    }
    
    // Manter texto original UTF-8 para melhor compatibilidade TTF
    $original_text = $text;
    
    // Converter para ISO-8859-1 apenas se não usar TTF
    $text_for_gd = mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    if ($text_for_gd !== $text) {
        $text = $text_for_gd;
    }

    // Get image info
    $image_info = getimagesize($image_path);
    if (!$image_info) {
        error_log("[TrinityKit] Não foi possível obter informações da imagem: $image_path");
        return false;
    }

    $image_width = $image_info[0];
    $image_height = $image_info[1];
    $mime_type = $image_info['mime'];

    // Create image resource from file
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($image_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($image_path);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($image_path);
            break;
        default:
            error_log("[TrinityKit] Tipo de imagem não suportado: $mime_type");
            return false;
    }

    if (!$image) {
        error_log("[TrinityKit] Erro ao criar recurso de imagem");
        return false;
    }

    // Enable anti-aliasing for better text quality
    imageantialias($image, true);

    // Calculate text area based on position from template
    $text_area = calculate_text_area($image_width, $image_height, $text_position);
    $text_area_x = $text_area['x'];
    $text_area_y = $text_area['y'];
    $text_area_width = $text_area['width'];
    $text_area_height = $text_area['height'];

    // Define colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $shadow = imagecolorallocate($image, 0, 0, 0); // Black shadow

    // Calculate REAL font size based on text area dimensions and font_size setting
    $base_font_size = max(22, min($text_area_width / 18, $text_area_height / 10)); // Tamanho base
    
    // Aplicar multiplicador baseado no tamanho selecionado
    $font_size_multiplier = array(
        'pequeno' => 0.75,  // 75% do tamanho base
        'medio' => 1.0,     // 100% do tamanho base (padrão)
        'grande' => 1.35    // 135% do tamanho base
    );
    
    $multiplier = isset($font_size_multiplier[$font_size]) ? $font_size_multiplier[$font_size] : 1.0;
    $calculated_font_size = $base_font_size * $multiplier;
    
    // Tentar usar fonte TTF para tamanho real
    $font_path = null;
    $use_ttf = false;
    
    // Verificar se há fontes TTF disponíveis no sistema
    $possible_fonts = [
        ABSPATH . 'wp-content/themes/' . get_template() . '/assets/fonts/SourGummy.ttf',
    ];
    
    foreach ($possible_fonts as $font_file) {
        if (file_exists($font_file)) {
            $font_path = $font_file;
            $use_ttf = true;
            break;
        }
    }
    
    if (!$use_ttf) {
        error_log("[TrinityKit] AVISO - Fonte SourGummy.ttf não encontrada, usando fonte built-in");
    }
    
    if (!$use_ttf) {
        $font = 5; // Built-in largest font
        $scale_factor = max(2, intval($calculated_font_size / 13)); // Font 5 tem ~13px, escalar proporcionalmente (reduzido)
        $scale_factor = min($scale_factor, 4); // Limitar escala máxima para não ficar gigante
    } else {
        $font = 5; // Definir para compatibilidade, mas não será usado
        $scale_factor = 1; // Não usado para TTF, mas definir para evitar erros
    }

    // Usar texto UTF-8 para TTF, ISO-8859-1 para built-in
    $text_to_use = $use_ttf ? $original_text : $text;
    
    // Prepare text for word wrapping (usando texto apropriado para o método de fonte)
    $words = explode(' ', $text_to_use);
    $lines = array();
    $current_line = '';
    
    // Calculate max characters per line baseado no método de fonte
    if ($use_ttf) {
        // Para TTF, calcular baseado no tamanho real da fonte usando texto UTF-8
        $sample_bbox = imagettfbbox($calculated_font_size, 0, $font_path, 'Ag'); // Usar caracteres que mostram altura completa
        $char_width = ($sample_bbox[4] - $sample_bbox[0]) / 2; // Dividir por 2 pois usamos 2 caracteres
        $char_height = $sample_bbox[1] - $sample_bbox[7];
    } else {
        // Para built-in font com escalonamento
        $char_width = imagefontwidth($font) * $scale_factor;
        $char_height = imagefontheight($font) * $scale_factor;
    }
    
    // Calculate max chars per line with responsive padding
    $responsive_padding = max(20, $text_area_width * 0.05); // 5% of area width, min 20px
    $max_chars_per_line = floor(($text_area_width - $responsive_padding) / $char_width);
    $max_chars_per_line = max(8, $max_chars_per_line); // Garantir mínimo menor para áreas pequenas

    foreach ($words as $word) {
        $test_line = empty($current_line) ? $word : $current_line . ' ' . $word;
        
        // Usar mb_strlen para UTF-8 (TTF) ou strlen para ISO-8859-1 (built-in)
        $line_length = $use_ttf ? mb_strlen($test_line, 'UTF-8') : strlen($test_line);
        $word_length = $use_ttf ? mb_strlen($word, 'UTF-8') : strlen($word);
        
        if ($line_length <= $max_chars_per_line) {
            $current_line = $test_line;
        } else {
            if (!empty($current_line)) {
                $lines[] = $current_line;
            }
            // Se a palavra é muito longa, quebrar ela também
            if ($word_length > $max_chars_per_line) {
                // Quebrar palavra usando função apropriada para o encoding
                if ($use_ttf) {
                    // UTF-8: usar mb_substr
                    $word_len = mb_strlen($word, 'UTF-8');
                    $chunk_size = $max_chars_per_line - 1;
                    for ($i = 0; $i < $word_len; $i += $chunk_size) {
                        $chunk = mb_substr($word, $i, $chunk_size, 'UTF-8');
                        $lines[] = $chunk . ($i + $chunk_size < $word_len ? '-' : '');
                    }
                } else {
                    // ISO-8859-1: usar str_split
                    $chunks = str_split($word, $max_chars_per_line - 1);
                    foreach ($chunks as $i => $chunk) {
                        $lines[] = $chunk . ($i < count($chunks) - 1 ? '-' : '');
                    }
                }
                $current_line = '';
            } else {
                $current_line = $word;
            }
        }
    }
    
    if (!empty($current_line)) {
        $lines[] = $current_line;
    }

    // Calculate total text height com line height otimizado para SourGummy semi-bold
    if ($use_ttf) {
        // Para SourGummy semi-bold: line height reduzido para melhor compactação
        $line_height = $char_height * 1.2; // 120% do tamanho da fonte (reduzido de 140%)
    } else {
        $line_height = $char_height + 6; // Built-in escalado com espaçamento reduzido (de 8 para 6)
    }
    
    $total_text_height = count($lines) * $line_height;

    // Calculate starting Y position to center text vertically
    $start_y = $text_area_y + ($text_area_height - $total_text_height) / 2;

    // Draw each line
    foreach ($lines as $i => $line) {
        $line_y = intval($start_y + ($i * $line_height));
        
        if ($use_ttf) {
            // Usar TTF para tamanho real
            $bbox = imagettfbbox($calculated_font_size, 0, $font_path, $line);
            $text_width = $bbox[4] - $bbox[0];
            $text_height = $bbox[1] - $bbox[7];
            $text_x = intval($text_area_x + ($text_area_width - $text_width) / 2);
            
            // Ajustar Y para TTF (baseline, não topo)
            $ttf_y = $line_y + abs($bbox[7]); // Ajustar para baseline
            
            // ✨ NOVA FUNCIONALIDADE: Borda preta de 8px ao redor do texto
            $border_size = 8; // 8px de borda para máximo contraste
            
            // Criar círculo de pontos para borda suave de 8px
            $border_points = array();
            for ($angle = 0; $angle < 360; $angle += 45) { // A cada 45 graus para suavidade
                $radian = deg2rad($angle);
                $border_points[] = array(
                    'x' => $border_size * cos($radian),
                    'y' => $border_size * sin($radian)
                );
            }
            
            // Desenhar borda preta em cada ponto calculado
            foreach ($border_points as $point) {
                $border_x = $text_x + $point['x'];
                $border_y = $ttf_y + $point['y'];
                imagettftext($image, $calculated_font_size, 0, $border_x, $border_y, $shadow, $font_path, $line);
            }
            
            // Draw main text (branco) por cima da borda
            imagettftext($image, $calculated_font_size, 0, $text_x, $ttf_y, $white, $font_path, $line);
            
        } else {
            // Método de escalonamento para built-in fonts
            $text_width = strlen($line) * imagefontwidth($font) * $scale_factor;
            $text_x = intval($text_area_x + ($text_area_width - $text_width) / 2);
            
            // Criar imagem temporária pequena para desenhar o texto
            $temp_width = strlen($line) * imagefontwidth($font) + 10;
            $temp_height = imagefontheight($font) + 10;
            
            // ✨ NOVA FUNCIONALIDADE: Borda preta de 8px para built-in fonts
            $border_size_builtin = 8; // 8px de borda (será escalonado)
            $scaled_width = $temp_width * $scale_factor;
            $scaled_height = $temp_height * $scale_factor;
            
            // Criar pontos de borda para built-in fonts
            $border_points_builtin = array();
            for ($angle = 0; $angle < 360; $angle += 45) { // A cada 45 graus
                $radian = deg2rad($angle);
                $border_points_builtin[] = array(
                    'x' => $border_size_builtin * cos($radian) * $scale_factor,
                    'y' => $border_size_builtin * sin($radian) * $scale_factor
                );
            }
            
            // Desenhar borda preta em cada ponto
            foreach ($border_points_builtin as $point) {
                // Criar imagem temporária para borda
                $temp_image_border = imagecreatetruecolor($temp_width, $temp_height);
                $temp_bg_border = imagecolorallocate($temp_image_border, 255, 0, 255); // Magenta para transparência
                $temp_black_border = imagecolorallocate($temp_image_border, 0, 0, 0); // Preto para borda
                
                imagefill($temp_image_border, 0, 0, $temp_bg_border);
                imagecolortransparent($temp_image_border, $temp_bg_border);
                
                // Desenhar texto preto na imagem temporária
                imagestring($temp_image_border, $font, 5, 5, $line, $temp_black_border);
                
                // Copiar borda escalada para posição com offset
                imagecopyresized($image, $temp_image_border,
                    $text_x + $point['x'], $line_y + $point['y'], 0, 0,
                    $scaled_width, $scaled_height, $temp_width, $temp_height);
                
                imagedestroy($temp_image_border);
            }
            
            // Desenhar texto principal branco por cima
            $temp_image = imagecreatetruecolor($temp_width, $temp_height);
            $temp_bg = imagecolorallocate($temp_image, 255, 0, 255); // Magenta para transparência
            $temp_white = imagecolorallocate($temp_image, 255, 255, 255);
            
            imagefill($temp_image, 0, 0, $temp_bg);
            imagecolortransparent($temp_image, $temp_bg);
            imagestring($temp_image, $font, 5, 5, $line, $temp_white);
            
            // Draw main text escalado (branco)
            imagecopyresized($image, $temp_image,
                $text_x, $line_y, 0, 0,
                $scaled_width, $scaled_height, $temp_width, $temp_height);
            
            imagedestroy($temp_image);
        }
    }

    // Generate unique filename for the merged image
    $upload_dir = wp_upload_dir();
    $merged_filename = 'merged-page-' . uniqid() . '.jpg';
    $merged_path = $upload_dir['path'] . '/' . $merged_filename;

    // Save the image
    $save_success = imagejpeg($image, $merged_path, 90); // 90% quality

    // Clean up
    imagedestroy($image);

    if (!$save_success) {
        error_log("[TrinityKit] Erro ao salvar imagem com texto sobreposto");
        return false;
    }
    
    // Verificar se o arquivo foi realmente criado
    if (file_exists($merged_path)) {
        $file_size = filesize($merged_path);
        
        if ($file_size == 0) {
            error_log("[TrinityKit] ERRO - Arquivo criado mas está vazio");
            return false;
        }
    } else {
        error_log("[TrinityKit] ERRO - Arquivo não foi criado: $merged_path");
        return false;
    }

    return $merged_path;
}

/**
 * Download image from URL and save temporarily
 * 
 * @param string $image_url URL of the image to download
 * @return string|false Path to the temporary downloaded image or false on error
 */
function download_image_from_url($image_url) {
    if (empty($image_url)) {
        return false;
    }
    
    // Create a temporary file
    $temp_file = tempnam(sys_get_temp_dir(), 'illustration_');
    
    // Download the image
    $image_data = file_get_contents($image_url);
    
    if ($image_data === false) {
        error_log("[TrinityKit] Erro ao baixar imagem da URL: $image_url");
        return false;
    }
    
    // Save to temporary file
    $result = file_put_contents($temp_file, $image_data);
    
    if ($result === false) {
        error_log("[TrinityKit] Erro ao salvar imagem temporária");
        return false;
    }
    
    return $temp_file;
}

/**
 * Save merged image to WordPress media library with professional metadata
 * 
 * @param string $image_path Path to the merged image
 * @param int $order_id ID do pedido
 * @param string $child_name Nome da criança
 * @param int $page_index Índice da página (0-based)
 * @param string $text_position Posição do texto aplicada
 * @return string|false URL of the saved image or false on error
 */
function save_merged_image_to_wordpress($image_path, $order_id = null, $child_name = '', $page_index = 0, $text_position = 'center_right') {
    if (!file_exists($image_path)) {
        error_log("[TrinityKit] Arquivo de imagem não encontrado: $image_path");
        return false;
    }

    // Generate professional filename
    $page_number = $page_index + 1;
    $sanitized_child_name = sanitize_file_name($child_name);
    $timestamp = date('Y-m-d_H-i-s');
    
    if ($order_id && $sanitized_child_name) {
        $professional_filename = "merged-pedido-{$order_id}-{$sanitized_child_name}-pagina-{$page_number}-{$timestamp}.jpg";
    } else {
        $professional_filename = "merged-page-{$timestamp}-" . uniqid() . ".jpg";
    }
    
    $file_type = wp_check_filetype($professional_filename, null);
    
    // Preparar array para wp_handle_sideload
    $file_array = array(
        'name' => $professional_filename,
        'type' => $file_type['type'],
        'tmp_name' => $image_path,
        'error' => 0,
        'size' => filesize($image_path)
    );
    
    // Verificar permissões do diretório de uploads
    $upload_dir = wp_upload_dir();
    if (!is_writable($upload_dir['path'])) {
        error_log("[TrinityKit] ERRO - Diretório de uploads não tem permissão de escrita: " . $upload_dir['path']);
    }
    
    // Incluir funções necessárias
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    // Upload via wp_handle_sideload (mais robusto)
    $uploaded_file = wp_handle_sideload($file_array, array('test_form' => false));
    
    if (isset($uploaded_file['error'])) {
        error_log("[TrinityKit] Erro no wp_handle_sideload: " . $uploaded_file['error']);
        return false;
    }
    
    // Create professional title and description
    $professional_title = $child_name ? 
        "Página Final Mesclada - {$child_name} - Página {$page_number}" : 
        "Página Final Mesclada - Página {$page_number}";
    
    $professional_content = $order_id ? 
        "Página final do livro personalizado com texto e ilustração mesclados para o pedido #{$order_id}. Criança: {$child_name}. Página {$page_number} com posição de texto '{$text_position}'." :
        "Página final do livro personalizado com texto e ilustração mesclados.";
    
    // Criar attachment no WordPress com metadados profissionais
    $attachment = array(
        'post_mime_type' => $uploaded_file['type'],
        'post_title' => $professional_title,
        'post_content' => $professional_content,
        'post_excerpt' => "Merge Final - Pedido #{$order_id} - {$child_name} - Página {$page_number}",
        'post_status' => 'inherit'
    );
    
    // Inserir attachment
    $attach_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit] Erro ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    // Add professional metadata
    if ($order_id) {
        update_post_meta($attach_id, '_trinitykitcms_order_id', $order_id);
        update_post_meta($attach_id, '_trinitykitcms_child_name', $child_name);
        update_post_meta($attach_id, '_trinitykitcms_page_number', $page_number);
        update_post_meta($attach_id, '_trinitykitcms_page_index', $page_index);
        update_post_meta($attach_id, '_trinitykitcms_text_position', $text_position);
        update_post_meta($attach_id, '_trinitykitcms_generation_method', 'text_image_merge');
        update_post_meta($attach_id, '_trinitykitcms_generation_date', current_time('mysql'));
        update_post_meta($attach_id, '_trinitykitcms_file_source', 'webhook_merge_assets');
        update_post_meta($attach_id, '_trinitykitcms_asset_type', 'final_merged_page');
    }
    
    // Add ALT text for accessibility
    $alt_text = $child_name ? 
        "Página final mesclada de {$child_name} - Página {$page_number}" :
        "Página final mesclada - Página {$page_number}";
    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
    
    // Gerar metadados de imagem
    $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);
    
    if (empty($attach_data)) {
        $image_info = getimagesize($uploaded_file['file']);
        if ($image_info) {
            $upload_dir = wp_upload_dir();
            $attach_data = array(
                'width' => $image_info[0],
                'height' => $image_info[1],
                'file' => str_replace($upload_dir['basedir'] . '/', '', $uploaded_file['file'])
            );
        }
    }
    
    if (!empty($attach_data)) {
        wp_update_attachment_metadata($attach_id, $attach_data);
    }
    
    $final_url = wp_get_attachment_url($attach_id);
    
    return $final_url;
}

/**
 * Handles the merge assets webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_merge_assets_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    // Get all orders with status 'created_assets_illustration'
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'created_assets_illustration',
                'compare' => '='
            )
        )
    );

    $orders = get_posts($args);
    $processed = 0;
    $errors = array();

    foreach ($orders as $order) {
        $order_id = $order->ID;
        
        // Get order details
        $child_name = get_field('child_name', $order_id);
        $book_template = get_field('book_template', $order_id);
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        // Get template pages to extract text positions
        $template_pages = array();
        if ($book_template && $book_template->ID) {
            $template_pages = get_field('template_book_pages', $book_template->ID);
        }
        
        // Check required fields
        $missing_fields = array();
        
        if (empty($child_name)) {
            $missing_fields[] = 'child_name';
        }
        if (empty($generated_pages)) {
            $missing_fields[] = 'generated_book_pages';
        }
        if (!$book_template || !$book_template->ID) {
            $missing_fields[] = 'book_template';
        }
        
        if (!empty($missing_fields)) {
            $missing_fields_str = implode(', ', $missing_fields);
            $error_msg = "[TrinityKit] Campos obrigatórios vazios para o pedido #$order_id: $missing_fields_str";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        $page_errors = 0;
        $processed_pages = 0;
        
        // Process each generated page
        foreach ($generated_pages as $index => $page) {
            $text_content = $page['generated_text_content'] ?? '';
            $illustration_id = $page['generated_illustration'] ?? null;
            
            // Get font size: PRIORIDADE para o template (fonte da verdade), depois página gerada, depois 'medio'
            // Normalizar o valor: pode vir como string, array ou null
            $raw_font_size = null;
            
            // PRIORIDADE 1: Pegar do template (fonte da verdade)
            if (isset($template_pages[$index])) {
                $template_page_data = $template_pages[$index];
                
                if (isset($template_page_data['font_size'])) {
                    $raw_font_size = $template_page_data['font_size'];
                } else {
                    // PRIORIDADE 2: Se não tiver no template, pegar da página gerada
                    if (isset($page['font_size']) && !empty($page['font_size'])) {
                        $raw_font_size = $page['font_size'];
                    }
                }
            } else {
                // PRIORIDADE 2: Se não tiver no template, pegar da página gerada
                if (isset($page['font_size']) && !empty($page['font_size'])) {
                    $raw_font_size = $page['font_size'];
                }
            }
            
            // PRIORIDADE 3: Se ainda não tiver valor, usar 'medio' como padrão
            if (!isset($raw_font_size) || empty($raw_font_size)) {
                $raw_font_size = null; // Será tratado abaixo para usar 'medio'
            }
            
            // Se for array, pegar o primeiro valor ou o valor 'value'
            if (is_array($raw_font_size)) {
                $font_size = $raw_font_size['value'] ?? $raw_font_size[0] ?? 'medio';
            } else if (is_string($raw_font_size) && !empty(trim($raw_font_size))) {
                $font_size = trim($raw_font_size);
            } else {
                $font_size = 'medio';
            }
            
            // Garantir que seja um dos valores válidos
            $valid_sizes = array('pequeno', 'medio', 'grande');
            if (!in_array($font_size, $valid_sizes)) {
                error_log("[TrinityKit] AVISO - Página $index: font_size inválido '$font_size', usando 'medio'");
                $font_size = 'medio';
            }
            
            // Get text position from template page (fallback to center_right if not found)
            $text_position = 'center_right'; // Default position
            if (isset($template_pages[$index]) && isset($template_pages[$index]['text_position'])) {
                $raw_position = $template_pages[$index]['text_position'];
                
                // Map legacy Portuguese values to English values
                $position_map = array(
                    'direito_superior' => 'top_right',
                    'direito_centralizado' => 'center_right',
                    'direito_inferior' => 'bottom_right',
                    'esquerda_superior' => 'top_left',
                    'esquerda_centralizado' => 'center_left',
                    'esquerda_inferior' => 'bottom_left',
                    'superior_centralizado' => 'top_center',
                    'inferior_centralizado' => 'bottom_center',
                    'centro_centralizado' => 'center_center',
                    // Keep English values as-is
                    'top_right' => 'top_right',
                    'center_right' => 'center_right', 
                    'bottom_right' => 'bottom_right',
                    'top_left' => 'top_left',
                    'center_left' => 'center_left',
                    'bottom_left' => 'bottom_left',
                    'top_center' => 'top_center',
                    'bottom_center' => 'bottom_center',
                    'center_center' => 'center_center'
                );
                
                $text_position = isset($position_map[$raw_position]) ? $position_map[$raw_position] : 'center_right';
            }
            
            if (empty($text_content)) {
                $error_msg = "[TrinityKit] Texto gerado vazio para página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                continue;
            }
            
            // Extrair ID da ilustração do array ACF ou usar diretamente se for número
            $final_illustration_id = null;
            
            if (is_array($illustration_id)) {
                // ACF retorna array com informações da imagem
                if (isset($illustration_id['ID'])) {
                    $final_illustration_id = $illustration_id['ID'];
                } elseif (isset($illustration_id['id'])) {
                    $final_illustration_id = $illustration_id['id'];
                }
            } elseif (is_numeric($illustration_id)) {
                // ID direto
                $final_illustration_id = $illustration_id;
            }
            
            if (empty($final_illustration_id) || !is_numeric($final_illustration_id)) {
                $error_msg = "[TrinityKit] Ilustração vazia ou inválida para página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                continue;
            }
            
            // Convert to integer to ensure it's a valid attachment ID
            $illustration_id = intval($final_illustration_id);
            
            // Get the illustration file path
            $illustration_path = get_attached_file($illustration_id);
            
            // Flag para indicar se devemos limpar arquivo temporário
            $temp_file_created = false;
            
            if (!$illustration_path || !file_exists($illustration_path)) {
                // Tentar obter URL do anexo como fallback
                $illustration_url = wp_get_attachment_url($illustration_id);
                
                if ($illustration_url) {
                    // Tentar baixar a imagem da URL
                    $illustration_path = download_image_from_url($illustration_url);
                    if ($illustration_path) {
                        $temp_file_created = true;
                    }
                }
                
                if (!$illustration_path) {
                    $error_msg = "[TrinityKit] Arquivo de ilustração não encontrado para página $index do pedido #$order_id (ID: $illustration_id)";
                    error_log($error_msg);
                    $page_errors++;
                    continue;
                }
            }
            
            // Create merged image with text overlay using position and font size from template
            $merged_image_path = add_text_overlay_to_image($illustration_path, $text_content, $text_position, $font_size);
            
            if ($merged_image_path === false) {
                $error_msg = "[TrinityKit] Erro ao criar imagem mesclada para página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                
                // Clean up temporary illustration file if created
                if ($temp_file_created && file_exists($illustration_path)) {
                    unlink($illustration_path);
                }
                continue;
            }
            
            // Save merged image to WordPress
            $merged_image_url = save_merged_image_to_wordpress($merged_image_path, $order_id, $child_name, $index, $text_position);
            
            if ($merged_image_url === false) {
                $error_msg = "[TrinityKit] Erro ao salvar imagem mesclada para página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                
                // Clean up temporary files
                unlink($merged_image_path);
                if ($temp_file_created && file_exists($illustration_path)) {
                    unlink($illustration_path);
                }
                continue;
            }
            
            // Get attachment ID for the merged image
            $merged_attachment_id = attachment_url_to_postid($merged_image_url);
            if (!$merged_attachment_id) {
                error_log("[TrinityKit] AVISO: Não foi possível obter o ID do anexo para URL: $merged_image_url");
                $merged_attachment_id = 0; // Fallback
            }
            
            // Update the final page with text directly in the existing repeater
            $field_key = "generated_book_pages_{$index}_final_page_with_text";
            $update_result = update_field($field_key, $merged_attachment_id, $order_id);
            
            if ($update_result) {
                $processed_pages++;
                error_log("[TrinityKit] Página final $index salva com sucesso - URL: $merged_image_url, ID: $merged_attachment_id");
            } else {
                $page_errors++;
                error_log("[TrinityKit] Falha ao salvar página final $index do pedido #$order_id");
            }
            
            // Clean up temporary files
            unlink($merged_image_path);
            if ($temp_file_created && file_exists($illustration_path)) {
                unlink($illustration_path);
            }
        }

        if ($page_errors > 0) {
            $error_msg = "[TrinityKit] Falha ao processar $page_errors páginas do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        // Update order status if all pages were processed successfully
        if ($page_errors === 0 && $processed_pages > 0) {
            $status_updated = update_field('order_status', 'created_assets_merge', $order_id);
            
            if ($status_updated) {
                // Add log entry
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-merge-assets',
                    "Assets mesclados com sucesso para $child_name - {$processed_pages} páginas processadas",
                    'created_assets_illustration',
                    'created_assets_merge'
                );
                
                $processed++;
            } else {
                $error_msg = "[TrinityKit] Falha ao atualizar status do pedido #$order_id";
                error_log($error_msg);
                $errors[] = $error_msg;
            }
        } else {
            $error_msg = "[TrinityKit] Falha ao processar $page_errors páginas do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
        }
    }
    

    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento de merge concluído. {$processed} pedidos processados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
