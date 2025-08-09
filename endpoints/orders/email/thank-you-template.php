<?php
/**
 * Email template for thank you emails
 * 
 * @package TrinityKit
 */

if (!defined('ABSPATH')) {
    exit;
}

function trinitykit_get_thank_you_email_template($name, $order_id, $order_total, $gender = 'menina') {
    $main_color = $gender === 'menina' ? '#f5349b' : '#357eff';
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; padding: 20px 0; background-color: white; border-radius: 8px 8px 0 0;">
                <img src="https://protagonizei.com/assets/images/navigation-logo.png" alt="Protagonizei Logo" style="max-width: 200px; height: auto;">
            </div>
            <div style="background-color: #ffffff; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h2>Olá ' . esc_html($name) . ',</h2>
                <p>Agradecemos imensamente pela sua compra! Estamos muito felizes em tê-lo como cliente.</p>
                
                <div style="background-color: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . $main_color . ';">
                    <h3>Detalhes do seu pedido:</h3>
                    <p><strong>Número do pedido:</strong> <span style="color: ' . $main_color . '; font-weight: bold;">#' . esc_html($order_id) . '</span></p>
                    <p><strong>Valor total:</strong> <span style="color: ' . $main_color . '; font-weight: bold;">R$ ' . number_format($order_total, 2, ',', '.') . '</span></p>
                </div>

                <div style="height: 1px; background-color: ' . $main_color . '; margin: 20px 0;"></div>

                <p>Seu pedido está sendo processado e você receberá atualizações em breve.</p>
                
                <p>Se tiver alguma dúvida, não hesite em entrar em contato conosco.</p>
            </div>
            <div style="text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px;">
                <p>© ' . date('Y') . ' Protagonizei. Todos os direitos reservados.</p>
                <p>Este é um email automático, por favor não responda.</p>
            </div>
        </div>
    </body>
    </html>';
}
