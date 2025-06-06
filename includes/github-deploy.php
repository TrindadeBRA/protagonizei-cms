<?php

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto ao arquivo
}

// Adicionar submenu para página de deploy GitHub
function trinitykitcms_add_github_deploy_submenu() {
    add_submenu_page(
        'trinitykitcms',
        'GitHub Deployment',
        'GitHub Deployment',
        'manage_options',
        'trinitykitcms-github-deploy',
        'trinitykitcms_render_github_deploy_page'
    );
}
add_action('admin_menu', 'trinitykitcms_add_github_deploy_submenu');

// Função para renderizar a página de deploy GitHub
function trinitykitcms_render_github_deploy_page() {
    // Verificar se o formulário foi enviado
    if (isset($_POST['github_deploy']) && check_admin_referer('trinitykitcms_github_deploy')) {
        $response = trinitykitcms_trigger_github_workflow();
        if (is_wp_error($response)) {
            echo '<div class="notice notice-error"><p>' . esc_html($response->get_error_message()) . '</p></div>';
        } else {
            $github_user = get_option('trinitykitcms_github_user', '');
            $github_repo = get_option('trinitykitcms_github_repo', '');
            $actions_url = "https://github.com/{$github_user}/{$github_repo}/actions";
            echo '<div class="notice notice-success"><p>Workflow iniciado com sucesso! <a href="' . esc_url($actions_url) . '" target="_blank">Clique aqui para acompanhar o status no GitHub Actions</a>.</p></div>';
        }
    }

    // Obter as configurações do GitHub
    $github_user = get_option('trinitykitcms_github_user', '');
    $github_repo = get_option('trinitykitcms_github_repo', '');
    $github_token = get_option('trinitykitcms_github_token', '');
?>
    <div class="wrap">
        <h1 style="margin-bottom: 30px; font-size: 24px; font-weight: bold;">GitHub Deployment</h1>

        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Configurações do Deploy</h2>
            <p>Configure as informações necessárias para o deploy do frontend através do GitHub Actions.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_github_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Usuário GitHub</th>
                        <td>
                            <input type="text" name="trinitykitcms_github_user" value="<?php echo esc_attr($github_user); ?>" class="regular-text" required>
                            <p class="description">Seu usuário do GitHub.</p>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th scope="row">Nome do Repositório</th>
                        <td>
                            <input type="text" name="trinitykitcms_github_repo" value="<?php echo esc_attr($github_repo); ?>" class="regular-text" required>
                            <p class="description">Nome do repositório no GitHub.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Token GitHub</th>
                        <td>
                            <input type="password" name="trinitykitcms_github_token" value="<?php echo esc_attr($github_token); ?>" class="regular-text" required>
                            <p class="description">Token pessoal de acesso ao GitHub. Pode ser gerado em: <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações'); ?>
            </form>
        </div>

        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Iniciar Deploy</h2>
            <p>Inicie o workflow de deploy no GitHub. Certifique-se de que todas as configurações estejam corretas.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('trinitykitcms_github_deploy'); ?>
                <p>
                    <input type="submit" name="github_deploy" value="Iniciar Deploy no GitHub" class="button button-primary button-large" 
                        <?php echo (empty($github_user) || empty($github_repo) || empty($github_token)) ? 'disabled' : ''; ?>>
                </p>
                <?php if (empty($github_user) || empty($github_repo) || empty($github_token)): ?>
                    <p class="description" style="color: #d63638;">Preencha todas as configurações do GitHub antes de iniciar o deploy.</p>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php
}

// Função para registrar as configurações do GitHub
function trinitykitcms_register_github_settings() {
    register_setting('trinitykitcms_github_settings', 'trinitykitcms_github_user', 'sanitize_text_field');
    register_setting('trinitykitcms_github_settings', 'trinitykitcms_github_repo', 'sanitize_text_field');
    register_setting('trinitykitcms_github_settings', 'trinitykitcms_github_token', 'sanitize_text_field');
}
add_action('admin_init', 'trinitykitcms_register_github_settings');

// Função para iniciar o workflow do GitHub
function trinitykitcms_trigger_github_workflow() {
    $github_user = get_option('trinitykitcms_github_user');
    $github_repo = get_option('trinitykitcms_github_repo');
    $github_token = get_option('trinitykitcms_github_token');
    
    if (empty($github_user) || empty($github_repo) || empty($github_token)) {
        return new WP_Error('missing_github_config', 'Configurações do GitHub incompletas.');
    }
    
    // Criar um commit diretamente na branch main para disparar o workflow
    $url = "https://api.github.com/repos/{$github_user}/{$github_repo}/contents/.github/workflow-trigger.txt";
    
    // Obtenção do SHA do arquivo se ele existir
    $args = array(
        'method' => 'GET',
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . $github_token,
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        )
    );
    
    $get_response = wp_remote_get($url, $args);
    $sha = '';
    
    if (!is_wp_error($get_response) && wp_remote_retrieve_response_code($get_response) === 200) {
        $file_data = json_decode(wp_remote_retrieve_body($get_response), true);
        if (isset($file_data['sha'])) {
            $sha = $file_data['sha'];
        }
    }
    
    // Dados para o commit
    $timestamp = current_time('timestamp');
    $data = array(
        'message' => 'Trigger deploy workflow - ' . date('Y-m-d H:i:s', $timestamp),
        'content' => base64_encode("Deploy trigger timestamp: " . $timestamp),
        'branch' => 'master'
    );
    
    // Adicionar SHA se o arquivo já existir
    if (!empty($sha)) {
        $data['sha'] = $sha;
    }
    
    // Configurar os argumentos da requisição para o commit
    $args = array(
        'method' => 'PUT',
        'timeout' => 30,
        'redirection' => 5,
        'httpversion' => '1.1',
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . $github_token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'body' => json_encode($data)
    );
    
    // Fazer a requisição para criar/atualizar o arquivo
    $response = wp_remote_request($url, $args);
    
    // Verificar se houve erros
    if (is_wp_error($response)) {
        return $response;
    }
    
    // Verificar o código de status
    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200 && $status_code !== 201) {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error_message = 'Erro ao iniciar o workflow no GitHub. ';
        $error_message .= 'Status: ' . $status_code . '. ';
        
        if (is_array($body) && isset($body['message'])) {
            $error_message .= 'Mensagem: ' . $body['message'];
        } else {
            $error_message .= 'Resposta: ' . wp_remote_retrieve_body($response);
        }
        
        return new WP_Error('github_error', $error_message);
    }
    
    // Adicionar ou atualizar secrets no repositório
    $secrets = array(
        'NEXT_PUBLIC_WORDPRESS_API_URL' => site_url('/wp-json/trinitykitcms-api/v1/'),
        'WORDPRESS_API_KEY' => get_option('trinitykitcms_api_key')
    );
    
    // Não estamos implementando a atualização de secrets aqui porque requer um processo de criptografia
    // mais complexo (libsodium) que não é facilmente implementável aqui
    
    return true;
} 