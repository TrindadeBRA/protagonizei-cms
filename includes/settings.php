<?php

function trinitykitcms_save_settings() {
    // Verifica se o usuário tem permissão
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Você não tem permissão para realizar esta ação.', array('status' => 403));
    }

    // Lista de campos permitidos com os novos nomes
    $allowed_fields = array(
        'trinitykitcms_whatsapp_url',
        'trinitykitcms_frontend_app_url',
        'trinitykitcms_github_user',
        'trinitykitcms_github_repo',
        'trinitykitcms_github_token',
        'trinitykitcms_google_analytics_id',
        'trinitykitcms_google_tag_manager_id'
    );

    // Processa cada campo
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            
            // Validação específica para URLs
            if ($field === 'trinitykitcms_whatsapp_url' || $field === 'trinitykitcms_frontend_app_url') {
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return new WP_Error(
                        'invalid_url',
                        'URL inválida para o campo ' . $field,
                        array('status' => 400)
                    );
                }
            }
            
            // Salva a configuração usando update_option
            update_option($field, $value);
        }
    }

    return array(
        'success' => true,
        'message' => 'Configurações salvas com sucesso!'
    );
}

function trinitykitcms_get_settings() {
    // Verifica se o usuário tem permissão
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'Você não tem permissão para realizar esta ação.', array('status' => 403));
    }

    // Retorna todas as configurações
    return array(
        'whatsapp_url' => get_theme_mod('whatsapp_url', ''),
        'frontend_app_url' => get_theme_mod('frontend_app_url', ''),
        'github_user' => get_theme_mod('github_user', ''),
        'github_repo' => get_theme_mod('github_repo', ''),
        'github_token' => get_theme_mod('github_token', ''),
        'google_analytics_id' => get_theme_mod('google_analytics_id', ''),//G-XXXXXXX
        'google_tag_manager_id' => get_theme_mod('google_tag_manager_id', '') //GTM-XXXXXXXX
    );
}
