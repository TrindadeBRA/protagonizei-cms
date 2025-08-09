<?php
/**
 * Email template for PDF delivery emails
 * 
 * @package TrinityKit
 */

if (!defined('ABSPATH')) {
    exit;
}

function trinitykit_get_delivery_email_template($name, $child_name, $order_id, $order_total, $pdf_url, $gender = 'menina') {
    $main_color = $gender === 'menina' ? '#f5349b' : '#357eff';
    $bg_color = $gender === 'menina' ? '#fdf0f8' : '#e8f4fd';
    
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
                <h2>🎉 Seu livro personalizado está pronto!</h2>
                <p>Olá ' . esc_html($name) . ',</p>
                <p>É com grande alegria que informamos que o livro personalizado de <strong>' . esc_html($child_name) . '</strong> foi finalizado com sucesso!</p>
                
                <div style="background-color: ' . $bg_color . '; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . $main_color . ';">
                    <h3>📚 Detalhes da Entrega:</h3>
                    <p><strong>Nome da Criança:</strong> <span style="color: ' . $main_color . '; font-weight: bold;">' . esc_html($child_name) . '</span></p>
                    <p><strong>Número do pedido:</strong> <span style="color: ' . $main_color . '; font-weight: bold;">#' . esc_html($order_id) . '</span></p>
                    <p><strong>Valor total:</strong> <span style="color: ' . $main_color . '; font-weight: bold;">R$ ' . number_format($order_total, 2, ',', '.') . '</span></p>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . esc_url($pdf_url) . '" style="background-color: ' . $main_color . '; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; display: inline-block;">
                        📖 Baixar Livro Personalizado
                    </a>
                </div>

                <div style="background-color: ' . $bg_color . '; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . $main_color . ';">
                    <h3>💡 Dicas para aproveitar ao máximo:</h3>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Salve o PDF em um local seguro do seu computador</li>
                        <li>Para melhor qualidade, use um leitor de PDF como Adobe Reader</li>
                        <li>Você pode imprimir o livro em casa ou em uma gráfica</li>
                        <li>Compartilhe com familiares e amigos!</li>
                    </ul>
                </div>

                <div style="height: 1px; background-color: ' . $main_color . '; margin: 20px 0;"></div>

                <p>Esperamos que <strong>' . esc_html($child_name) . '</strong> adore sua história personalizada! Cada página foi criada com muito carinho e dedicação.</p>
                
                <p>Se tiver alguma dúvida ou precisar de ajuda, não hesite em entrar em contato conosco.</p>
                
                <p style="margin-top: 30px; font-weight: bold; color: ' . $main_color . ';">
                    Obrigado por escolher a Protagonizei! 🎨✨
                </p>
            </div>
            <div style="text-align: center; padding: 20px; font-size: 12px; color: #666; margin-top: 20px;">
                <p>© ' . date('Y') . ' Protagonizei. Todos os direitos reservados.</p>
                <p>Este é um email automático, por favor não responda.</p>
            </div>
        </div>
    </body>
    </html>';
}
