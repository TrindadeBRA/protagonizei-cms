<?php

// Definir constante do diretório do plugin
define('THEME_DIR', plugin_dir_path(__FILE__));

// Configurações
require_once THEME_DIR . 'configs.php';

// Includes
require_once THEME_DIR . 'includes/apikey.php';
require_once THEME_DIR . 'includes/settings.php';
require_once THEME_DIR . 'includes/github-deploy.php';
require_once THEME_DIR . 'includes/cpt/cpt-contacts.php';
require_once THEME_DIR . 'includes/scf/scf-contacts.php';
require_once THEME_DIR . 'includes/swagger/swagger-page.php';

// Endpoints
require_once THEME_DIR . 'endpoints/api-configs.php';
require_once THEME_DIR . 'endpoints/api-contacts.php';
require_once THEME_DIR . 'endpoints/api-posts-slugs.php';
require_once THEME_DIR . 'endpoints/api-post-details.php';

// Impede acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

// Configuração básica do tema
function trinitykitcms_setup() {
    add_theme_support('title-tag'); // Permite que o WP gerencie o título da página
    add_theme_support('post-thumbnails'); // Ativa suporte a imagens destacadas
    add_theme_support('custom-logo'); // Permite upload de logo personalizado
}

// Hook para executar a configuração do tema
add_action('after_setup_theme', 'trinitykitcms_setup');

// Configuração de logs para debug do tema
ini_set('error_log', get_template_directory() . '/debug.log');


// Verifica se o plugin Secure Custom Fields está ativo.
add_action('admin_init', 'check_secure_custom_fields');
function check_secure_custom_fields() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    
    // Verifica se o plugin Secure Custom Fields está ativo.
    // Altere o caminho para refletir a estrutura do plugin, se necessário.
    if ( ! is_plugin_active( 'secure-custom-fields/secure-custom-fields.php' ) ) {
        add_action( 'admin_notices', 'secure_custom_fields_warning' );
    }
}

function secure_custom_fields_warning() {
    echo '<div class="notice notice-error">
            <p><strong>Warning:</strong> The <em>Secure Custom Fields</em> plugin is required for the full functionality of this theme. Please <a href="/wp-admin/plugin-install.php?s=Secure%2520Custom%2520Fields&tab=search&type=term" target="_blank">install and activate the plugin</a>.</p>
          </div>';
}

// Desabilita o Gutenberg e força o uso do editor clássico
function disable_gutenberg_editor() {
    return false;
}
add_filter('use_block_editor_for_post', 'disable_gutenberg_editor', 10);
add_filter('use_block_editor_for_post_type', 'disable_gutenberg_editor', 10);


// Configuração do SMTP para o envio de emails
add_action('phpmailer_init', function($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp-hve.office365.com';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $phpmailer->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $phpmailer->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
    $phpmailer->SMTPSecure = 'tls'; // STARTTLS
    $phpmailer->From       = defined('SMTP_FROM') ? SMTP_FROM : '';
    $phpmailer->FromName   = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : '';
    $phpmailer->addReplyTo(
        defined('SMTP_REPLY_TO') ? SMTP_REPLY_TO : '',
        defined('SMTP_REPLY_TO_NAME') ? SMTP_REPLY_TO_NAME : ''
    );
});

// Retorna erro 403 se alguém tentar acessar o index
add_filter('rest_index', function ($response) {
    return new WP_Error(
        'rest_forbidden',
        __('Acesso à API bloqueado.'),
        ['status' => 403]
    );
});


add_action('rest_api_init', function () {
    add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
        $frontend_url = get_option('trinitykitcms_frontend_app_url');
        
        $allowed_origins = [
            'http://localhost:3000',
            $frontend_url
        ];

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];

            if (in_array($origin, $allowed_origins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization');
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['message' => 'CORS bloqueado']);
                exit;
            }
        }

        return $served;
    }, 10, 4);
});

// Rewrite post preview and view URLs to use frontend domain
function trinitykitcms_rewrite_post_preview_url($preview_link, $post) {
    if (get_post_type($post) !== 'post') {
        return $preview_link;
    }
    $frontend_url = get_option('trinitykitcms_frontend_app_url');
    if ($frontend_url) {
        $frontend_url = rtrim($frontend_url, '/');
        return $frontend_url . '/blog/preview?slug=' . $post->post_name;
    }
    return $preview_link;
}
add_filter('preview_post_link', 'trinitykitcms_rewrite_post_preview_url', 10, 2);

function trinitykitcms_rewrite_post_permalink($permalink, $post) {
    if (get_post_type($post) !== 'post') {
        return $permalink;
    }
    $frontend_url = get_option('trinitykitcms_frontend_app_url');
    if ($frontend_url) {
        $frontend_url = rtrim($frontend_url, '/');
        return $frontend_url . '/blog/preview?slug=' . $post->post_name;
    }
    return $permalink;
}
add_filter('post_link', 'trinitykitcms_rewrite_post_permalink', 10, 2);
add_filter('post_type_link', 'trinitykitcms_rewrite_post_permalink', 10, 2);