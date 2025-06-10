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
        <style>
            :root {
                --color-main: ' . $main_color . ';
            }
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                text-align: center;
                padding: 20px 0;
                background-color: white;
                border-radius: 8px 8px 0 0;
            }
            .logo {
                max-width: 200px;
                height: auto;
            }
            .content {
                background-color: #ffffff;
                padding: 30px;
                border-radius: 0 0 8px 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .footer {
                text-align: center;
                padding: 20px;
                font-size: 12px;
                color: #666666;
                margin-top: 20px;
            }
            .order-details {
                background-color: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid var(--color-main);
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background-color: var(--color-main);
                color: #ffffff;
                text-decoration: none;
                border-radius: 5px;
                margin: 20px 0;
                font-weight: bold;
            }
            .highlight {
                color: var(--color-main);
                font-weight: bold;
            }
            .divider {
                height: 1px;
                background: linear-gradient(to right, var(--color-main), var(--color-main));
                margin: 20px 0;
            }
            .social-links {
                margin-top: 20px;
            }
            .social-links a {
                color: var(--color-main);
                text-decoration: none;
                margin: 0 10px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://protagonizei.thetrinityweb.com.br/assets/images/navigation-logo.png" alt="Protagonizei Logo" class="logo">
            </div>
            <div class="content">
                <h2>Olá ' . esc_html($name) . ',</h2>
                <p>Agradecemos imensamente pela sua compra! Estamos muito felizes em tê-lo como cliente.</p>
                
                <div class="order-details">
                    <h3>Detalhes do seu pedido:</h3>
                    <p><strong>Número do pedido:</strong> <span class="highlight">#' . esc_html($order_id) . '</span></p>
                    <p><strong>Valor total:</strong> <span class="highlight">R$ ' . number_format($order_total, 2, ',', '.') . '</span></p>
                </div>

                <div class="divider"></div>

                <p>Seu pedido está sendo processado e você receberá atualizações em breve.</p>
                
                <p>Se tiver alguma dúvida, não hesite em entrar em contato conosco.</p>

            </div>
            <div class="footer">
                <p>© ' . date('Y') . ' Protagonizei. Todos os direitos reservados.</p>
                <p>Este é um email automático, por favor não responda.</p>
            </div>
        </div>
    </body>
    </html>';
} 