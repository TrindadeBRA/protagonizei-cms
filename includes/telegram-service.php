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
                    'error' => $resulSt->getDescription(),
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
}