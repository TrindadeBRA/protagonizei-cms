<?php
/**
 * API para entrega individual de pedido
 * 
 * Este endpoint processa um pedido específico com status 'ready_for_delivery',
 * envia email para o cliente com o link do PDF final,
 * e notifica no Telegram sobre a entrega bem-sucedida.
 * 
 * Funcionalidades:
 * - Busca um pedido específico por ID
 * - Valida se está no status 'ready_for_delivery'
 * - Envia email personalizado com link do PDF
 * - Atualiza status do pedido para 'delivered'
 * - Notifica no Telegram sobre a entrega
 * - Registra logs de progresso
 * 
 * @author TrinityKit Team
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/includes/integrations.php';

/**
 * Registra o endpoint REST API para entrega individual de PDF
 */
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/orders/(?P<order_id>\d+)/deliver', array(
        'methods' => 'POST',
        'callback' => 'trinitykit_handle_deliver_single_order',
        'permission_callback' => function () {
            return true;
        },
        'args' => array(
            'order_id' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            )
        )
    ));
});

/**
 * Função principal para entrega individual de pedido
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function trinitykit_handle_deliver_single_order($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    
    $order_id = intval($request['order_id']);
    error_log("[TrinityKit] Iniciando entrega individual do pedido #$order_id");
    
    try {
        // Verificar se o pedido existe
        $order = get_post($order_id);
        if (!$order || $order->post_type !== 'orders') {
            return new WP_REST_Response(array(
                'code' => 'order_not_found',
                'message' => 'Pedido não encontrado',
                'order_id' => $order_id
            ), 404);
        }

        // Verificar status do pedido
        $current_status = get_field('order_status', $order_id);
        
        if ($current_status === 'delivered' || $current_status === 'completed') {
            return new WP_REST_Response(array(
                'code' => 'already_delivered',
                'message' => 'Pedido já foi entregue anteriormente',
                'order_id' => $order_id,
                'current_status' => $current_status
            ), 400);
        }

        if ($current_status !== 'ready_for_delivery') {
            return new WP_REST_Response(array(
                'code' => 'invalid_status',
                'message' => 'Pedido não está pronto para entrega. Status atual: ' . $current_status,
                'order_id' => $order_id,
                'current_status' => $current_status,
                'required_status' => 'ready_for_delivery'
            ), 400);
        }

        // Obter dados do pedido
        $child_name = get_field('child_name', $order_id);
        $buyer_name = get_field('buyer_name', $order_id);
        $buyer_email = get_field('buyer_email', $order_id);
        $order_total = get_field('payment_amount', $order_id);
        $gender = get_field('child_gender', $order_id);
        $pdf_url = get_field('generated_pdf_link', $order_id);
        
        error_log("[TrinityKit] Processando entrega do pedido #$order_id - $child_name");

        // Verificar campos obrigatórios
        if (empty($buyer_name) || empty($buyer_email) || empty($pdf_url)) {
            return new WP_REST_Response(array(
                'code' => 'missing_data',
                'message' => 'Dados obrigatórios ausentes para entrega',
                'order_id' => $order_id,
                'missing' => array(
                    'buyer_name' => empty($buyer_name),
                    'buyer_email' => empty($buyer_email),
                    'pdf_url' => empty($pdf_url)
                )
            ), 400);
        }
        
        // Validar email
        if (!is_email($buyer_email)) {
            return new WP_REST_Response(array(
                'code' => 'invalid_email',
                'message' => 'Email inválido: ' . $buyer_email,
                'order_id' => $order_id
            ), 400);
        }
        
        // Garantir que order_total seja numérico
        $order_total = is_numeric($order_total) ? floatval($order_total) : 0;
        
        // Obter template de email
        require_once __DIR__ . '/email/delivery-template.php';
        $subject = '🎉 Seu livro personalizado está pronto para download!';
        $message = trinitykit_get_delivery_email_template(
            $buyer_name, 
            $child_name, 
            $order_id, 
            $order_total, 
            $pdf_url, 
            $gender
        );
        
        // Enviar email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Protagonizei <noreply@protagonizei.com>'
        );
        
        $sent = wp_mail($buyer_email, $subject, $message, $headers);
        
        if (!$sent) {
            global $phpmailer;
            $error_details = '';
            if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                $error_details = $phpmailer->ErrorInfo;
            }
            
            error_log("[TrinityKit] Falha ao enviar email para pedido #$order_id: $error_details");
            
            // Notificar erro no Telegram
            try {
                $telegram = new TelegramService();
                if ($telegram->isConfigured()) {
                    $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                    
                    $error_telegram_msg = "🚨 <b>ERRO NO ENVIO DE EMAIL DE ENTREGA</b>\n\n";
                    $error_telegram_msg .= "❌ <b>Falha ao enviar email (Entrega Individual)</b>\n";
                    $error_telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                    $error_telegram_msg .= "📧 <b>Cliente:</b> " . htmlspecialchars($buyer_name) . "\n";
                    $error_telegram_msg .= "💌 <b>E-mail:</b> " . htmlspecialchars($buyer_email) . "\n";
                    $error_telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n";
                    if ($error_details) {
                        $error_telegram_msg .= "🔍 <b>Erro:</b> " . htmlspecialchars($error_details) . "\n";
                    }
                    $error_telegram_msg .= "\n⚠️ <b>AÇÃO NECESSÁRIA:</b>\n";
                    $error_telegram_msg .= "• Verificar configurações de email\n";
                    $error_telegram_msg .= "• Tentar novamente ou enviar manualmente\n\n";
                    $error_telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO</a>";
                    
                    $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                    $error_telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                    
                    $telegram->sendTextMessage($error_telegram_msg);
                }
            } catch (Exception $e) {
                error_log("[TrinityKit] Erro ao enviar notificação Telegram de erro: " . $e->getMessage());
            }
            
            return new WP_REST_Response(array(
                'code' => 'email_send_failed',
                'message' => 'Falha ao enviar email de entrega',
                'order_id' => $order_id,
                'error_details' => $error_details
            ), 500);
        }
        
        // Atualizar status do pedido para entregue
        $status_updated = update_field('order_status', 'delivered', $order_id);
        
        if (!$status_updated) {
            return new WP_REST_Response(array(
                'code' => 'status_update_failed',
                'message' => 'Email enviado, mas falha ao atualizar status para delivered',
                'order_id' => $order_id
            ), 500);
        }
        
        // Adicionar log de progresso
        trinitykit_add_post_log(
            $order_id,
            'api-deliver-single-order',
            "Email de entrega enviado para $buyer_name - PDF de $child_name entregue com sucesso! (Entrega Individual)",
            'ready_for_delivery',
            'delivered'
        );
        
        // Enviar notificação do Telegram sobre entrega bem-sucedida
        try {
            $telegram = new TelegramService();
            if ($telegram->isConfigured()) {
                $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                
                $telegram_msg = "🎉 <b>ENTREGA INDIVIDUAL REALIZADA COM SUCESSO!</b>\n\n";
                $telegram_msg .= "📚 <b>Livro Personalizado Entregue</b>\n";
                $telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                $telegram_msg .= "📧 <b>Cliente:</b> " . htmlspecialchars($buyer_name) . "\n";
                $telegram_msg .= "💌 <b>E-mail:</b> " . htmlspecialchars($buyer_email) . "\n";
                $telegram_msg .= "💰 <b>Valor:</b> R$ " . number_format($order_total, 2, ',', '.') . "\n";
                $telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n";
                $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                $telegram_msg .= "📅 <b>Data da Entrega:</b> " . $date->format('d/m/Y H:i:s') . "\n";
                $telegram_msg .= "📎 <b>PDF:</b> <a href='" . esc_url($pdf_url) . "'>Ver PDF</a>\n\n";
                $telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>Ver Pedido no Admin</a>\n\n";
                $telegram_msg .= "✅ <b>Status:</b> Entregue com sucesso!";
                
                $telegram_result = $telegram->sendTextMessage($telegram_msg);
                
                if ($telegram_result['success']) {
                    error_log("[TrinityKit] Notificação Telegram de entrega enviada para pedido #$order_id");
                } else {
                    error_log("[TrinityKit] Erro ao enviar notificação Telegram de entrega para pedido #$order_id: " . $telegram_result['error']);
                }
            }
        } catch (Exception $e) {
            error_log("[TrinityKit] Erro na notificação Telegram de entrega para pedido #$order_id: " . $e->getMessage());
        }
        
        error_log("[TrinityKit] Entrega individual realizada com sucesso para pedido #$order_id");
        
        return new WP_REST_Response(array(
            'message' => 'Pedido entregue com sucesso',
            'order_id' => $order_id,
            'child_name' => $child_name,
            'buyer_name' => $buyer_name,
            'buyer_email' => $buyer_email,
            'pdf_url' => $pdf_url,
            'status' => 'delivered'
        ), 200);

    } catch (Exception $e) {
        $error_msg = "Erro inesperado na entrega do pedido #$order_id: " . $e->getMessage();
        error_log("[TrinityKit] $error_msg");
        
        // Notificar erro no Telegram
        try {
            $telegram = new TelegramService();
            if ($telegram->isConfigured()) {
                $order_url = home_url("/wp-admin/post.php?post={$order_id}&action=edit");
                
                $error_telegram_msg = "💥 <b>ERRO INESPERADO NA ENTREGA INDIVIDUAL</b>\n\n";
                $error_telegram_msg .= "❌ <b>Erro inesperado</b>\n";
                $error_telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n";
                $error_telegram_msg .= "🔍 <b>Erro:</b> " . $e->getMessage() . "\n\n";
                $error_telegram_msg .= "⚠️ <b>VERIFICAÇÃO MANUAL NECESSÁRIA</b>\n\n";
                $error_telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO</a>";
                
                $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                $error_telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                
                $telegram->sendTextMessage($error_telegram_msg);
            }
        } catch (Exception $telegram_e) {
            error_log("[TrinityKit] Erro ao enviar notificação Telegram de erro: " . $telegram_e->getMessage());
        }
        
        return new WP_REST_Response(array(
            'code' => 'unexpected_error',
            'message' => $error_msg,
            'order_id' => $order_id
        ), 500);
    }
}

