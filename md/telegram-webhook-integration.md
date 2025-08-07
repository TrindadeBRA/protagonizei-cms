# Integração do Telegram com Webhooks

## Webhook Email Thanks integrado com Telegram

### Funcionalidade
O webhook `/wp-json/trinitykitcms-api/v1/webhook/send-thanks` agora envia automaticamente uma notificação no grupo do Telegram após enviar emails de agradecimento para pedidos pagos.

### O que acontece:
1. ✅ Processa pedidos com status 'paid'
2. ✅ Envia email de agradecimento para o cliente
3. ✅ Atualiza status do pedido para 'thanked'
4. ✅ **NOVO:** Envia notificação no Telegram com:
   - Nome do cliente
   - E-mail do cliente  
   - Valor do pedido
   - ID do pedido
   - Data/hora do processamento
   - **Link direto para editar o pedido no admin**

### Formato da mensagem no Telegram:
```
✅ Email de Agradecimento Enviado!

📧 Cliente: João da Silva
💌 E-mail: joao@exemplo.com
💰 Valor: R$ 99,00
🔢 Pedido: #384
📅 Data: 15/01/2025 14:30:15

🔗 Ver Pedido no Admin
```

### URLs de Teste

#### Desenvolvimento (localhost)
```
http://localhost:8080/wp-json/trinitykitcms-api/v1/webhook/send-thanks
```

#### Produção
```
https://cms.protagonizei.com/wp-json/trinitykitcms-api/v1/webhook/send-thanks
```

### URL do Admin do Pedido
A URL gerada seguirá o padrão:
```
http://localhost:8080/wp-admin/post.php?post=384&action=edit
```
ou em produção:
```
https://cms.protagonizei.com/wp-admin/post.php?post=384&action=edit
```

### Como testar manualmente:

1. **Crie um pedido de teste** com status 'paid'
2. **Execute o webhook** via GET:
   ```bash
   curl "http://localhost:8080/wp-json/trinitykitcms-api/v1/webhook/send-thanks"
   ```
3. **Verifique**:
   - ✅ Email foi enviado
   - ✅ Status mudou para 'thanked'
   - ✅ Mensagem apareceu no grupo Telegram
   - ✅ Link do admin funciona

### Logs
Os logs incluem informações sobre:
- ✅ Sucesso ao enviar notificação Telegram
- ❌ Erros de notificação Telegram (não afeta o processo principal)
- 📝 Todos os logs ficam no error_log do WordPress

### Configuração necessária
Certifique-se de que as configurações do Telegram estão preenchidas em:
**WordPress Admin > Trinity Kit CMS > Integrações > Telegram**

### Tratamento de Erros
- Se o Telegram falhar, o processo continua normalmente
- Erros são logados mas não interrompem o envio de emails
- Sistema é resiliente - emails sempre têm prioridade

## Próximos passos
Este padrão pode ser aplicado a outros webhooks:
- `webhook-payment-confirm.php` - Confirmação de pagamento
- `api-create-order.php` - Criação de novos pedidos
- Qualquer outro endpoint que processe pedidos