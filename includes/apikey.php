<?php

function trinitykitcms_validate_api_key($request) {
    // Verifica múltiplos locais para a API key
    $api_key = trinitykitcms_get_api_key_from_request($request);

    if (empty($api_key)) {
        return new WP_Error(
            'api_key_missing',
            'API Key não fornecida',
            array('status' => 401)
        );
    }

    $stored_key = get_option('trinitykitcms_api_key');

    if (empty($stored_key)) {
        return new WP_Error(
            'api_key_not_configured',
            'API Key não configurada no sistema',
            array('status' => 500)
        );
    }

    if (!hash_equals($stored_key, $api_key)) {
        return new WP_Error(
            'invalid_api_key',
            'API Key inválida',
            array('status' => 401)
        );
    }

    return true;
}

function trinitykitcms_get_api_key_from_request($request) {
    // Tenta obter do cabeçalho X-API-Key
    $api_key = $request->get_header('X-API-Key');

    // Se não encontrar no cabeçalho, tenta nos parâmetros
    if (empty($api_key)) {
        $api_key = $request->get_param('api_key');
    }

    return $api_key;
}

function trinitykitcms_generate_api_key() {
    $existing_key = get_option('trinitykitcms_api_key', '');
    
    if (empty($existing_key)) {
        $api_key = bin2hex(random_bytes(16));
        update_option('trinitykitcms_api_key', $api_key);
    }
}