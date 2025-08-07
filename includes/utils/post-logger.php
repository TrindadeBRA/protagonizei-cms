<?php
/**
 * Custom logging functionality for posts
 * 
 * @package TrinityKit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// trinitykit_add_post_log(
//     $order_id,
//     'nome-da-api-ou-funcao',
//     'Sua mensagem de log aqui',
//     'status_antigo', // opcional
//     'status_novo'    // opcional
// );

/**
 * Custom logging function for posts
 * 
 * @param int $post_id The post ID to add the log to
 * @param string $api_name The name of the API or function making the log
 * @param string $message The log message
 * @param string $old_status Optional old status for status changes
 * @param string $new_status Optional new status for status changes
 */
function trinitykit_add_post_log($post_id, $api_name, $message, $old_status = '', $new_status = '') {
    // Get current post content
    $post = get_post($post_id);
    $current_content = $post->post_content;
    
    // Create log entry
    $timestamp = current_time('mysql');
    $log_entry = "\n\n--- POST LOGGER ---\n";
    $log_entry .= "Data/Hora: $timestamp\n";
    $log_entry .= "API/Função: $api_name\n";
    
    if ($old_status && $new_status) {
        $log_entry .= "Status: $old_status -> $new_status\n";
    } elseif ($old_status) {
        $log_entry .= "Status: $old_status\n";
    } elseif ($new_status) {
        $log_entry .= "Status: $new_status\n";
    }
    
    $log_entry .= "Mensagem: $message\n";
    $log_entry .= "------------------------\n\n";
    
    // Add new log at the beginning of the content
    $new_content = $log_entry . $current_content;
    
    // Update post content
    wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $new_content
    ));
}