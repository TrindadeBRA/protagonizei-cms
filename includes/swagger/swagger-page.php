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
function trinitykitcms_validate_json($json_string) {
    json_decode($json_string);
    return json_last_error() === JSON_ERROR_NONE;
}

function trinitykitcms_serve_swagger_json()
{
    if (isset($_GET['trinitykitcms_swagger'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        $json = file_get_contents(THEME_DIR . 'includes/swagger/swagger.json');
        if ($json === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to read swagger.json']);
            exit;
        }
        if (!trinitykitcms_validate_json($json)) {
            http_response_code(500);
            echo json_encode(['error' => 'Invalid JSON in swagger.json']);
            exit;
        }
        echo $json;
        exit;
    }
    
    if (isset($_GET['trinitykitcms_orders_swagger'])) {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        $json = file_get_contents(THEME_DIR . 'includes/swagger/orders-swagger.json');
        if ($json === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to read orders-swagger.json']);
            exit;
        }
        if (!trinitykitcms_validate_json($json)) {
            http_response_code(500);
            echo json_encode(['error' => 'Invalid JSON in orders-swagger.json']);
            exit;
        }
        echo $json;
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
                background-color: #f5f5f5;
            }

            .swagger-container {
                max-width: 1460px;
                margin: 0 auto;
                padding: 20px;
            }

            .api-section {
                margin-bottom: 40px;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                overflow: hidden;
            }

            .api-title {
                font-size: 24px;
                margin: 0;
                padding: 20px;
                background-color: #41444e;
                color: white;
                border-bottom: 1px solid #e0e0e0;
            }

            .api-description {
                padding: 20px;
                background-color: #f8f9fa;
                border-bottom: 1px solid #e0e0e0;
                color: #666;
            }

            .swagger-ui-wrapper {
                padding: 20px;
            }

            .swagger-ui .topbar {
                display: none;
            }

            .auth-container {
                background-color: #f8f9fa;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }

            .auth-title {
                font-size: 18px;
                margin-bottom: 10px;
                color: #41444e;
            }

            .auth-input {
                width: 100%;
                padding: 8px;
                margin-bottom: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }

            .auth-button {
                background-color: #41444e;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
            }

            .auth-button:hover {
                background-color: #2c2e33;
            }
        </style>
    </head>

    <body>
        <div class="swagger-container">
            <div class="auth-container">
                <h3 class="auth-title">Configuração de Autenticação</h3>
                <input type="text" id="api-key" class="auth-input" placeholder="Digite sua API Key">
                <button onclick="setApiKey()" class="auth-button">Aplicar API Key</button>
            </div>

            <div class="api-section">
                <h2 class="api-title">API Principal</h2>
                <div class="api-description">
                    Documentação da API principal do TrinityKitCMS, incluindo endpoints para posts, configurações e formulário de contato.
                </div>
                <div class="swagger-ui-wrapper">
                    <div id="swagger-ui-main"></div>
                </div>
            </div>
            
            <div class="api-section">
                <h2 class="api-title">API de Pedidos</h2>
                <div class="api-description">
                    Documentação da API de pedidos, incluindo endpoints para listagem e criação de pedidos.
                </div>
                <div class="swagger-ui-wrapper">
                    <div id="swagger-ui-orders"></div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
        <script>
            let apiKey = '';

            function setApiKey() {
                apiKey = document.getElementById('api-key').value;
                if (apiKey) {
                    // Atualizar a UI do Swagger com a nova API Key
                    window.mainUI.preauthorizeApiKey('ApiKeyAuth', apiKey);
                    window.ordersUI.preauthorizeApiKey('ApiKeyAuth', apiKey);
                }
            }

            window.onload = function() {
                const mainApiUrl = '<?php echo add_query_arg('trinitykitcms_swagger', '1', site_url()); ?>';
                const ordersApiUrl = '<?php echo add_query_arg('trinitykitcms_orders_swagger', '1', site_url()); ?>';

                // Configuração para a API Principal
                window.mainUI = SwaggerUIBundle({
                    url: mainApiUrl,
                    dom_id: '#swagger-ui-main',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout",
                    docExpansion: "list",
                    defaultModelsExpandDepth: -1,
                    onComplete: function() {
                        if (apiKey) {
                            window.mainUI.preauthorizeApiKey('ApiKeyAuth', apiKey);
                        }
                    }
                });

                // Configuração para a API de Pedidos
                window.ordersUI = SwaggerUIBundle({
                    url: ordersApiUrl,
                    dom_id: '#swagger-ui-orders',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout",
                    docExpansion: "list",
                    defaultModelsExpandDepth: -1,
                    onComplete: function() {
                        if (apiKey) {
                            window.ordersUI.preauthorizeApiKey('ApiKeyAuth', apiKey);
                        }
                    }
                });
            };
        </script>
    </body>

    </html>
<?php
    exit;
}