<?php

// Definir constante do diretório do plugin
define('THEME_DIR', plugin_dir_path(__FILE__));

// Configurações
require_once THEME_DIR . 'configs.php';

// Includes
require_once THEME_DIR . 'includes/apikey.php';
require_once THEME_DIR . 'includes/settings.php';
require_once THEME_DIR . 'includes/integrations.php';
require_once THEME_DIR . 'includes/github-deploy.php';
require_once THEME_DIR . 'includes/utils/post-logger.php';
require_once THEME_DIR . 'includes/cpt/cpt-contacts.php';
require_once THEME_DIR . 'includes/scf/scf-contacts.php';
require_once THEME_DIR . 'includes/cpt/cpt-orders.php';
require_once THEME_DIR . 'includes/scf/scf-orders.php';
require_once THEME_DIR . 'includes/cpt/cpt-book-templates.php';
require_once THEME_DIR . 'includes/scf/scf-book-templates.php';
require_once THEME_DIR . 'includes/swagger/swagger-page.php';

// Endpoints
require_once THEME_DIR . 'endpoints/api-configs.php';
require_once THEME_DIR . 'endpoints/api-contacts.php';
require_once THEME_DIR . 'endpoints/api-posts-slugs.php';
require_once THEME_DIR . 'endpoints/api-post-details.php';

// Endpoints de pedidos
require_once THEME_DIR . 'endpoints/orders/api-create-order.php';
require_once THEME_DIR . 'endpoints/orders/api-create-pix-payment.php';
require_once THEME_DIR . 'endpoints/orders/webhook-payment-confirm.php';
require_once THEME_DIR . 'endpoints/orders/api-check-payment-status.php';
require_once THEME_DIR . 'endpoints/orders/webhook-email-thanks.php';
require_once THEME_DIR . 'endpoints/orders/webhook-assets-text.php';
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
            'http://localhost:8080',
            'https://cms.protagonizei.com',
            $frontend_url
        ];

        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];

            if (in_array($origin, $allowed_origins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-API-Key, X-Requested-With');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');    // cache for 1 day
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['message' => 'CORS bloqueado']);
                exit;
            }
        }

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
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

// Looking to send emails in production? Check out our Email API/SMTP product!
function mailtrap($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = 'sandbox.smtp.mailtrap.io';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 2525;
    $phpmailer->Username = '074245c3a9070d';
    $phpmailer->Password = 'ad803e39bef73e';
  }
  
  add_action('phpmailer_init', 'mailtrap');