<?php
/**
 * Template Index - Redirecionamento
 * 
 * Se não estiver logado: redireciona para o frontend
 * Se estiver logado: redireciona para o dashboard do WordPress
 */

// Se não estiver logado, redirecionar para o frontend
if (!is_user_logged_in()) {
    $frontend_urls = get_option('trinitykitcms_frontend_app_url');
        if ($frontend_urls) {
        $urls_array = array_map('trim', explode(',', $frontend_urls));
        $main_frontend_url = $urls_array[0];
        wp_redirect($main_frontend_url);
        exit;
    }
}

// Se estiver logado, redirecionar para o dashboard do WordPress
wp_redirect(admin_url('index.php'));
exit; 