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
    
    register_rest_route('trinitykitcms-api/v1', '/contact-form/list', array(
        'methods'  => 'GET',
        'callback' => 'contact_form_list',
        'permission_callback' => function () {
            return true; // We'll handle authentication in the callback
        },
        'args' => array(
            'hours' => array(
                'required' => true,
                'type' => 'integer',
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 0;
                },
                'sanitize_callback' => 'absint',
                'description' => 'Quantidade de horas para buscar leads (ex: 24 para últimas 24 horas)'
            ),
            'exclude_tags' => array(
                'required' => false,
                'type' => 'string',
                'validate_callback' => function($param) {
                    return is_string($param) || is_null($param);
                },
                'sanitize_callback' => function($param) {
                    if (empty($param)) {
                        return '';
                    }
                    return sanitize_text_field($param);
                },
                'description' => 'Tags separadas por vírgula que devem ser ignoradas na resposta. Ex: "spam,teste,cliente"'
            ),
            'only_tags' => array(
                'required' => false,
                'type' => 'string',
                'validate_callback' => function($param) {
                    return is_string($param) || is_null($param);
                },
                'sanitize_callback' => function($param) {
                    if (empty($param)) {
                        return '';
                    }
                    return sanitize_text_field($param);
                },
                'description' => 'Tags separadas por vírgula - busca apenas leads com essas tags. Ex: "orçamento,lead"'
            )
        )
    ));
    
    register_rest_route('trinitykitcms-api/v1', '/contact-form/delete', array(
        'methods'  => 'DELETE',
        'callback' => 'contact_form_delete',
        'permission_callback' => function () {
            return true; // We'll handle authentication in the callback
        },
        'args' => array(
            'email' => array(
                'required' => true,
                'type' => 'string',
                'validate_callback' => function($param) {
                    return is_email($param);
                },
                'sanitize_callback' => 'sanitize_email',
                'description' => 'Email do lead a ser removido'
            )
        )
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

/**
 * Lista todos os leads (contatos) criados nas últimas X horas
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function contact_form_list($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    
    $hours = $request->get_param('hours');
    
    if (empty($hours) || !is_numeric($hours) || $hours < 0) {
        return new WP_Error(
            'invalid_hours',
            __('Parâmetro hours é obrigatório e deve ser um número positivo'),
            array('status' => 400)
        );
    }
    
    // Obtém os parâmetros de filtro de tags (strings separadas por vírgula)
    $exclude_tags_param = $request->get_param('exclude_tags');
    $only_tags_param = $request->get_param('only_tags');
    
    // Converte strings separadas por vírgula em arrays (case-insensitive)
    $exclude_tags = array();
    if (!empty($exclude_tags_param) && is_string($exclude_tags_param)) {
        $exclude_tags = array_map('trim', explode(',', $exclude_tags_param));
        $exclude_tags = array_filter($exclude_tags, function($tag) {
            return !empty($tag);
        });
        // Converte para minúsculas para comparação case-insensitive
        $exclude_tags = array_map('mb_strtolower', $exclude_tags);
    }
    
    $only_tags = array();
    if (!empty($only_tags_param) && is_string($only_tags_param)) {
        $only_tags = array_map('trim', explode(',', $only_tags_param));
        $only_tags = array_filter($only_tags, function($tag) {
            return !empty($tag);
        });
        // Converte para minúsculas para comparação case-insensitive
        $only_tags = array_map('mb_strtolower', $only_tags);
    }
    
    // Calcula a data de corte (últimas X horas)
    $date_cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
    
    // Busca os posts do tipo contact_form criados nas últimas X horas
    $args = array(
        'post_type' => 'contact_form',
        'post_status' => 'publish',
        'posts_per_page' => -1, // Retorna todos
        'date_query' => array(
            array(
                'after' => $date_cutoff,
                'inclusive' => true,
            ),
        ),
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $leads = get_posts($args);
    
    $leads_data = array();
    
    foreach ($leads as $lead) {
        // Busca os campos ACF
        $email = get_field('email', $lead->ID);
        $name = get_field('name', $lead->ID);
        $phone = get_field('phone', $lead->ID);
        $linkedin = get_field('linkedin', $lead->ID);
        $attachment = get_field('attachment', $lead->ID);
        
        // Busca as tags
        $tags = wp_get_post_terms($lead->ID, 'contact_tags', array('fields' => 'names'));
        $tags = $tags ? $tags : array();
        
        // Converte tags do lead para minúsculas para comparação case-insensitive
        $tags_lower = array_map('mb_strtolower', $tags);
        
        // Aplica filtro exclude_tags: se o lead tiver qualquer tag excluída, pula
        if (!empty($exclude_tags)) {
            $has_excluded_tag = false;
            foreach ($tags_lower as $tag_lower) {
                if (in_array($tag_lower, $exclude_tags)) {
                    $has_excluded_tag = true;
                    break;
                }
            }
            if ($has_excluded_tag) {
                continue; // Pula este lead
            }
        }
        
        // Aplica filtro only_tags: se especificado, apenas leads com pelo menos uma das tags
        if (!empty($only_tags)) {
            $has_included_tag = false;
            foreach ($tags_lower as $tag_lower) {
                if (in_array($tag_lower, $only_tags)) {
                    $has_included_tag = true;
                    break;
                }
            }
            if (!$has_included_tag) {
                continue; // Pula este lead
            }
        }
        
        // Prepara dados do anexo
        $attachment_data = null;
        if ($attachment) {
            if (is_array($attachment)) {
                // Se retornar array (ACF return format = array)
                $attachment_id = isset($attachment['ID']) ? $attachment['ID'] : $attachment;
            } else {
                $attachment_id = $attachment;
            }
            
            if ($attachment_id) {
                $attachment_url = wp_get_attachment_url($attachment_id);
                $attachment_metadata = wp_get_attachment_metadata($attachment_id);
                
                $attachment_data = array(
                    'id' => $attachment_id,
                    'url' => $attachment_url,
                    'filename' => basename($attachment_url),
                    'mime_type' => get_post_mime_type($attachment_id),
                    'filesize' => isset($attachment_metadata['filesize']) ? $attachment_metadata['filesize'] : filesize(get_attached_file($attachment_id))
                );
            }
        }
        
        $leads_data[] = array(
            'id' => $lead->ID,
            'title' => $lead->post_title,
            'name' => $name ? $name : 'N/A',
            'email' => $email ? $email : '',
            'phone' => $phone ? $phone : 'N/A',
            'linkedin' => $linkedin ? $linkedin : 'N/A',
            'message' => $lead->post_content,
            'tags' => $tags,
            'attachment' => $attachment_data,
            'created_at' => $lead->post_date,
            'created_at_gmt' => $lead->post_date_gmt,
            'modified_at' => $lead->post_modified,
            'modified_at_gmt' => $lead->post_modified_gmt
        );
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'data' => $leads_data,
        'total' => count($leads_data),
        'hours_filter' => intval($hours),
        'date_cutoff' => $date_cutoff,
        'exclude_tags' => !empty($exclude_tags) ? $exclude_tags : null,
        'only_tags' => !empty($only_tags) ? $only_tags : null
    ), 200);
}

/**
 * Remove todos os leads (contatos) com o email especificado
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function contact_form_delete($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    
    $email = $request->get_param('email');
    
    // Validação do email
    if (empty($email)) {
        return new WP_Error(
            'invalid_email',
            __('Parâmetro email é obrigatório'),
            array('status' => 400)
        );
    }
    
    // Sanitiza o email
    $email = sanitize_email($email);
    
    if (!is_email($email)) {
        return new WP_Error(
            'invalid_email_format',
            __('Email inválido'),
            array('status' => 400)
        );
    }
    
    // Busca todos os posts do tipo contact_form com o email especificado
    $args = array(
        'post_type' => 'contact_form',
        'post_status' => 'any', // Busca em todos os status (publish, draft, trash, etc)
        'posts_per_page' => -1, // Retorna todos
        'meta_query' => array(
            array(
                'key' => 'email',
                'value' => $email,
                'compare' => '='
            )
        )
    );
    
    $leads = get_posts($args);
    
    if (empty($leads)) {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Nenhum lead encontrado com o email especificado',
            'email' => $email,
            'deleted_count' => 0
        ), 200);
    }
    
    $deleted_count = 0;
    $deleted_ids = array();
    $errors = array();
    
    foreach ($leads as $lead) {
        // Busca anexos associados antes de deletar
        $attachment = get_field('attachment', $lead->ID);
        
        // Deleta o post (force_delete = true remove permanentemente)
        $deleted = wp_delete_post($lead->ID, true);
        
        if ($deleted) {
            $deleted_count++;
            $deleted_ids[] = $lead->ID;
            
            // Se houver anexo, também remove o arquivo
            if ($attachment) {
                if (is_array($attachment)) {
                    $attachment_id = isset($attachment['ID']) ? $attachment['ID'] : $attachment;
                } else {
                    $attachment_id = $attachment;
                }
                
                if ($attachment_id && is_numeric($attachment_id)) {
                    wp_delete_attachment($attachment_id, true);
                }
            }
        } else {
            $errors[] = "Falha ao deletar lead ID: {$lead->ID}";
        }
    }
    
    return new WP_REST_Response(array(
        'success' => true,
        'message' => "{$deleted_count} lead(s) removido(s) com sucesso",
        'email' => $email,
        'deleted_count' => $deleted_count,
        'deleted_ids' => $deleted_ids,
        'errors' => !empty($errors) ? $errors : null
    ), 200);
}