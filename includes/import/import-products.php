<?php

// Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

function import_tiken_products() {
    // Caminho para o arquivo CSV
    $csv_file = get_template_directory() . '/assets/produtos-tiken-20-05-v2.csv';

    // Verifica se o arquivo existe
    if (!file_exists($csv_file)) {
        wp_die('Arquivo CSV não encontrado em: ' . $csv_file);
    }

    // Abre o arquivo CSV com codificação UTF-8
    setlocale(LC_ALL, 'pt_BR.UTF-8');
    $file = fopen($csv_file, 'r');
    
    // Detecta e converte a codificação se necessário
    $first_line = fgets($file);
    rewind($file);
    
    // Detecta BOM e remove se existir
    if (substr($first_line, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
        fseek($file, 3);
    }

    // Pula a primeira linha (cabeçalho)
    $header = fgetcsv($file, 0, '#');

    // Arrays para armazenar termos únicos
    $product_lines = array();
    $subcategories = array();
    
    // Primeiro passo: Coletar todas as linhas de produto e subcategorias únicas
    while (($line = fgetcsv($file, 0, '#')) !== FALSE) {
        if (count($line) < 5) continue;
        
        // Converte a codificação de cada campo se necessário
        $line = array_map(function($field) {
            // Remove caracteres invisíveis e espaços extras
            $field = trim($field);
            
            // Detecta a codificação atual
            $encoding = mb_detect_encoding($field, 'UTF-8, ISO-8859-1');
            
            // Converte para UTF-8 se não estiver
            if ($encoding != 'UTF-8') {
                $field = mb_convert_encoding($field, 'UTF-8', $encoding);
            }
            
            // Remove caracteres não imprimíveis
            $field = preg_replace('/[\x00-\x1F\x7F]/u', '', $field);
            
            return $field;
        }, $line);
        
        $product_line = $line[1];
        $subcategory = $line[2];
        
        // Valida se é uma linha de produto válida (não deve ser número)
        if (is_numeric(trim($product_line))) {
            continue;
        }
        
        if (!isset($product_lines[$product_line])) {
            $product_lines[$product_line] = array();
        }
        if (!empty($subcategory) && !in_array($subcategory, $product_lines[$product_line])) {
            $product_lines[$product_line][] = $subcategory;
        }
    }

    // Volta ao início do arquivo
    rewind($file);
    // Pula o cabeçalho novamente
    fgetcsv($file, 0, '#');

    // Criar hierarquia de termos
    $term_hierarchy = array();
    foreach ($product_lines as $product_line => $subs) {
        // Valida novamente se é uma linha de produto válida
        if (is_numeric(trim($product_line))) {
            continue;
        }
        
        // Cria ou obtém o termo pai (linha de produto)
        $parent_term = term_exists($product_line, 'product_lines');
        if (!$parent_term) {
            $parent_term = wp_insert_term($product_line, 'product_lines');
        }
        if (is_wp_error($parent_term)) continue;

        $term_hierarchy[$product_line] = array(
            'term_id' => $parent_term['term_id'],
            'subcategories' => array()
        );

        // Cria os termos filhos (subcategorias)
        foreach ($subs as $subcategory) {
            $child_term = term_exists($subcategory, 'product_lines', $parent_term['term_id']);
            if (!$child_term) {
                $child_term = wp_insert_term(
                    $subcategory,
                    'product_lines',
                    array('parent' => $parent_term['term_id'])
                );
            }
            if (!is_wp_error($child_term)) {
                $term_hierarchy[$product_line]['subcategories'][$subcategory] = $child_term['term_id'];
            }
        }
    }

    // Contador de produtos importados
    $imported = 0;
    $errors = array();

    // Processa cada linha do arquivo para criar os produtos
    while (($line = fgetcsv($file, 0, '#')) !== FALSE) {
        if (count($line) < 5) continue;

        // Converte a codificação de cada campo novamente
        $line = array_map(function($field) {
            $field = trim($field);
            $encoding = mb_detect_encoding($field, 'UTF-8, ISO-8859-1');
            if ($encoding != 'UTF-8') {
                $field = mb_convert_encoding($field, 'UTF-8', $encoding);
            }
            return preg_replace('/[\x00-\x1F\x7F]/u', '', $field);
        }, $line);

        $segments = array_map('trim', explode(',', $line[0]));
        $product_line = $line[1];
        $subcategory = $line[2];
        $product_name = $line[3];
        $cas_number = $line[4];

        // Pula se a linha de produto for numérica
        if (is_numeric(trim($product_line))) {
            $errors[] = "Linha de produto inválida (numérica): {$product_line}";
            continue;
        }

        // Verifica se temos a hierarquia para esta linha de produto
        if (!isset($term_hierarchy[$product_line])) {
            $errors[] = "Linha de produto não encontrada: {$product_line}";
            continue;
        }

        // Determina os termos para associar o produto
        $parent_term = get_term_by('name', $product_line, 'product_lines');
        if (!$parent_term) {
            // Cria o termo pai se não existir
            $parent_result = wp_insert_term($product_line, 'product_lines');
            if (!is_wp_error($parent_result)) {
                $parent_term = get_term_by('id', $parent_result['term_id'], 'product_lines');
            }
        }

        if (!$parent_term || is_wp_error($parent_term)) {
            $errors[] = "Erro ao criar/encontrar linha de produto: {$product_line}";
            continue;
        }

        $terms_to_set = array($parent_term->term_id); // Começa com o ID do pai

        // Se tiver subcategoria, cria/encontra especificamente sob este pai
        if (!empty($subcategory)) {
            // Procura a subcategoria específica sob este pai
            $child_terms = get_terms(array(
                'taxonomy' => 'product_lines',
                'name' => $subcategory,
                'parent' => $parent_term->term_id,
                'hide_empty' => false
            ));

            if (empty($child_terms)) {
                // Cria nova subcategoria sob este pai específico
                $child_result = wp_insert_term($subcategory, 'product_lines', array(
                    'parent' => $parent_term->term_id
                ));
                if (!is_wp_error($child_result)) {
                    $terms_to_set[] = $child_result['term_id'];
                }
            } else {
                // Usa a subcategoria existente que pertence a este pai
                $terms_to_set[] = $child_terms[0]->term_id;
            }
        }

        // Cria o produto
        $product_data = array(
            'post_title'    => $product_name,
            'post_status'   => 'publish',
            'post_type'     => 'products'
        );

        $product_id = wp_insert_post($product_data);

        if (!is_wp_error($product_id)) {
            // Adiciona os segmentos
            foreach ($segments as $segment) {
                wp_set_object_terms($product_id, $segment, 'segments', true);
            }

            // Adiciona a linha de produto e subcategoria usando os IDs específicos
            wp_set_object_terms($product_id, $terms_to_set, 'product_lines');

            // Adiciona o CAS Number
            update_field('cas_number', $cas_number, $product_id);

            $imported++;
        } else {
            $errors[] = "Erro ao importar produto: {$product_name} - " . $product_id->get_error_message();
        }
    }

    // Fecha o arquivo
    fclose($file);

    // Retorna o resultado
    return array(
        'imported' => $imported,
        'errors' => $errors
    );
}

// Adiciona a página de importação ao menu
function add_import_page() {
    add_submenu_page(
        'edit.php?post_type=products',
        'Importar Produtos',
        'Importar Produtos',
        'manage_options',
        'import-products',
        'render_import_page'
    );
}
add_action('admin_menu', 'add_import_page');

// Renderiza a página de importação
function render_import_page() {
    ?>
    <div class="wrap">
        <h1>Importar Produtos</h1>
        <?php
        if (isset($_POST['import_products']) && check_admin_referer('import_products_nonce')) {
            $result = import_tiken_products();
            echo '<div class="notice notice-success"><p>';
            echo "Importação concluída! {$result['imported']} produtos importados.";
            echo '</p></div>';

            if (!empty($result['errors'])) {
                echo '<div class="notice notice-error"><p>';
                echo "Erros encontrados:<br>";
                echo implode('<br>', $result['errors']);
                echo '</p></div>';
            }
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('import_products_nonce'); ?>
            <p>Clique no botão abaixo para iniciar a importação dos produtos do arquivo CSV.</p>
            <input type="submit" name="import_products" class="button button-primary" value="Iniciar Importação">
        </form>
    </div>
    <?php
} 