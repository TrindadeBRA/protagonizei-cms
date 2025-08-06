<?php
/**
 * Webhook endpoint for creating image assets with face swap
 * 
 * This endpoint checks for orders with created text assets and creates image assets with face swap
 * 
 * @package TrinityKit
 */

// Ensure this file is being included by WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Register the webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/generate-image-assets', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_image_assets_webhook',
    ));
});

/**
 * Process face swap with FaceSwap API
 * 
 * @param string $swap_image_url URL da imagem do rosto da criança
 * @param string $target_image_url URL da imagem base para aplicar o face swap
 * @return string|false URL da imagem processada ou false em caso de erro
 */
function process_face_swap_with_faceswap($swap_image_url, $target_image_url) {
    $api_key = get_option('trinitykitcms_faceswap_api_key');
    $base_url = get_option('trinitykitcms_faceswap_base_url');

    if (empty($api_key) || empty($base_url)) {
        error_log("[TrinityKit] Configurações do FaceSwap não encontradas");
        return false;
    }

    // URL para iniciar o face swap
    $run_url = $base_url . '/run';
    
    $headers = [
        'Content-Type: application/json',
        'x-magicapi-key: ' . $api_key
    ];

    $body = [
        'input' => [
            'swap_image' => $swap_image_url,
            'target_image' => $target_image_url,
            'target_face_index' => 1
        ]
    ];

    // Primeira requisição - iniciar o face swap
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $run_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log("[TrinityKit] Erro cURL na requisição de face swap: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("[TrinityKit] Erro FaceSwap API ($http_code): " . $response);
        return false;
    }
    
    $response_data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("[TrinityKit] Resposta JSON inválida do FaceSwap: " . json_last_error_msg());
        return false;
    }
    
    $task_id = $response_data['id'];
    
    // Aguardar e verificar o status
    $max_attempts = 30; // 30 tentativas com 2 segundos cada = 1 minuto
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        sleep(2); // Aguardar 2 segundos entre verificações
        
        $status_url = $base_url . '/status/' . $task_id;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $status_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $status_response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log("[TrinityKit] Erro cURL na verificação de status: " . curl_error($ch));
            curl_close($ch);
            $attempt++;
            continue;
        }
        
        $status_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_http_code !== 200) {
            error_log("[TrinityKit] Erro na verificação de status ($status_http_code): " . $status_response);
            $attempt++;
            continue;
        }
        
        $status_data = json_decode($status_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[TrinityKit] Resposta JSON inválida na verificação de status: " . json_last_error_msg());
            $attempt++;
            continue;
        }
        
        if ($status_data['status'] === 'COMPLETED') {
            // Processar a imagem base64 e salvar no WordPress
            $base64_image = $status_data['output'];
            $image_url = save_base64_image_to_wordpress($base64_image);
            
            if ($image_url) {
                return $image_url;
            } else {
                error_log("[TrinityKit] Erro ao salvar imagem processada");
                return false;
            }
        } elseif ($status_data['status'] === 'FAILED') {
            error_log("[TrinityKit] Face swap falhou para task ID: " . $task_id);
            return false;
        }
        
        $attempt++;
    }
    
    error_log("[TrinityKit] Timeout aguardando processamento do face swap");
    return false;
}

/**
 * Salva uma imagem base64 no WordPress
 * 
 * @param string $base64_image String base64 da imagem
 * @return string|false URL da imagem salva ou false em caso de erro
 */
function save_base64_image_to_wordpress($base64_image) {
    // Remover o prefixo data:image/jpeg;base64, se existir
    if (strpos($base64_image, 'data:image/') === 0) {
        $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
    }
    
    $image_data = base64_decode($base64_image);
    
    if ($image_data === false) {
        error_log("[TrinityKit] Erro ao decodificar imagem base64");
        return false;
    }
    
    // Gerar nome único para o arquivo
    $filename = 'faceswap-' . uniqid() . '.jpg';
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    // Salvar arquivo
    $file_saved = file_put_contents($file_path, $image_data);
    
    if ($file_saved === false) {
        error_log("[TrinityKit] Erro ao salvar arquivo de imagem");
        return false;
    }
    
    // Preparar dados para inserção no WordPress
    $file_type = wp_check_filetype($filename, null);
    
    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    
    // Inserir no WordPress
    $attach_id = wp_insert_attachment($attachment, $file_path);
    
    if (is_wp_error($attach_id)) {
        error_log("[TrinityKit] Erro ao inserir anexo: " . $attach_id->get_error_message());
        return false;
    }
    
    // Gerar tamanhos de imagem
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    return wp_get_attachment_url($attach_id);
}

/**
 * Handles the image assets webhook
 * 
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response Response object
 */
function trinitykit_handle_image_assets_webhook($request) {
    // Get all orders with status 'created_assets_text'
    $args = array(
        'post_type' => 'orders',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'order_status',
                'value' => 'created_assets_text',
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
        $child_gender = get_field('child_gender', $order_id);
        $child_skin_tone = get_field('child_skin_tone', $order_id);
        $child_face_photo = get_field('child_face_photo', $order_id);
        $book_template = get_field('book_template', $order_id);
        $generated_pages = get_field('generated_book_pages', $order_id);
        
        // Skip if required fields are empty
        if (empty($child_name) || empty($child_gender) || empty($child_skin_tone) || 
            empty($child_face_photo) || !$book_template || empty($generated_pages)) {
            $error_msg = "[TrinityKit] Campos obrigatórios vazios para o pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        $template_pages = get_field('template_book_pages', $book_template->ID);
        $updated_pages = array();
        $page_errors = 0;

        foreach ($template_pages as $index => $page) {
            // Determinar a imagem base baseada no gênero e tom de pele
            $base_image_field = '';
            if ($child_gender === 'menino') {
                if ($child_skin_tone === 'clara') {
                    $base_image_field = 'base_illustration_boy_light';
                } elseif ($child_skin_tone === 'media') {
                    $base_image_field = 'base_illustration_boy_medium';
                } else { // escura
                    $base_image_field = 'base_illustration_boy_dark';
                }
            } else { // menina
                if ($child_skin_tone === 'clara') {
                    $base_image_field = 'base_illustration_girl_light';
                } elseif ($child_skin_tone === 'media') {
                    $base_image_field = 'base_illustration_girl_medium';
                } else { // escura
                    $base_image_field = 'base_illustration_girl_dark';
                }
            }

            $base_image = $page[$base_image_field];
            
            if (empty($base_image)) {
                $error_msg = "[TrinityKit] Imagem base não encontrada para página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                continue;
            }

            // URLs das imagens
            $swap_image_url = $child_face_photo['url'];
            $target_image_url = $base_image['url'];

            // Processar face swap
            $processed_image_url = process_face_swap_with_faceswap($swap_image_url, $target_image_url);
            
            if ($processed_image_url === false) {
                $error_msg = "[TrinityKit] Erro ao processar face swap da página $index do pedido #$order_id";
                error_log($error_msg);
                $page_errors++;
                continue;
            }

            // Atualizar a página com a nova ilustração
            $updated_pages[] = array(
                'generated_text_content' => $generated_pages[$index]['generated_text_content'],
                'generated_illustration' => array(
                    'url' => $processed_image_url,
                    'id' => attachment_url_to_postid($processed_image_url)
                )
            );
        }

        if ($page_errors > 0) {
            $error_msg = "[TrinityKit] Falha ao processar $page_errors páginas do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
            continue;
        }

        // Update order with generated pages
        $pages_updated = update_field('generated_book_pages', $updated_pages, $order_id);
        $status_updated = update_field('order_status', 'created_assets_illustration', $order_id);
        
        if ($pages_updated && $status_updated) {
            // Add log entry
            trinitykit_add_post_log(
                $order_id,
                'webhook-image-assets',
                "Ilustrações com face swap geradas para $child_name",
                'created_assets_text',
                'created_assets_illustration'
            );
            
            $processed++;
        } else {
            $error_msg = "[TrinityKit] Falha ao atualizar campos do pedido #$order_id";
            error_log($error_msg);
            $errors[] = $error_msg;
        }
    }
    
    if (!empty($errors)) {
        error_log("[TrinityKit] Erros encontrados: " . implode(" | ", $errors));
    }
    
    // Return response with detailed information
    return new WP_REST_Response(array(
        'message' => "Processamento concluído. {$processed} pedidos processados.",
        'processed' => $processed,
        'total' => count($orders),
        'errors' => $errors
    ), !empty($errors) ? 500 : 200);
}
