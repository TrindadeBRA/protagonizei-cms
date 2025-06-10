<?php

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto ao arquivo
}

// Registrar as configurações de integrações
function trinitykitcms_register_integration_settings() {
    // Registrar configurações do Asaas
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_wallet_id', 'sanitize_text_field');
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_api_url', 'sanitize_url');

    // Registrar configurações do Deepseek
    register_setting('trinitykitcms_deepseek_settings', 'trinitykitcms_deepseek_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_deepseek_settings', 'trinitykitcms_deepseek_base_url', 'sanitize_url');
}
add_action('admin_init', 'trinitykitcms_register_integration_settings');

// Renderizar a página de configurações de integrações
function trinitykitcms_render_integrations_page() {
?>
    <div class="wrap">
        <h1 style="margin-bottom: 30px; font-size: 24px; font-weight: bold;">Integrações</h1>

        <!-- Bloco de configurações do Asaas -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Configurações do Asaas</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_asaas_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Asaas Wallet ID</th>
                        <td>
                            <input type="text" name="trinitykitcms_asaas_wallet_id" value="<?php echo esc_attr(get_option('trinitykitcms_asaas_wallet_id')); ?>" class="regular-text">
                            <p class="description">ID da carteira Asaas.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Asaas API Key</th>
                        <td>
                            <input type="password" name="trinitykitcms_asaas_api_key" value="<?php echo esc_attr(get_option('trinitykitcms_asaas_api_key')); ?>" class="regular-text">
                            <p class="description">Chave de API do Asaas.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Asaas API URL</th>
                        <td>
                            <input type="url" name="trinitykitcms_asaas_api_url" value="<?php echo esc_attr(get_option('trinitykitcms_asaas_api_url')); ?>" class="regular-text">
                            <p class="description">URL base da API do Asaas. Use https://api-sandbox.asaas.com/v3 para ambiente de teste ou https://api.asaas.com/v3 para produção.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do Asaas'); ?>
            </form>
        </div>

        <!-- Bloco de configurações do Deepseek -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Configurações do Deepseek</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_deepseek_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Deepseek API Key</th>
                        <td>
                            <input type="password" name="trinitykitcms_deepseek_api_key" value="<?php echo esc_attr(get_option('trinitykitcms_deepseek_api_key')); ?>" class="regular-text">
                            <p class="description">Chave de API do Deepseek.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Deepseek Base URL</th>
                        <td>
                            <input type="url" name="trinitykitcms_deepseek_base_url" value="<?php echo esc_attr(get_option('trinitykitcms_deepseek_base_url')); ?>" class="regular-text">
                            <p class="description">URL base da API do Deepseek.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do Deepseek'); ?>
            </form>
        </div>
    </div>
<?php
}

// Adicionar submenu para integrações
function trinitykitcms_add_integrations_menu() {
    add_submenu_page(
        'trinitykitcms',
        'Integrações',
        'Integrações',
        'manage_options',
        'trinitykitcms-integrations',
        'trinitykitcms_render_integrations_page'
    );
}
add_action('admin_menu', 'trinitykitcms_add_integrations_menu'); 