<?php

if (!defined('ABSPATH')) {
    exit; // Evita acesso direto ao arquivo
}

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

/**
 * Classe para gerenciar envio de mensagens via Telegram
 */
class TelegramService {
    private $telegram;
    private $bot_token;
    private $bot_username;
    private $chat_id;

    public function __construct() {
        $this->bot_token = get_option('trinitykitcms_telegram_bot_token');
        $this->bot_username = get_option('trinitykitcms_telegram_bot_username', 'ProtagonizeiBot');
        $this->chat_id = get_option('trinitykitcms_telegram_chat_id');

        // Inicializar apenas se o token estiver configurado
        if ($this->bot_token) {
            try {
                $this->telegram = new Telegram($this->bot_token, $this->bot_username);
            } catch (Exception $e) {
                error_log('Erro ao inicializar Telegram: ' . $e->getMessage());
            }
        }
    }

    /**
     * Verifica se o serviço está configurado corretamente
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->bot_token) && !empty($this->chat_id) && $this->telegram !== null;
    }

    /**
     * Envia uma mensagem de texto simples
     * 
     * @param string $message A mensagem a ser enviada
     * @param string|null $chat_id ID do chat (opcional, usa o padrão se não especificado)
     * @return array Resultado da operação
     */
    public function sendTextMessage($message, $chat_id = null) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telegram não está configurado corretamente'
            ];
        }

        $target_chat_id = $chat_id ?: $this->chat_id;

        try {
            $result = Request::sendMessage([
                'chat_id' => $target_chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            if ($result->isOk()) {
                return [
                    'success' => true,
                    'message_id' => $result->getResult()->getMessageId(),
                    'data' => $result->getResult()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result->getDescription(),
                    'error_code' => $result->getErrorCode()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia uma mensagem formatada em Markdown
     * 
     * @param string $message A mensagem em Markdown
     * @param string|null $chat_id ID do chat (opcional)
     * @return array Resultado da operação
     */
    public function sendMarkdownMessage($message, $chat_id = null) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telegram não está configurado corretamente'
            ];
        }

        $target_chat_id = $chat_id ?: $this->chat_id;

        try {
            $result = Request::sendMessage([
                'chat_id' => $target_chat_id,
                'text' => $message,
                'parse_mode' => 'MarkdownV2'
            ]);

            if ($result->isOk()) {
                return [
                    'success' => true,
                    'message_id' => $result->getResult()->getMessageId(),
                    'data' => $result->getResult()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result->getDescription(),
                    'error_code' => $result->getErrorCode()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia uma mensagem com botões inline
     * 
     * @param string $message Texto da mensagem
     * @param array $buttons Array de botões no formato [['text' => 'Botão', 'url' => 'https://...'], ...]
     * @param string|null $chat_id ID do chat (opcional)
     * @return array Resultado da operação
     */
    public function sendMessageWithButtons($message, $buttons, $chat_id = null) {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telegram não está configurado corretamente'
            ];
        }

        $target_chat_id = $chat_id ?: $this->chat_id;

        // Criar teclado inline
        $keyboard = [];
        foreach ($buttons as $button) {
            $keyboard[] = [$button];
        }

        try {
            $result = Request::sendMessage([
                'chat_id' => $target_chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
                'reply_markup' => [
                    'inline_keyboard' => $keyboard
                ]
            ]);

            if ($result->isOk()) {
                return [
                    'success' => true,
                    'message_id' => $result->getResult()->getMessageId(),
                    'data' => $result->getResult()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result->getDescription(),
                    'error_code' => $result->getErrorCode()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envia uma notificação de pedido
     * 
     * @param array $order_data Dados do pedido
     * @return array Resultado da operação
     */
    public function sendOrderNotification($order_data) {
        $message = "🛒 <b>Novo Pedido!</b>\n\n";
        $message .= "📧 <b>E-mail:</b> " . htmlspecialchars($order_data['email']) . "\n";
        $message .= "📱 <b>WhatsApp:</b> " . htmlspecialchars($order_data['whatsapp']) . "\n";
        $message .= "💰 <b>Valor:</b> R$ " . number_format($order_data['value'] / 100, 2, ',', '.') . "\n";
        $message .= "📅 <b>Data:</b> " . date('d/m/Y H:i:s') . "\n";
        
        if (!empty($order_data['product'])) {
            $message .= "🎁 <b>Produto:</b> " . htmlspecialchars($order_data['product']) . "\n";
        }
        
        if (!empty($order_data['order_id'])) {
            $message .= "🔢 <b>ID do Pedido:</b> " . htmlspecialchars($order_data['order_id']) . "\n";
        }

        return $this->sendTextMessage($message);
    }

    /**
     * Envia uma notificação de pagamento confirmado
     * 
     * @param array $payment_data Dados do pagamento
     * @return array Resultado da operação
     */
    public function sendPaymentConfirmation($payment_data) {
        $message = "✅ <b>Pagamento Confirmado!</b>\n\n";
        $message .= "📧 <b>E-mail:</b> " . htmlspecialchars($payment_data['email']) . "\n";
        $message .= "💰 <b>Valor:</b> R$ " . number_format($payment_data['value'] / 100, 2, ',', '.') . "\n";
        $message .= "📅 <b>Data:</b> " . date('d/m/Y H:i:s') . "\n";
        
        if (!empty($payment_data['payment_method'])) {
            $message .= "💳 <b>Método:</b> " . htmlspecialchars($payment_data['payment_method']) . "\n";
        }
        
        if (!empty($payment_data['order_id'])) {
            $message .= "🔢 <b>ID do Pedido:</b> " . htmlspecialchars($payment_data['order_id']) . "\n";
        }

        return $this->sendTextMessage($message);
    }

    /**
     * Testa a conexão enviando uma mensagem de teste
     * 
     * @return array Resultado do teste
     */
    public function testConnection() {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Configuração incompleta'
            ];
        }

        $test_message = "🤖 Teste de conexão realizado em " . date('d/m/Y H:i:s');
        return $this->sendTextMessage($test_message);
    }

    /**
     * Obtém informações sobre o bot
     * 
     * @return array Informações do bot
     */
    public function getBotInfo() {
        if (!$this->telegram) {
            return [
                'success' => false,
                'error' => 'Bot não inicializado'
            ];
        }

        try {
            $result = Request::getMe();
            
            if ($result->isOk()) {
                return [
                    'success' => true,
                    'bot_info' => $result->getResult()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result->getDescription()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}