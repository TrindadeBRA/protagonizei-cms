<?php

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto ao arquivo
}

// Adicionar submenu no menu TrinityKitCMS
function trinitykitcms_add_assets_submenu() {
    add_submenu_page(
        'trinitykitcms', // slug do menu pai
        'Assets', // título da página
        'Assets', // título no menu
        'manage_options', // capacidade necessária
        'trinitykitcms-assets', // slug do submenu
        'trinitykitcms_render_assets_page' // função de callback
    );
}
add_action('admin_menu', 'trinitykitcms_add_assets_submenu');

// Registrar as configurações
function trinitykitcms_register_assets_settings() {
    register_setting('trinitykitcms_assets_settings', 'trinitykitcms_company_presentation_pdf');
    register_setting('trinitykitcms_assets_settings', 'trinitykitcms_plastics_catalog_pdf');
    register_setting('trinitykitcms_assets_settings', 'trinitykitcms_rubber_catalog_pdf');
    register_setting('trinitykitcms_assets_settings', 'trinitykitcms_adhesives_catalog_pdf');
}
add_action('admin_init', 'trinitykitcms_register_assets_settings');

// Renderizar a página de assets
function trinitykitcms_render_assets_page() {
    // Verificar se o usuário tem permissão
    if (!current_user_can('manage_options')) {
        return;
    }

    // Salvar as URLs dos arquivos quando o formulário for enviado
    if (isset($_POST['submit'])) {
        if (isset($_POST['company_presentation'])) {
            update_option('trinitykitcms_company_presentation_pdf', esc_url_raw($_POST['company_presentation']));
        }
        if (isset($_POST['plastics_catalog'])) {
            update_option('trinitykitcms_plastics_catalog_pdf', esc_url_raw($_POST['plastics_catalog']));
        }
        if (isset($_POST['rubber_catalog'])) {
            update_option('trinitykitcms_rubber_catalog_pdf', esc_url_raw($_POST['rubber_catalog']));
        }
        if (isset($_POST['adhesives_catalog'])) {
            update_option('trinitykitcms_adhesives_catalog_pdf', esc_url_raw($_POST['adhesives_catalog']));
        }
    }

    // Obter os valores salvos
    $company_presentation = get_option('trinitykitcms_company_presentation_pdf', '');
    $plastics_catalog = get_option('trinitykitcms_plastics_catalog_pdf', '');
    $rubber_catalog = get_option('trinitykitcms_rubber_catalog_pdf', '');
    $adhesives_catalog = get_option('trinitykitcms_adhesives_catalog_pdf', '');
?>
    <div class="wrap">
        <h1 style="margin-bottom: 30px; font-size: 24px; font-weight: bold;">Assets</h1>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
            <h2>Gerenciamento de Arquivos</h2>
            <p>Configure os arquivos PDF que serão disponibilizados no site.</p>
            
            <form method="post" action="">
                <?php settings_fields('trinitykitcms_assets_settings'); ?>
                
                <table class="form-table">
                    <!-- Apresentação da Empresa -->
                    <tr>
                        <th scope="row">Apresentação da Empresa</th>
                        <td>
                            <input type="text" id="company_presentation" name="company_presentation" 
                                value="<?php echo esc_attr($company_presentation); ?>" class="regular-text">
                            <input type="button" class="button button-secondary" value="Selecionar PDF" 
                                onclick="uploadPDF('company_presentation')">
                            <?php if ($company_presentation): ?>
                                <a href="<?php echo esc_url($company_presentation); ?>" target="_blank" class="button button-secondary">
                                    Visualizar PDF
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Catálogo de Plásticos & Elastômeros -->
                    <tr>
                        <th scope="row">Catálogo de Plásticos & Elastômeros</th>
                        <td>
                            <input type="text" id="plastics_catalog" name="plastics_catalog" 
                                value="<?php echo esc_attr($plastics_catalog); ?>" class="regular-text">
                            <input type="button" class="button button-secondary" value="Selecionar PDF" 
                                onclick="uploadPDF('plastics_catalog')">
                            <?php if ($plastics_catalog): ?>
                                <a href="<?php echo esc_url($plastics_catalog); ?>" target="_blank" class="button button-secondary">
                                    Visualizar PDF
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Catálogo de Borrachas -->
                    <tr>
                        <th scope="row">Catálogo de Borrachas</th>
                        <td>
                            <input type="text" id="rubber_catalog" name="rubber_catalog" 
                                value="<?php echo esc_attr($rubber_catalog); ?>" class="regular-text">
                            <input type="button" class="button button-secondary" value="Selecionar PDF" 
                                onclick="uploadPDF('rubber_catalog')">
                            <?php if ($rubber_catalog): ?>
                                <a href="<?php echo esc_url($rubber_catalog); ?>" target="_blank" class="button button-secondary">
                                    Visualizar PDF
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Catálogo de Adesivos -->
                    <tr>
                        <th scope="row">Catálogo de Adesivos</th>
                        <td>
                            <input type="text" id="adhesives_catalog" name="adhesives_catalog" 
                                value="<?php echo esc_attr($adhesives_catalog); ?>" class="regular-text">
                            <input type="button" class="button button-secondary" value="Selecionar PDF" 
                                onclick="uploadPDF('adhesives_catalog')">
                            <?php if ($adhesives_catalog): ?>
                                <a href="<?php echo esc_url($adhesives_catalog); ?>" target="_blank" class="button button-secondary">
                                    Visualizar PDF
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Salvar Alterações'); ?>
            </form>
        </div>
    </div>

    <script>
    function uploadPDF(fieldId) {
        var mediaUploader;
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Selecionar PDF',
            button: {
                text: 'Usar este PDF'
            },
            multiple: false,
            library: {
                type: 'application/pdf'
            }
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            document.getElementById(fieldId).value = attachment.url;
        });

        mediaUploader.open();
    }
    </script>
<?php
}

// Adicionar suporte ao media uploader
function trinitykitcms_assets_admin_scripts() {
    if (isset($_GET['page']) && $_GET['page'] === 'trinitykitcms-assets') {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'trinitykitcms_assets_admin_scripts'); 