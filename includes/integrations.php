<?php

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto ao arquivo
}

// Registrar as configurações de integrações
function trinitykitcms_register_integration_settings() {
    // Registrar configurações do Asaas
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_wallet_id', 'sanitize_text_field');
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_pix_key', 'sanitize_text_field');
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_asaas_settings', 'trinitykitcms_asaas_api_url', 'sanitize_url');

    // Registrar configurações do Deepseek
    register_setting('trinitykitcms_deepseek_settings', 'trinitykitcms_deepseek_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_deepseek_settings', 'trinitykitcms_deepseek_base_url', 'sanitize_url');

    // Registrar configurações do FaceSwap
    register_setting('trinitykitcms_faceswap_settings', 'trinitykitcms_faceswap_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_faceswap_settings', 'trinitykitcms_faceswap_base_url', 'sanitize_url');

    // Registrar configurações do Telegram
    register_setting('trinitykitcms_telegram_settings', 'trinitykitcms_telegram_bot_token', 'sanitize_text_field');
    register_setting('trinitykitcms_telegram_settings', 'trinitykitcms_telegram_chat_id', 'sanitize_text_field');
    register_setting('trinitykitcms_telegram_settings', 'trinitykitcms_telegram_bot_username', 'sanitize_text_field');

    // Registrar configurações do SMTP
    register_setting('trinitykitcms_smtp_settings', 'trinitykitcms_smtp_host', 'sanitize_text_field');
    register_setting('trinitykitcms_smtp_settings', 'trinitykitcms_smtp_username', 'sanitize_text_field');
    register_setting('trinitykitcms_smtp_settings', 'trinitykitcms_smtp_password', 'sanitize_text_field');

    // Registrar configurações do FAL.AI
    register_setting('trinitykitcms_falai_settings', 'trinitykitcms_falai_api_key', 'sanitize_text_field');
    register_setting('trinitykitcms_falai_settings', 'trinitykitcms_falai_base_url', 'sanitize_url');
    register_setting('trinitykitcms_falai_settings', 'trinitykitcms_falai_prompt', 'sanitize_textarea_field');
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
                        <th scope="row">Chave PIX (Asaas)</th>
                        <td>
                            <input type="text" name="trinitykitcms_asaas_pix_key" value="<?php echo esc_attr(get_option('trinitykitcms_asaas_pix_key')); ?>" class="regular-text">
                            <p class="description">Sua chave PIX cadastrada no Asaas (e-mail, CPF/CNPJ, telefone ou chave aleatória).</p>
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
            <h2 style="display: flex; align-items: center; gap: 10px;">Configurações do Deepseek - <a href="https://platform.deepseek.com/usage" target="_blank">Uso</a></h2>
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

        <!-- Bloco de configurações do FaceSwap -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2 style="display: flex; align-items: center; gap: 10px;">Configurações do FaceSwap - <a href="https://api.market/store/magicapi/faceswap-image-v3" target="_blank">FaceSwap V3</a></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_faceswap_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">FaceSwap API Key</th>
                        <td>
                            <input type="password" name="trinitykitcms_faceswap_api_key" value="<?php echo esc_attr(get_option('trinitykitcms_faceswap_api_key')); ?>" class="regular-text">
                            <p class="description">Chave de API do FaceSwap.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">FaceSwap Base URL</th>
                        <td>
                            <input type="url" name="trinitykitcms_faceswap_base_url" value="<?php echo esc_attr(get_option('trinitykitcms_faceswap_base_url')); ?>" class="regular-text">
                            <p class="description">URL base da API do FaceSwap.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do FaceSwap'); ?>
            </form>
        </div>

        <!-- Bloco de configurações do Telegram -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Configurações do Telegram</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_telegram_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Bot Token</th>
                        <td>
                            <input type="password" name="trinitykitcms_telegram_bot_token" value="<?php echo esc_attr(get_option('trinitykitcms_telegram_bot_token')); ?>" class="regular-text">
                            <p class="description">Token do bot fornecido pelo BotFather (ex: 123456789:ABCdefGHIjklMNOpqr-STUvwxYZ).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Chat ID do Grupo</th>
                        <td>
                            <input type="text" name="trinitykitcms_telegram_chat_id" value="<?php echo esc_attr(get_option('trinitykitcms_telegram_chat_id')); ?>" class="regular-text">
                            <p class="description">ID do chat/grupo onde as mensagens serão enviadas (ex: -1234567890).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Nome do Bot</th>
                        <td>
                            <input type="text" name="trinitykitcms_telegram_bot_username" value="<?php echo esc_attr(get_option('trinitykitcms_telegram_bot_username')); ?>" class="regular-text">
                            <p class="description">Nome de usuário do bot (ex: MeuBot). Opcional, usado para referência.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do Telegram'); ?>
            </form>
        </div>

        <!-- Bloco de configurações do SMTP -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Configurações do SMTP</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_smtp_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Host SMTP</th>
                        <td>
                            <input type="text" name="trinitykitcms_smtp_host" value="<?php echo esc_attr(get_option('trinitykitcms_smtp_host')); ?>" class="regular-text">
                            <p class="description">Servidor SMTP (ex: email-smtp.us-east-1.amazonaws.com).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Usuário SMTP</th>
                        <td>
                            <input type="text" name="trinitykitcms_smtp_username" value="<?php echo esc_attr(get_option('trinitykitcms_smtp_username')); ?>" class="regular-text">
                            <p class="description">Nome de usuário SMTP.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Senha SMTP</th>
                        <td>
                            <input type="password" name="trinitykitcms_smtp_password" value="<?php echo esc_attr(get_option('trinitykitcms_smtp_password')); ?>" class="regular-text">
                            <p class="description">Senha SMTP.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do SMTP'); ?>
            </form>
        </div>

        <!-- Bloco de configurações do FAL.AI -->
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2 style="display: flex; align-items: center; gap: 10px;">Configurações do FAL.AI - <a href="https://fal.ai/dashboard/usage-billing/credits" target="_blank">Créditos</a></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('trinitykitcms_falai_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">FAL.AI API Key</th>
                        <td>
                            <input type="password" name="trinitykitcms_falai_api_key" value="<?php echo esc_attr(get_option('trinitykitcms_falai_api_key')); ?>" class="regular-text">
                            <p class="description">Chave de API do FAL.AI.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">FAL.AI Base URL</th>
                        <td>
                            <input type="url" name="trinitykitcms_falai_base_url" value="<?php echo esc_attr(get_option('trinitykitcms_falai_base_url')); ?>" class="regular-text">
                            <p class="description">URL base da API do FAL.AI. Use <strong>https://fal.run</strong> (recomendado).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Prompt FAL.AI <span style="color: red;">*</span></th>
                        <td>
                            <textarea name="trinitykitcms_falai_prompt" rows="6" class="large-text" required><?php echo esc_textarea(get_option('trinitykitcms_falai_prompt', '')); ?></textarea>
                            <p class="description"><strong>Obrigatório:</strong> Prompt usado para processamento de face edit com FAL.AI. O webhook retornará erro se este campo estiver vazio.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Salvar Configurações do FAL.AI'); ?>
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