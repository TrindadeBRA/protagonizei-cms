<?php

// Definir constante do diretório do plugin
define('THEME_DIR', plugin_dir_path(__FILE__));

// Carregar autoload do Composer
if (file_exists(THEME_DIR . 'vendor/autoload.php')) {
    require_once THEME_DIR . 'vendor/autoload.php';
}

// Configurações
require_once THEME_DIR . 'configs.php';

// Includes
require_once THEME_DIR . 'includes/apikey.php';
require_once THEME_DIR . 'includes/settings.php';
require_once THEME_DIR . 'includes/integrations.php';
require_once THEME_DIR . 'includes/telegram-service.php';
require_once THEME_DIR . 'includes/github-deploy.php';
require_once THEME_DIR . 'includes/utils/post-logger.php';
require_once THEME_DIR . 'includes/ajax-handlers.php';
require_once THEME_DIR . 'includes/widgets.php';
require_once THEME_DIR . 'includes/cpt/cpt-contacts.php';
require_once THEME_DIR . 'includes/scf/scf-contacts.php';
require_once THEME_DIR . 'includes/cpt/cpt-orders.php';
require_once THEME_DIR . 'includes/scf/scf-orders.php';
require_once THEME_DIR . 'includes/cpt/cpt-book-templates.php';
require_once THEME_DIR . 'includes/scf/scf-book-templates.php';
require_once THEME_DIR . 'includes/cpt/cpt-coupons.php';
require_once THEME_DIR . 'includes/scf/scf-coupons.php';
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
require_once THEME_DIR . 'endpoints/orders/webhook-initiate-faceswap.php';
require_once THEME_DIR . 'endpoints/orders/webhook-check-faceswap.php';
require_once THEME_DIR . 'endpoints/orders/webhook-initiate-falai.php';
require_once THEME_DIR . 'endpoints/orders/webhook-check-falai.php';
require_once THEME_DIR . 'endpoints/orders/webhook-falai-callback.php';
require_once THEME_DIR . 'endpoints/orders/webhook-assets-merge.php';
require_once THEME_DIR . 'endpoints/orders/webhook-generate-pdf.php';
require_once THEME_DIR . 'endpoints/orders/webhook-deliver-pdf.php';
require_once THEME_DIR . 'endpoints/orders/api-deliver-single-order.php';
require_once THEME_DIR . 'endpoints/orders/api-get-book-details.php';
require_once THEME_DIR . 'endpoints/orders/api-check-coupon.php';
require_once THEME_DIR . 'endpoints/orders/api-get-order-pages.php';
require_once THEME_DIR . 'endpoints/orders/webhook-update-order-status.php';

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

// Desabilita o tamanho máximo de imagem
add_filter('big_image_size_threshold', '__return_false');

// Remove WordPress update notifications
function remove_update_notifications() {
    // Remove core notifications
    add_filter('pre_site_transient_update_core', '__return_null');

}
add_action('admin_init', 'remove_update_notifications');



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
        $request_uri = $request->get_route();
        
        // Endpoint de webhook que não precisa de verificação CORS (chamado por serviço externo FAL.AI)
        $is_webhook_falai = strpos($request_uri, '/webhook/falai-callback') !== false;
        
        // Tratar preflight OPTIONS request primeiro
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            if ($is_webhook_falai) {
                // Webhook FAL.AI: permitir qualquer origem
                header('Access-Control-Allow-Origin: *');
            } else {
                // Outros endpoints: verificar origem permitida
                $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
                if ($origin && in_array($origin, trinitykitcms_get_allowed_origins())) {
                    header("Access-Control-Allow-Origin: $origin");
                    header('Access-Control-Allow-Credentials: true');
                }
            }
            
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-API-Key, X-Requested-With');
            header('Access-Control-Max-Age: 86400');
            status_header(200);
            exit();
        }
        
        // Se for webhook FAL.AI, permitir sem verificação de origem
        if ($is_webhook_falai) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-API-Key, X-Requested-With');
            return $served;
        }
        
        // Verificação CORS padrão para outros endpoints
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
            $allowed_origins = trinitykitcms_get_allowed_origins();
            
            if (in_array($origin, $allowed_origins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization, X-API-Key, X-Requested-With');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
            } else {
                error_log('[TrinityKit CORS] Bloqueado para origem: ' . $origin);
                error_log('[TrinityKit CORS] Origens permitidas: ' . implode(', ', $allowed_origins));
                
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['message' => 'CORS bloqueado']);
                exit;
            }
        }
        
        return $served;
    }, 10, 4);
});

/**
 * Retorna lista de origens permitidas para CORS
 * 
 * @return array Lista de origens permitidas
 */
function trinitykitcms_get_allowed_origins() {
    // Origens base permitidas (apenas desenvolvimento e CMS)
    $allowed_origins = [
        'http://localhost:3000',
        'http://localhost:8080',
        'https://cms.protagonizei.com',
        'https://cms-develop.protagonizei.com',
    ];
    
    // Adicionar URLs do frontend (suporta múltiplas URLs separadas por vírgula)
    $frontend_url = get_option('trinitykitcms_frontend_app_url');
    if (!empty($frontend_url)) {
        $frontend_urls = array_map('trim', explode(',', $frontend_url));
        $allowed_origins = array_merge($allowed_origins, $frontend_urls);
    }
    
    // Remover valores vazios e duplicados
    return array_values(array_filter(array_unique($allowed_origins)));
}

// Rewrite post preview and view URLs to use frontend domain
function trinitykitcms_rewrite_post_preview_url($preview_link, $post) {
    if (get_post_type($post) !== 'post') {
        return $preview_link;
    }
    // Se houver múltiplas URLs separadas por vírgula, usa apenas a primeira
    $frontend_urls = get_option('trinitykitcms_frontend_app_url');
    if ($frontend_urls) {
        $frontend_urls_array = array_map('trim', explode(',', $frontend_urls));
        $frontend_url = rtrim($frontend_urls_array[0], '/');
        return $frontend_url . '/blog/preview?slug=' . $post->post_name;
    }
    return $preview_link;
}
add_filter('preview_post_link', 'trinitykitcms_rewrite_post_preview_url', 10, 2);

function trinitykitcms_rewrite_post_permalink($permalink, $post) {
    if (get_post_type($post) !== 'post') {
        return $permalink;
    }
    // Se houver múltiplas URLs separadas por vírgula, usa apenas a primeira
    $frontend_urls = get_option('trinitykitcms_frontend_app_url');
    if ($frontend_urls) {
        $frontend_urls_array = array_map('trim', explode(',', $frontend_urls));
        $frontend_url = rtrim($frontend_urls_array[0], '/');
        return $frontend_url . '/blog/preview?slug=' . $post->post_name;
    }
    return $permalink;
}
add_filter('post_link', 'trinitykitcms_rewrite_post_permalink', 10, 2);
add_filter('post_type_link', 'trinitykitcms_rewrite_post_permalink', 10, 2);

// Configuração SMTP usando configurações do banco de dados
function trinitykitcms_smtp_configuration($phpmailer) {
    // Obter configurações do banco
    $smtp_host = get_option('trinitykitcms_smtp_host');
    $smtp_username = get_option('trinitykitcms_smtp_username');
    $smtp_password = get_option('trinitykitcms_smtp_password');

    // Verificar se as configurações básicas estão preenchidas
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        error_log('SMTP não configurado: Host, usuário ou senha não informados.');
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = $smtp_host;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = $smtp_username;
    $phpmailer->Password = $smtp_password;
    $phpmailer->Port = 587; // Porta padrão TLS (hardcoded)
    $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // TLS (hardcoded)

    // Configurar remetente (hardcoded)
    $phpmailer->setFrom('noreply@protagonizei.com', 'Protagonizei');
}

add_action('phpmailer_init', 'trinitykitcms_smtp_configuration');

// Permitir upload de arquivos PSD e SVG
function trinitykitcms_permitir_tipos_arquivo($mimes) {
    $mimes['psd'] = 'image/vnd.adobe.photoshop';
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}
add_filter('upload_mimes', 'trinitykitcms_permitir_tipos_arquivo');

// NÃO remover <p> quando alternar abas no editor clássico
add_filter('tiny_mce_before_init', function ($init) {
    // não ficar reescrevendo parágrafos
    $init['wpautop'] = false;
    $init['forced_root_block'] = false;

    // impedir "minificação" do HTML e manter formatação
    $init['remove_linebreaks'] = false;         // não remove quebras de linha
    $init['apply_source_formatting'] = true;    // preserva/gera formatação no source
    $init['indent'] = true;                     // indenta o HTML
    $init['keep_styles'] = true;                // evita limpar estilos/attrs

    // opcional: evita conversões chatas de caracteres
    $init['entity_encoding'] = 'raw';

    return $init;
});
