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
    
    // Obter URL do front-end para o livro interativo
    // Se houver múltiplas URLs separadas por vírgula, usa apenas a primeira
    $frontend_urls = get_option('trinitykitcms_frontend_app_url', 'https://protagonizei.com');
    $frontend_urls_array = array_map('trim', explode(',', $frontend_urls));
    $frontend_url = rtrim($frontend_urls_array[0], '/');
    $interactive_book_url = $frontend_url . '/play?id=' . esc_attr($order_id);
    
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

                <div style="text-align: center; margin: 40px 0;">
                    <p style="margin-bottom: 25px; font-weight: bold; color: #333; font-size: 18px;">Escolha como deseja visualizar seu livro:</p>
                    
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 550px; margin: 0 auto;">
                        <tr>
                            <td align="center" style="padding: 0 8px 15px 8px; vertical-align: top;">
                                <a href="' . esc_url($interactive_book_url) . '" style="background-color: ' . $main_color . '; color: white; padding: 20px 25px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 15px; display: block; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease; min-width: 200px;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">📱</div>
                                    <div style="line-height: 1.4;">Visualizar<br>Livro Interativo</div>
                                </a>
                            </td>
                            <td align="center" style="padding: 0 8px 15px 8px; vertical-align: top;">
                                <a href="' . esc_url($pdf_url) . '" style="background-color: #ffffff; color: ' . $main_color . '; padding: 20px 25px; text-decoration: none; border-radius: 12px; font-weight: bold; font-size: 15px; display: block; border: 2px solid ' . $main_color . '; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-width: 200px;">
                                    <div style="font-size: 28px; margin-bottom: 8px;">📖</div>
                                    <div style="line-height: 1.4;">Baixar PDF<br>do Livro</div>
                                </a>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background-color: ' . $bg_color . '; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid ' . $main_color . ';">
                    <h3>💡 Dicas para aproveitar ao máximo:</h3>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><strong>Livro Interativo:</strong> Experimente a versão interativa com animações e efeitos de virar páginas - perfeito para tablets e computadores!</li>
                        <li><strong>PDF para Download:</strong> Salve o PDF em um local seguro do seu computador para imprimir quando quiser</li>
                        <li>Para melhor qualidade do PDF, use um leitor como Adobe Reader</li>
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
