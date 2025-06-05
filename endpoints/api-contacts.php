<?php

/**
 * Registra as rotas da API para o formulário de contato
 */
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/contact-form/submit', array(
        'methods'  => 'POST',
        'callback' => 'contact_form_submit',
        'permission_callback' => '__return_true'
    ));
});

/**
 * Processa o envio do formulário de contato
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function contact_form_submit($request) {
    
    $params = $request->get_params();

    // Extrai e sanitiza os parâmetros
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : 'N/A';
    $email = isset($params['email']) ? sanitize_email($params['email']) : '';
    $phone = isset($params['phone']) ? sanitize_text_field($params['phone']) : 'N/A';
    $message = isset($params['message']) ? sanitize_textarea_field($params['message']) : 'N/A';
    $tag = isset($params['tag']) ? sanitize_text_field($params['tag']) : '';
    $linkedin = isset($params['linkedin']) ? sanitize_text_field($params['linkedin']) : 'N/A';

    // Validação dos campos obrigatórios
    if (empty($email)) {
        return new WP_Error('invalid_email_data', __('Email inválido'), array('status' => 400));
    }
    
    if (empty($tag)) {
        return new WP_Error('invalid_tag_data', __('Tag não pode estar vazia'), array('status' => 400));
    }

    // Handle file upload
    $attachment_id = null;
    if (!empty($_FILES['attachment'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $_FILES['attachment'];

        // Sanitize file name
        $sanitized_filename = sanitize_file_name($file['name']);
        $file['name'] = $sanitized_filename;
        $_FILES['attachment']['name'] = $sanitized_filename;

        // Validate file type
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/svg+xml',
            'image/webp'
        );

        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file_type', __('Tipo de arquivo não permitido'), array('status' => 400));
        }

        // Check file size (10MB limit)
        if ($file['size'] > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', __('Arquivo muito grande. Tamanho máximo permitido: 10MB'), array('status' => 400));
        }

        // Additional validation for PDF files
        if ($file['type'] === 'application/pdf') {
            // Read first 5 bytes to check PDF signature
            $handle = fopen($file['tmp_name'], 'rb');
            if ($handle === false) {
                return new WP_Error('pdf_validation_failed', __('Não foi possível abrir o arquivo PDF para validação'), array('status' => 500));
            }

            $header = fread($handle, 5);
            fclose($handle);

            if ($header !== '%PDF-') {
                return new WP_Error('invalid_pdf', __('Arquivo PDF inválido ou corrompido'), array('status' => 400));
            }
        }

        // Upload the file
        $attachment_id = media_handle_upload('attachment', 0);

        if (is_wp_error($attachment_id)) {
            return new WP_Error('file_upload_failed', __('Falha ao fazer upload do arquivo: ' . $attachment_id->get_error_message()), array('status' => 500));
        }

        // Verify the attachment was created
        if (!$attachment_id) {
            return new WP_Error('file_upload_failed', __('Falha ao criar anexo'), array('status' => 500));
        }

        // Get the file path and update metadata
        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            wp_delete_attachment($attachment_id, true);
            return new WP_Error('file_not_found', __('Arquivo não encontrado no servidor'), array('status' => 404));
        }

        // Set proper file permissions
        chmod($file_path, 0644);

        // Update attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!$metadata) {
            $metadata = array();
        }
        $metadata['file'] = _wp_relative_upload_path($file_path);
        wp_update_attachment_metadata($attachment_id, $metadata);

        // For PDFs, ensure proper MIME type is set
        if ($file['type'] === 'application/pdf') {
            update_post_meta($attachment_id, '_wp_attached_file', $file_path);
            update_post_meta($attachment_id, '_wp_mime_type', 'application/pdf');
        }
    }

    // Define o título do post
    if (!empty($name) && !empty($email)) {
        $post_title = $name . ' - ' . $email;
    } else {
        $post_title = $email;
    }

    // Cria o post
    $post_id = wp_insert_post(array(
        'post_title'   => $post_title,
        'post_content' => $message,
        'post_status'  => 'publish',
        'post_type'    => 'contact_form',
    ));

    // Atualiza os campos personalizados
    update_field('email', $email, $post_id);
    update_field('name', $name, $post_id);
    update_field('phone', $phone, $post_id);
    update_field('linkedin', $linkedin, $post_id);

    // Update attachment field if file was uploaded
    if ($attachment_id) {
        update_field('attachment', $attachment_id, $post_id);
    }

    // Adiciona a tag ao post
    if (!empty($tag)) {
        wp_set_object_terms($post_id, $tag, 'contact_tags', true);
    }

    // Caso a tag seja "Solicitar Amostra" ou "Literatura Técnica" enviar email
    if ($tag == 'Solicitar Amostra' || $tag == 'Literatura Técnica') {
        $to = array(
            'trindadebra@gmail.com',
            'marketing@tiken.com.br',
            'laboratorio@tiken.com.br'
        );
        $subject = $tag . ' - ' . $name;
        $body = $message;

        wp_mail($to, $subject, $body);
    }

    // Retorna a resposta
    if ($post_id) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Formulário enviado com sucesso',
            'attachment_id' => $attachment_id,
            'attachment_url' => $attachment_id ? wp_get_attachment_url($attachment_id) : null
        ), 200);
    } else {
        return new WP_Error(
            'submission_failed', 
            __('Falha ao enviar formulário'), 
            array('status' => 500)
        );
    }
}