# Ciclo de Vida dos Pedidos - Sistema Protagonizei

Este documento detalha o ciclo de vida completo dos pedidos no sistema Protagonizei, incluindo todos os status possíveis, APIs e webhooks que manipulam cada etapa.

## Status dos Pedidos

O sistema possui 11 status principais para os pedidos:

| Status | Descrição | Label no Admin |
|--------|-----------|----------------|
| `created` | Pedido inicial criado | Criado |
| `awaiting_payment` | Aguardando confirmação do pagamento | Aguardando Pagamento |
| `paid` | Pagamento confirmado | Pago |
| `thanked` | Email de agradecimento enviado | Agradecido |
| `created_assets_text` | Assets de texto personalizados criados | Assets de Texto Criados |
| `created_assets_illustration` | Assets de ilustração criados | Assets de Ilustração Criados |
| `created_assets_merge` | Assets finais merged criados | Assets Finais Criados |
| `ready_for_delivery` | Produto pronto para entrega | Pronto para Entrega |
| `delivered` | Produto entregue ao cliente | Entregue |
| `completed` | Processo completo (entregue + PDF gerado) | Concluído (Entregue e PDF Gerado) |
| `error` | Status de erro | Erro |

## Fluxo Completo do Ciclo de Vida

### 1. **CREATED** → Criação do Pedido
**API:** `POST /wp-json/trinitykitcms-api/v1/orders`  
**Arquivo:** `endpoints/orders/api-create-order.php`

**O que faz:**
- Valida todos os dados do pedido (nome da criança, idade, gênero, tom de pele, dados do comprador)
- Faz upload da foto da criança
- Cria o post do tipo `orders`
- Associa automaticamente o modelo de livro mais recente
- Define status inicial como `created`
- Registra log da criação

**Dados salvos:**
- Dados da criança (nome, idade, gênero, tom de pele, foto)
- Dados do comprador (nome, email, telefone)
- Associação com modelo de livro

---

### 2. **AWAITING_PAYMENT** → Geração do PIX
**API:** `POST /wp-json/trinitykitcms-api/v1/orders/{order_id}/pix`  
**Arquivo:** `endpoints/orders/api-create-pix-payment.php`

**O que faz:**
- Integra com API do Asaas para criar QR Code PIX estático
- Valor fixo de R$ 49,99
- QR Code expira em 24 horas
- Atualiza status para `awaiting_payment`
- Registra log da geração do PIX

**Dados retornados:**
- ID da chave PIX
- Imagem do QR Code (base64)
- Código PIX para copiar e colar

---

### 3. **PAID** → Confirmação do Pagamento
**Webhook:** `POST /wp-json/trinitykitcms-api/v1/webhook/payment-confirm`  
**Arquivo:** `endpoints/orders/webhook-payment-confirm.php`

**Disparado por:** Sistema Asaas quando pagamento é confirmado

**O que faz:**
- Recebe webhook do Asaas com evento `PAYMENT_RECEIVED`
- Valida dados do pagamento
- Atualiza status para `paid`
- Salva ID da transação, data e valor do pagamento
- Remove dados do QR Code PIX (não mais necessários)
- Registra log da confirmação

**Dados salvos:**
- `payment_transaction_id`
- `payment_date`
- `payment_amount`

---

### 4. **THANKED** → Email de Agradecimento
**Webhook:** `GET /wp-json/trinitykitcms-api/v1/webhook/send-thanks`  
**Arquivo:** `endpoints/orders/webhook-email-thanks.php`

**Disparado por:** Sistema externo (provavelmente cron job)

**O que faz:**
- Busca todos os pedidos com status `paid`
- Envia email de agradecimento personalizado para cada comprador
- Usa template específico baseado no gênero da criança
- Atualiza status para `thanked`
- Registra log para cada email enviado

**Template de email:** `endpoints/orders/email/thank-you-template.php`

---

### 5. **CREATED_ASSETS_TEXT** → Geração de Textos Personalizados
**Webhook:** `GET /wp-json/trinitykitcms-api/v1/webhook/generate-text-assets`  
**Arquivo:** `endpoints/orders/webhook-assets-text.php`

**Disparado por:** Sistema externo (provavelmente cron job)

**O que faz:**
- Busca todos os pedidos com status `thanked`
- Para cada página do modelo de livro:
  - Pega o texto base (menino ou menina)
  - Usa API Deepseek para personalizar substituindo `{nome}` pelo nome da criança
  - Salva texto personalizado
- Atualiza status para `created_assets_text`
- Salva todas as páginas geradas no campo `generated_book_pages`

**Integração externa:** API Deepseek para processamento de texto com IA

---

### 6. **CREATED_ASSETS_ILLUSTRATION** → Geração de Ilustrações
**Webhook:** `GET /wp-json/trinitykitcms-api/v1/webhook/generate-image-assets`  
**Arquivo:** `endpoints/orders/webhook-assets-image.php`

**Disparado por:** Sistema externo (provavelmente cron job)

**O que faz:**
- Busca todos os pedidos com status `created_assets_text`
- Para cada página do modelo de livro:
  - Busca a ilustração base correta baseada no gênero e tom de pele da criança
  - Aplica face swap usando a foto da criança via API FaceSwap
  - Salva as ilustrações personalizadas no campo `generated_illustration`
- Atualiza status para `created_assets_illustration`

**Integração externa:** API FaceSwap para aplicação de face swap nas ilustrações

---

### 7. **CREATED_ASSETS_MERGE** → Merge dos Assets
**Webhook:** `GET /wp-json/trinitykitcms-api/v1/webhook/merge-assets`  
**Arquivo:** `endpoints/orders/webhook-assets-merge-pdf.php`

**Disparado por:** Sistema externo (provavelmente cron job)

**O que faz:**
- Busca todos os pedidos com status `created_assets_illustration`
- Para cada página gerada:
  - Combina texto personalizado com ilustração processada
  - Aplica o texto na metade direita da imagem horizontal
  - Usa fonte branca com sombra para boa legibilidade
  - Salva páginas finais no campo `merged_book_pages`
- Atualiza status para `created_assets_merge`
- Prepara dados para geração do PDF final

**Processamento:** Usa biblioteca GD do PHP para manipulação de imagens e sobreposição de texto

---

### 8. **READY_FOR_DELIVERY** → Pronto para Entrega
**Status:** Definido no sistema mas não implementado nos arquivos analisados

**Função esperada:**
- PDF final gerado e disponível
- Sistema pronto para entregar produto ao cliente

---

### 9. **DELIVERED** → Produto Entregue
**Status:** Definido no sistema mas não implementado nos arquivos analisados

**Função esperada:**
- Produto entregue ao cliente (email com PDF)
- Notificação de entrega enviada

---

### 10. **COMPLETED** → Processo Concluído
**Status:** Definido no sistema mas não implementado nos arquivos analisados

**Função esperada:**
- Processo totalmente finalizado
- PDF gerado e entregue com sucesso
- Status final do pedido

---

### 11. **ERROR** → Status de Erro
**Status:** Definido no sistema para casos de falha

**Usado quando:**
- Falhas na integração com APIs externas
- Erros no processamento de assets
- Problemas na geração do produto final

## APIs de Consulta

### Verificação de Status de Pagamento
**API:** `GET /wp-json/trinitykitcms-api/v1/orders/{order_id}/payment-status`  
**Arquivo:** `endpoints/orders/api-check-payment-status.php`

**O que faz:**
- Verifica se um pedido específico foi pago
- Retorna status atual do pedido
- Registra log da consulta se pedido estiver pago

## Campos Principais do Pedido

### Dados da Criança
- `child_name` - Nome da criança
- `child_age` - Idade (2-8 anos)
- `child_gender` - Gênero (menino/menina)
- `child_skin_tone` - Tom de pele (clara/media/escura)
- `child_face_photo` - Foto do rosto para face swap

### Dados do Comprador
- `buyer_name` - Nome do responsável
- `buyer_email` - Email para comunicações
- `buyer_phone` - Telefone de contato

### Dados do Pagamento
- `payment_transaction_id` - ID da transação Asaas
- `payment_date` - Data/hora do pagamento
- `payment_amount` - Valor pago

### Assets Gerados
- `generated_pdf_link` - Link do PDF final
- `generated_book_pages` - Array com páginas personalizadas
  - `generated_text_content` - Texto personalizado de cada página
  - `generated_illustration` - Ilustração personalizada de cada página
- `merged_book_pages` - Array com páginas finais mescladas
  - `page_number` - Número da página
  - `merged_content` - ID da imagem final com texto e ilustração combinados
  - `original_text` - Texto original usado na mesclagem
  - `original_illustration` - ID da ilustração original usada

## Integrações Externas

### Asaas (Pagamentos)
- **Função:** Processamento de pagamentos PIX
- **API:** Criação de QR Codes estáticos
- **Webhook:** Confirmação automática de pagamentos

### Deepseek (IA de Texto)
- **Função:** Personalização de textos do livro
- **Processo:** Substituição inteligente de placeholders
- **Modelo:** deepseek-chat

### Sistema de Logs
- **Função:** Rastreamento de todas as operações
- **Arquivo:** `includes/utils/post-logger.php`
- **Dados:** Ação, descrição, status anterior/novo, timestamp

## Notas de Implementação

1. **Status Não Implementados:** Os status `ready_for_delivery`, `delivered` e `completed` estão definidos no sistema mas não possuem implementação nos arquivos analisados.

2. **Automação:** O sistema usa webhooks para automação do fluxo, provavelmente executados por cron jobs externos.

3. **Rastreabilidade:** Cada operação é registrada no sistema de logs para auditoria completa.

4. **Validações:** Todos os endpoints possuem validações robustas de dados de entrada.

5. **Erro Handling:** Sistema preparado para lidar com falhas em integrações externas.