<?php
/**
 * Webhook para gerar PDFs com as páginas finais dos livros
 * 
 * Este endpoint processa pedidos com status 'created_assets_merge',
 * coleta todas as imagens final_page_with_text de cada página,
 * e gera um PDF completo do livro personalizado usando a biblioteca TCPDF.
 * 
 * Funcionalidades:
 * - Busca pedidos no status 'created_assets_merge'
 * - Coleta todas as imagens finais das páginas do livro
 * - Gera PDF usando a biblioteca TCPDF (alta qualidade e compatibilidade)
 * - Salva o PDF no WordPress Media Library
 * - Atualiza os campos generated_pdf_link e generated_pdf_attachment
 * - Atualiza status do pedido para 'ready_for_delivery'
 * - Notifica erros via Telegram quando necessário
 * 
 * @author TrinityKit Team
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/includes/integrations.php';
require_once get_template_directory() . '/vendor/autoload.php';

/**
 * Registra o endpoint REST API para geração de PDFs
 */
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/generate-pdf', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_generate_pdf_webhook',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Função principal do webhook de geração de PDFs
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function trinitykit_handle_generate_pdf_webhook($request) {
    error_log("[TrinityKit] Iniciando webhook de geração de PDFs");
    
    try {
        // Buscar pedidos no status 'created_assets_merge'
        $orders = get_posts(array(
            'post_type' => 'orders',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'order_status',
                    'value' => 'created_assets_merge',
                    'compare' => '='
                )
            )
        ));

        if (empty($orders)) {
            return new WP_REST_Response(array(
                'message' => 'Nenhum pedido encontrado com status created_assets_merge',
                'processed' => 0,
                'total' => 0,
                'errors' => array()
            ), 200);
        }

        $processed = 0;
        $total = count($orders);
        $errors = array();

        error_log("[TrinityKit] Encontrados $total pedidos para gerar PDF");

        foreach ($orders as $order) {
            $order_id = $order->ID;
            $child_name = get_field('child_name', $order_id);
            
            error_log("[TrinityKit] Processando pedido #$order_id - $child_name");

            try {
                // Verificar se já existe PDF gerado
                $existing_pdf = get_field('generated_pdf_attachment', $order_id);
                if (!empty($existing_pdf)) {
                    error_log("[TrinityKit] Pedido #$order_id já possui PDF gerado, pulando");
                    continue;
                }

                // Buscar páginas geradas do livro
                $generated_pages = get_field('generated_book_pages', $order_id);
                if (empty($generated_pages)) {
                    $error_msg = "Pedido #$order_id: Nenhuma página gerada encontrada";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    send_telegram_error_notification(
                        "❌ *Erro na geração de PDF*\n\n" .
                        "📋 Pedido: #$order_id\n" .
                        "👶 Criança: $child_name\n" .
                        "🔍 Problema: Nenhuma página gerada encontrada\n\n" .
                        "🔗 [Ver pedido](https://cms.protagonizei.com/wp-admin/post.php?post=$order_id&action=edit)",
                        "Erro na Geração de PDF"
                    );
                    continue;
                }

                // Coletar imagens finais das páginas
                $page_images = array();
                $missing_pages = array();

                foreach ($generated_pages as $index => $page) {
                    $final_image = $page['final_page_with_text'] ?? null;
                    
                    if (empty($final_image) || empty($final_image['url'])) {
                        $missing_pages[] = $index + 1;
                        continue;
                    }

                    $page_images[] = array(
                        'page_number' => $index + 1,
                        'image_url' => $final_image['url'],
                        'image_path' => get_attached_file($final_image['ID'])
                    );
                }

                if (!empty($missing_pages)) {
                    $missing_list = implode(', ', $missing_pages);
                    $error_msg = "Pedido #$order_id: Páginas finais ausentes: $missing_list";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    send_telegram_error_notification(
                        "⚠️ *Páginas finais ausentes*\n\n" .
                        "📋 Pedido: #$order_id\n" .
                        "👶 Criança: $child_name\n" .
                        "📄 Páginas ausentes: $missing_list\n\n" .
                        "🔗 [Ver pedido](https://cms.protagonizei.com/wp-admin/post.php?post=$order_id&action=edit)",
                        "Páginas Finais Ausentes"
                    );
                    continue;
                }

                if (empty($page_images)) {
                    $error_msg = "Pedido #$order_id: Nenhuma imagem final válida encontrada";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    continue;
                }

                // Gerar PDF
                error_log("[TrinityKit] Gerando PDF para pedido #$order_id com " . count($page_images) . " páginas");
                $pdf_result = generate_book_pdf($order_id, $child_name, $page_images);
                
                if (!$pdf_result['success']) {
                    $error_msg = "Pedido #$order_id: " . $pdf_result['error'];
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    send_telegram_error_notification(
                        "❌ *Erro na geração de PDF*\n\n" .
                        "📋 Pedido: #$order_id\n" .
                        "👶 Criança: $child_name\n" .
                        "🔍 Erro: " . $pdf_result['error'] . "\n\n" .
                        "🔗 [Ver pedido](https://cms.protagonizei.com/wp-admin/post.php?post=$order_id&action=edit)",
                        "Erro na Geração de PDF"
                    );
                    continue;
                }

                // Salvar PDF no WordPress
                $attachment_id = save_pdf_to_wordpress(
                    $pdf_result['file_path'], 
                    $order_id, 
                    $child_name, 
                    count($page_images)
                );

                if (!$attachment_id) {
                    $error_msg = "Pedido #$order_id: Falha ao salvar PDF no WordPress";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    continue;
                }

                // Obter URL do PDF
                $pdf_url = wp_get_attachment_url($attachment_id);

                // Atualizar campos ACF
                $pdf_attachment_data = array(
                    'ID' => $attachment_id,
                    'url' => $pdf_url,
                    'filename' => basename(get_attached_file($attachment_id))
                );

                update_field('generated_pdf_link', $pdf_url, $order_id);
                update_field('generated_pdf_attachment', $pdf_attachment_data, $order_id);

                // Atualizar status do pedido para pronto para entrega
                update_field('order_status', 'ready_for_delivery', $order_id);

                // Adicionar log de progresso
                trinitykit_add_post_log(
                    $order_id,
                    'webhook-generate-pdf',
                    "PDF gerado com sucesso para $child_name - " . count($page_images) . " páginas. Pedido pronto para entrega!",
                    'created_assets_merge',
                    'ready_for_delivery'
                );

                // Limpar arquivo temporário
                if (file_exists($pdf_result['file_path'])) {
                    unlink($pdf_result['file_path']);
                }

                $processed++;
                error_log("[TrinityKit] PDF gerado com sucesso para pedido #$order_id");

            } catch (Exception $e) {
                $error_msg = "Pedido #$order_id: Erro inesperado - " . $e->getMessage();
                error_log("[TrinityKit] $error_msg");
                $errors[] = $error_msg;
                send_telegram_error_notification(
                    "💥 *Erro inesperado na geração de PDF*\n\n" .
                    "📋 Pedido: #$order_id\n" .
                    "👶 Criança: $child_name\n" .
                    "🔍 Erro: " . $e->getMessage() . "\n\n" .
                    "🔗 [Ver pedido](https://cms.protagonizei.com/wp-admin/post.php?post=$order_id&action=edit)",
                    "Erro Inesperado"
                );
            }
        }

        $message = "Processamento de geração de PDF concluído. $processed pedidos processados.";
        error_log("[TrinityKit] $message");

        $response_data = array(
            'message' => $message,
            'processed' => $processed,
            'total' => $total,
            'errors' => $errors
        );

        $status_code = empty($errors) ? 200 : 500;
        return new WP_REST_Response($response_data, $status_code);

    } catch (Exception $e) {
        $error_msg = 'Erro inesperado no webhook de geração de PDF: ' . $e->getMessage();
        error_log("[TrinityKit] $error_msg");
        
        return new WP_REST_Response(array(
            'message' => $error_msg,
            'processed' => 0,
            'total' => 0,
            'errors' => array($error_msg)
        ), 500);
    }
}

/**
 * Gera um PDF com as páginas do livro usando recursos nativos do PHP
 * 
 * @param int $order_id ID do pedido
 * @param string $child_name Nome da criança
 * @param array $page_images Array com dados das imagens das páginas
 * @return array Resultado da operação
 */
function generate_book_pdf($order_id, $child_name, $page_images) {
    try {
        // Garantir ordenação correta das páginas
        usort($page_images, function($a, $b) {
            return $a['page_number'] - $b['page_number'];
        });
        
        error_log("[TrinityKit] Páginas ordenadas para PDF:");
        foreach ($page_images as $page) {
            error_log("[TrinityKit] - Página " . $page['page_number'] . ": " . basename($page['image_path']));
        }
        
        // Gerar nome do arquivo
        $sanitized_child_name = sanitize_file_name($child_name);
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "livro-{$sanitized_child_name}-pedido-{$order_id}-{$timestamp}.pdf";
        
        // Definir caminho temporário
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['path'] . '/' . $filename;
        
        error_log("[TrinityKit] Diretório de upload: " . $upload_dir['path']);
        error_log("[TrinityKit] Caminho do PDF: $temp_path");
        
        // Verificar se o diretório existe e é gravável
        if (!is_dir($upload_dir['path'])) {
            return array(
                'success' => false,
                'error' => 'Diretório de uploads não existe: ' . $upload_dir['path']
            );
        }
        
        if (!is_writable($upload_dir['path'])) {
            return array(
                'success' => false,
                'error' => 'Diretório de uploads não é gravável: ' . $upload_dir['path']
            );
        }
        
        // Criar PDF usando TCPDF
        error_log("[TrinityKit] Criando PDF com TCPDF...");
        
        // Criar instância do TCPDF
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurações do documento
        $pdf->SetCreator('TrinityKit CMS');
        $pdf->SetAuthor('Protagonizei');
        $pdf->SetTitle("Livro Personalizado - $child_name");
        $pdf->SetSubject("Livro personalizado gerado para $child_name");
        $pdf->SetKeywords('livro, personalizado, criança, protagonizei');
        
        // Remover cabeçalho e rodapé padrão
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configurar margens (muito pequenas para usar toda a página)
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        
        // Processar cada página
        foreach ($page_images as $index => $page_data) {
            if (!file_exists($page_data['image_path'])) {
                error_log("[TrinityKit] Imagem não encontrada: " . $page_data['image_path']);
                continue;
            }
            
            error_log("[TrinityKit] Processando página " . $page_data['page_number'] . ": " . $page_data['image_path']);
            
            // Adicionar nova página
            $pdf->AddPage();
            
            // Obter dimensões da página em mm
            $page_width = $pdf->getPageWidth();
            $page_height = $pdf->getPageHeight();
            
            // Inserir imagem ocupando toda a página
            $pdf->Image(
                $page_data['image_path'],  // Caminho da imagem
                0,                         // X
                0,                         // Y
                $page_width,              // Largura
                $page_height,             // Altura
                '',                       // Tipo (auto-detect)
                '',                       // Link
                '',                       // Align
                false,                    // Resize
                300,                      // DPI
                '',                       // Palign
                false,                    // Ismask
                false,                    // Imgmask
                0,                        // Border
                false,                    // Fitbox
                false,                    // Hidden
                true                      // Fitonpage
            );
        }
        
        // Salvar PDF
        $pdf->Output($temp_path, 'F');
        
        if (!file_exists($temp_path)) {
            return array(
                'success' => false,
                'error' => 'Erro ao salvar arquivo PDF em: ' . $temp_path
            );
        }
        
        $file_size = filesize($temp_path);
        error_log("[TrinityKit] PDF gerado com sucesso: $file_size bytes em $temp_path");
        
        return array(
            'success' => true,
            'file_path' => $temp_path,
            'filename' => $filename
        );
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => 'Erro ao gerar PDF: ' . $e->getMessage()
        );
    }
}



/**
 * Salva o PDF no WordPress Media Library com metadados profissionais
 * 
 * @param string $file_path Caminho do arquivo PDF
 * @param int $order_id ID do pedido
 * @param string $child_name Nome da criança
 * @param int $total_pages Número total de páginas
 * @return int|false ID do attachment ou false em caso de erro
 */
function save_pdf_to_wordpress($file_path, $order_id, $child_name, $total_pages) {
    if (!file_exists($file_path)) {
        error_log("[TrinityKit] Arquivo PDF não encontrado: $file_path");
        return false;
    }
    
    $filename = basename($file_path);
    $upload_dir = wp_upload_dir();
    
    // O arquivo já está no diretório correto, usar o caminho atual
    $target_path = $file_path;
    
    // Preparar dados do attachment
    $attachment = array(
        'guid' => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => 'application/pdf',
        'post_title' => "Livro Personalizado - $child_name",
        'post_content' => "Livro personalizado gerado para $child_name com $total_pages páginas. Pedido #$order_id.",
        'post_excerpt' => "PDF do livro personalizado de $child_name ($total_pages páginas)",
        'post_status' => 'inherit'
    );
    
    // Inserir attachment
    $attach_id = wp_insert_attachment($attachment, $target_path);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit] Erro ao inserir PDF: " . $attach_id->get_error_message());
        return false;
    }
    
    // Gerar metadados do attachment
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $target_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    // Adicionar metadados personalizados
    update_post_meta($attach_id, '_trinitykitcms_order_id', $order_id);
    update_post_meta($attach_id, '_trinitykitcms_child_name', $child_name);
    update_post_meta($attach_id, '_trinitykitcms_total_pages', $total_pages);
    update_post_meta($attach_id, '_trinitykitcms_generation_method', 'tcpdf_library');
    update_post_meta($attach_id, '_trinitykitcms_generation_date', current_time('mysql'));
    update_post_meta($attach_id, '_trinitykitcms_file_source', 'webhook_generate_pdf');
    update_post_meta($attach_id, '_trinitykitcms_file_type', 'complete_book_pdf');
    
    // Adicionar descrição alternativa
    update_post_meta($attach_id, '_wp_attachment_image_alt', "Livro personalizado de $child_name - $total_pages páginas");
    
    error_log("[TrinityKit] PDF salvo com sucesso - ID: $attach_id, Arquivo: $filename");
    
    return $attach_id;
}

// Nota: A função send_telegram_error_notification() está definida em webhook-initiate-faceswap.php
