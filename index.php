<?php
/**
 * Página inicial - redireciona para o dashboard
 * 
 * @package TrinityKit
 */

// Evita acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Inclui o dashboard
include(get_template_directory() . '/dashboard-home.php');