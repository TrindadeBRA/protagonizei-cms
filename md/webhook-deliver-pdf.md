# Webhook de Entrega de PDFs

## Visão Geral

O webhook `webhook-deliver-pdf.php` é responsável por automatizar o processo de entrega dos livros personalizados aos clientes. Ele processa pedidos que estão no status "ready_for_delivery" e realiza as seguintes ações:

1. **Envio de Email**: Envia email personalizado ao cliente com link para download do PDF
2. **Atualização de Status**: Muda o status do pedido para "delivered"
3. **Notificação Telegram**: Informa a equipe sobre a entrega bem-sucedida
4. **Logs de Progresso**: Registra todas as ações no sistema de logs

## Endpoint

```
GET /wp-json/trinitykitcms-api/v1/webhook/deliver-pdf
```

## Funcionamento

### 1. Busca de Pedidos
- Procura por pedidos com status `ready_for_delivery`
- Verifica se já não foram entregues anteriormente
- Valida campos obrigatórios (nome, email, link do PDF)

### 2. Processamento de Email
- Utiliza o template `delivery-template.php`
- Personaliza o email com dados da criança e do pedido
- Inclui link direto para download do PDF
- Envia com formatação HTML responsiva

### 3. Atualização de Status
- Muda status de `ready_for_delivery` para `delivered`
- Registra log de progresso no sistema
- Evita processamento duplicado

### 4. Notificações Telegram
- **Sucesso**: Notifica entrega bem-sucedida com detalhes completos
- **Erro de Email**: Alerta sobre falha no envio
- **Erro Geral**: Notifica erros inesperados

## Template de Email

O template `delivery-template.php` inclui:

- **Cabeçalho**: Logo da Protagonizei
- **Mensagem Principal**: Confirmação de entrega
- **Detalhes do Pedido**: Nome da criança, número do pedido, valor
- **Botão de Download**: Link direto para o PDF
- **Dicas de Uso**: Instruções para aproveitar o livro
- **Rodapé**: Informações de contato e direitos autorais

## Fluxo de Status

```
ready_for_delivery → [webhook-deliver-pdf] → delivered
```

## Configuração

### Pré-requisitos
- Pedido deve estar no status `ready_for_delivery`
- Campo `generated_pdf_link` deve estar preenchido
- Dados do cliente (nome e email) devem estar válidos
- TelegramService deve estar configurado

### Campos ACF Necessários
- `order_status`: Status atual do pedido
- `child_name`: Nome da criança
- `buyer_name`: Nome do comprador
- `buyer_email`: Email do comprador
- `payment_amount`: Valor do pedido
- `child_gender`: Gênero da criança
- `generated_pdf_link`: URL do PDF final

## Uso

### Execução Manual
```bash
curl "https://seudominio.com/wp-json/trinitykitcms-api/v1/webhook/deliver-pdf"
```

### Execução Automatizada
- Pode ser configurado como cron job
- Recomendado executar a cada 1-2 horas
- Verifica automaticamente novos pedidos prontos

### Monitoramento
- Logs são registrados com prefixo `[TrinityKit]`
- Notificações de erro são enviadas para o Telegram
- Status de retorno HTTP indica sucesso (200) ou erro (500)

## Tratamento de Erros

### Erros Comuns
1. **Email Inválido**: Cliente com email malformado
2. **PDF Não Gerado**: Link do PDF não encontrado
3. **Falha no Envio**: Problemas de configuração de email
4. **Erro de Status**: Falha ao atualizar status do pedido

### Ações Automáticas
- Erros são registrados no log do sistema
- Notificações são enviadas para o Telegram
- Pedidos com erro continuam no status anterior
- Sistema tenta processar outros pedidos

### Ações Manuais Necessárias
- Verificar configurações de email
- Enviar email manualmente se necessário
- Corrigir dados incorretos do pedido
- Verificar geração do PDF

## Exemplo de Resposta

### Sucesso
```json
{
    "message": "Processamento de entrega de PDFs concluído. 3 pedidos entregues.",
    "processed": 3,
    "total": 3,
    "errors": []
}
```

### Com Erros
```json
{
    "message": "Processamento de entrega de PDFs concluído. 2 pedidos entregues.",
    "processed": 2,
    "total": 3,
    "errors": [
        "Pedido #123: Email inválido: email@invalido"
    ]
}
```

## Integração com Outros Webhooks

- **webhook-generate-pdf**: Gera o PDF e define status `ready_for_delivery`
- **webhook-deliver-pdf**: Entrega o PDF e define status `delivered`
- **webhook-email-thanks**: Envia email de agradecimento inicial

## Segurança

- Endpoint público (sem autenticação)
- Validação de dados de entrada
- Sanitização de dados antes do envio
- Logs de todas as operações
- Tratamento de exceções

## Manutenção

### Verificações Regulares
- Logs de erro no sistema
- Notificações do Telegram
- Status dos pedidos processados
- Configurações de email

### Atualizações
- Template de email pode ser personalizado
- Mensagens do Telegram podem ser ajustadas
- Lógica de validação pode ser expandida
- Novos campos podem ser adicionados

## Suporte

Para dúvidas ou problemas:
1. Verificar logs do sistema
2. Consultar notificações do Telegram
3. Verificar status dos pedidos no admin
4. Testar endpoint manualmente
5. Verificar configurações de email
