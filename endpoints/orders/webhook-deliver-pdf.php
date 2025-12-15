<?php
/**
 * Webhook para envio do email de entrega do PDF e notificação de entrega
 * 
 * Este endpoint processa pedidos com status 'ready_for_delivery',
 * envia email para o cliente com o link do PDF final,
 * e notifica no Telegram sobre a entrega bem-sucedida.
 * 
 * Funcionalidades:
 * - Busca pedidos no status 'ready_for_delivery'
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
 * Registra o endpoint REST API para entrega de PDFs
 */
add_action('rest_api_init', function () {
    register_rest_route('trinitykitcms-api/v1', '/webhook/deliver-pdf', array(
        'methods' => 'GET',
        'callback' => 'trinitykit_handle_deliver_pdf_webhook',
        'permission_callback' => function () {
            return true;
        }
    ));
});

/**
 * Função principal do webhook de entrega de PDFs
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function trinitykit_handle_deliver_pdf_webhook($request) {
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }
    error_log("[TrinityKit] Iniciando webhook de entrega de PDFs");
    
    try {
        // Buscar pedidos no status 'ready_for_delivery'
        $orders = get_posts(array(
            'post_type' => 'orders',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'order_status',
                    'value' => 'ready_for_delivery',
                    'compare' => '='
                )
            )
        ));

        if (empty($orders)) {
            return new WP_REST_Response(array(
                'message' => 'Nenhum pedido encontrado com status ready_for_delivery',
                'processed' => 0,
                'total' => 0,
                'errors' => array()
            ), 200);
        }

        $processed = 0;
        $total = count($orders);
        $errors = array();

        error_log("[TrinityKit] Encontrados $total pedidos para entrega");

        foreach ($orders as $order) {
            $order_id = $order->ID;
            $child_name = get_field('child_name', $order_id);
            $buyer_name = get_field('buyer_name', $order_id);
            $buyer_email = get_field('buyer_email', $order_id);
            $order_total = get_field('payment_amount', $order_id);
            $gender = get_field('child_gender', $order_id);
            $pdf_url = get_field('generated_pdf_link', $order_id);
            
            error_log("[TrinityKit] Processando entrega do pedido #$order_id - $child_name");

            try {
                // Verificar se já foi entregue
                $current_status = get_field('order_status', $order_id);
                if ($current_status === 'delivered') {
                    error_log("[TrinityKit] Pedido #$order_id já foi entregue, pulando");
                    continue;
                }

                // Verificar campos obrigatórios
                if (empty($buyer_name) || empty($buyer_email) || empty($pdf_url)) {
                    $error_msg = "Pedido #$order_id: Campos obrigatórios vazios para entrega";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    continue;
                }
                
                // Validar email
                if (!is_email($buyer_email)) {
                    $error_msg = "Pedido #$order_id: Email inválido: $buyer_email";
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    continue;
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
                
                if ($sent) {
                    // Atualizar status do pedido para entregue
                    $status_updated = update_field('order_status', 'delivered', $order_id);
                    
                    if ($status_updated) {
                        // Adicionar log de progresso
                        trinitykit_add_post_log(
                            $order_id,
                            'webhook-deliver-pdf',
                            "Email de entrega enviado para $buyer_name - PDF de $child_name entregue com sucesso!",
                            'ready_for_delivery',
                            'delivered'
                        );
                        
                        // Enviar notificação do Telegram sobre entrega bem-sucedida
                        try {
                            $telegram = new TelegramService();
                            if ($telegram->isConfigured()) {
                                $order_url = get_permalink($order_id);
                                
                                $telegram_msg = "🎉 <b>ENTREGA REALIZADA COM SUCESSO!</b>\n\n";
                                $telegram_msg .= "📚 <b>Livro Personalizado Entregue</b>\n";
                                $telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                                $telegram_msg .= "📧 <b>Cliente:</b> " . htmlspecialchars($buyer_name) . "\n";
                                $telegram_msg .= "💌 <b>E-mail:</b> " . htmlspecialchars($buyer_email) . "\n";
                                $telegram_msg .= "💰 <b>Valor:</b> R$ " . number_format($order_total, 2, ',', '.') . "\n";
                                $telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n";
                                $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                                $telegram_msg .= "📅 <b>Data da Entrega:</b> " . $date->format('d/m/Y H:i:s') . "\n";
                                $telegram_msg .= "📎 <b>PDF:</b> <a href='" . esc_url($pdf_url) . "'>Ver PDF</a>\n\n";
                                $telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>Ver Pedido</a>\n\n";
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
                        
                        $processed++;
                        error_log("[TrinityKit] Entrega realizada com sucesso para pedido #$order_id");
                        
                    } else {
                        $error_msg = "Pedido #$order_id: Falha ao atualizar status para delivered";
                        error_log("[TrinityKit] $error_msg");
                        $errors[] = $error_msg;
                    }
                    
                } else {
                    global $phpmailer;
                    $error_msg = "Pedido #$order_id: Falha ao enviar email de entrega";
                    if (isset($phpmailer) && $phpmailer->ErrorInfo) {
                        $error_msg .= " - Erro PHPMailer: " . $phpmailer->ErrorInfo;
                    }
                    error_log("[TrinityKit] $error_msg");
                    $errors[] = $error_msg;
                    
                    // Notificar erro no Telegram
                    try {
                        $telegram = new TelegramService();
                        if ($telegram->isConfigured()) {
                            $order_url = get_permalink($order_id);
                            
                            $error_telegram_msg = "🚨 <b>ERRO NO ENVIO DE EMAIL DE ENTREGA</b>\n\n";
                            $error_telegram_msg .= "❌ <b>Falha ao enviar email</b>\n";
                            $error_telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
                            $error_telegram_msg .= "📧 <b>Cliente:</b> " . htmlspecialchars($buyer_name) . "\n";
                            $error_telegram_msg .= "🔢 <b>Pedido:</b> #" . $order_id . "\n\n";
                            $error_telegram_msg .= "⚠️ <b>AÇÃO NECESSÁRIA:</b>\n";
                            $error_telegram_msg .= "• Verificar configurações de email\n";
                            $error_telegram_msg .= "• Enviar manualmente se necessário\n\n";
                            $error_telegram_msg .= "🔗 <a href='" . esc_url($order_url) . "'>ABRIR PEDIDO</a>";
                            
                            $date = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                            $error_telegram_msg .= "\n\n📅 " . $date->format('d/m/Y H:i:s');
                            
                            $telegram->sendTextMessage($error_telegram_msg);
                        }
                    } catch (Exception $e) {
                        error_log("[TrinityKit] Erro ao enviar notificação Telegram de erro: " . $e->getMessage());
                    }
                }

            } catch (Exception $e) {
                $error_msg = "Pedido #$order_id: Erro inesperado na entrega - " . $e->getMessage();
                error_log("[TrinityKit] $error_msg");
                $errors[] = $error_msg;
                
                // Notificar erro no Telegram
                try {
                    $telegram = new TelegramService();
                    if ($telegram->isConfigured()) {
                        $order_url = get_permalink($order_id);
                        
                        $error_telegram_msg = "💥 <b>ERRO INESPERADO NA ENTREGA</b>\n\n";
                        $error_telegram_msg .= "❌ <b>Erro inesperado</b>\n";
                        $error_telegram_msg .= "👶 <b>Criança:</b> " . htmlspecialchars($child_name) . "\n";
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
            }
        }

        $message = "Processamento de entrega de PDFs concluído. $processed pedidos entregues.";
        error_log("[TrinityKit] $message");

        $response_data = array(
            'message' => $message,
            'processed' => $processed,
            'total' => $total,
            'errors' => $errors
        );

        $status_code = empty($errors) ? 200 : 500;
        return new WP_REST_Response($response_data, $status_code);

    } catch (Exception $e) {
        $error_msg = 'Erro inesperado no webhook de entrega de PDFs: ' . $e->getMessage();
        error_log("[TrinityKit] $error_msg");
        
        return new WP_REST_Response(array(
            'message' => $error_msg,
            'processed' => 0,
            'total' => 0,
            'errors' => array($error_msg)
        ), 500);
    }
}
