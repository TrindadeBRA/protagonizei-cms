<?php
if (!defined('ABSPATH')) {
    exit;
}

// Adicionar a página do Swagger
function trinitykitcms_add_swagger_page()
{
    if (isset($_GET['trinitykitcms_swagger_ui'])) {
        // Verificar se o usuário está logado e tem capacidade de administrador
        if (!current_user_can('manage_options')) {
            wp_die('Acesso negado. Você precisa ter permissões de administrador para acessar esta página.', 'Acesso Negado', [
                'response' => 403,
                'back_link' => true,
            ]);
        }
        require_once THEME_DIR . 'includes/swagger/swagger-page.php';
        trinitykitcms_render_swagger_page();
    }
}
add_action('init', 'trinitykitcms_add_swagger_page');

// Adicionar rota para servir o arquivo swagger.json
function trinitykitcms_serve_swagger_json()
{
    if (isset($_GET['trinitykitcms_swagger'])) {
        header('Content-Type: application/json');
        echo file_get_contents(THEME_DIR . 'includes/swagger/swagger.json');
        exit;
    }
}
add_action('init', 'trinitykitcms_serve_swagger_json');


function trinitykitcms_render_swagger_page()
{
?>
    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <title>TrinityKitCMS API - Documentação</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
        <style>
            body {
                margin: 0;
                padding: 0;
            }

            #swagger-ui {
                max-width: 1460px;
                margin: 0 auto;
                padding: 20px;
            }
        </style>
    </head>

    <body>
        <div id="swagger-ui"></div>
        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
        <script>
            window.onload = function() {
                window.ui = SwaggerUIBundle({
                    url: '<?php echo add_query_arg('trinitykitcms_swagger', '1', site_url()); ?>',
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                });
            };
        </script>
    </body>

    </html>
<?php
    exit;
}