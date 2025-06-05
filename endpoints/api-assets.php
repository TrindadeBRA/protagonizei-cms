<?php

// Verifica se o arquivo está sendo acessado diretamente
if (!defined('ABSPATH')) {
    exit; // Sai se acessado diretamente
}

function trinitykitcms_get_assets($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    // Obtém os assets diretamente usando os nomes das opções
    $assets = array(
        'company_presentation' => get_option('trinitykitcms_company_presentation_pdf', ''),
        'plastics_catalog' => get_option('trinitykitcms_plastics_catalog_pdf', ''),
        'rubber_catalog' => get_option('trinitykitcms_rubber_catalog_pdf', ''),
        'adhesives_catalog' => get_option('trinitykitcms_adhesives_catalog_pdf', ''),
    );

    return array(
        'success' => true,
        'data' => $assets
    );
}


/**
 * Registra os endpoints da API para assets.
 */
function trinitykitcms_register_assets_endpoints() {
    register_rest_route('trinitykitcms-api/v1', '/assets', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_assets',
            'permission_callback' => '__return_true'
        )
    ));
}
add_action('rest_api_init', 'trinitykitcms_register_assets_endpoints', 10); 