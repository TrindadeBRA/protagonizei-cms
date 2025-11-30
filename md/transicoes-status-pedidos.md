# 🔄 Transições de Status - Guia Prático

## Como "Pular" de um Status para o Próximo

### 1️⃣ **INÍCIO** → **CREATED**
```http
POST /wp-json/trinitykitcms-api/v1/orders
Content-Type: multipart/form-data

{
  "childName": "Maria",
  "childAge": 5,
  "childGender": "girl", 
  "skinTone": "light",
  "parentName": "João Silva",
  "email": "joao@email.com",
  "phone": "(11) 99999-9999",
  "photo": [arquivo da foto]
}
```
**Resultado:** Status `created` + pedido #{id} criado

---

### 2️⃣ **CREATED** → **AWAITING_PAYMENT**
```http
POST /wp-json/trinitykitcms-api/v1/orders/{order_id}/pix
```
**Resultado:** 
- Status `awaiting_payment`
- QR Code PIX gerado (R$ 49,99, expira em 24h)
- Integração automática com Asaas

---

### 3️⃣ **AWAITING_PAYMENT** → **PAID** 
```http
POST /wp-json/trinitykitcms-api/v1/webhook/payment-confirm
Content-Type: application/json

{
  "event": "PAYMENT_RECEIVED",
  "payment": {
    "id": "pay_123456789",
    "externalReference": "{order_id}",
    "paymentDate": "2025-01-20T10:30:00",
    "value": 49.99
  }
}
```
**Quem chama:** Sistema Asaas (automático após pagamento)  
**Resultado:** Status `paid` + dados do pagamento salvos

---

### 4️⃣ **PAID** → **THANKED**
```http
GET /wp-json/trinitykitcms-api/v1/webhook/send-thanks
```
**Quem chama:** Cron job externo (automático)  
**Resultado:** 
- Status `thanked`
- Email de agradecimento enviado ao comprador

---

### 5️⃣ **THANKED** → **CREATED_ASSETS_TEXT**
```http
GET /wp-json/trinitykitcms-api/v1/webhook/generate-text-assets  
```
**Quem chama:** Cron job externo (automático)  
**Resultado:**
- Status `created_assets_text`
- Textos personalizados com nome da criança via IA Deepseek
- Campo `generated_book_pages` preenchido

---

### ⚠️ **TRANSIÇÕES NÃO IMPLEMENTADAS**

### 6️⃣ **CREATED_ASSETS_TEXT** → **CREATED_ASSETS_ILLUSTRATION**
```http
🔮 FUTURO: POST /wp-json/trinitykitcms-api/v1/webhook/generate-illustrations
```
**Funcionalidade esperada:**
- Face swap da foto da criança nas ilustrações
- Ajuste para tom de pele correto
- Salvar ilustrações personalizadas

---

### 7️⃣ **CREATED_ASSETS_ILLUSTRATION** → **CREATED_ASSETS_MERGE**
```http
🔮 FUTURO: POST /wp-json/trinitykitcms-api/v1/webhook/merge-assets
```
**Funcionalidade esperada:**
- Combinar textos + ilustrações
- Gerar páginas finais do livro

---

### 8️⃣ **CREATED_ASSETS_MERGE** → **READY_FOR_DELIVERY**
```http
🔮 FUTURO: POST /wp-json/trinitykitcms-api/v1/webhook/generate-pdf
```
**Funcionalidade esperada:**
- Gerar PDF final do livro personalizado
- Salvar link do PDF gerado

---

### 9️⃣ **READY_FOR_DELIVERY** → **DELIVERED**
```http
🔮 FUTURO: POST /wp-json/trinitykitcms-api/v1/webhook/deliver-product
```
**Funcionalidade esperada:**
- Enviar email com PDF para o cliente
- Confirmar entrega

---

### 🔟 **DELIVERED** → **COMPLETED**
```http
🔮 FUTURO: POST /wp-json/trinitykitcms-api/v1/webhook/complete-order
```
**Funcionalidade esperada:**
- Marcar processo como totalmente finalizado
- Limpeza de dados temporários

---

## 🔍 **API de Consulta**

### Verificar Status Atual
```http
GET /wp-json/trinitykitcms-api/v1/orders/{order_id}/payment-status
```
**Retorna:**
```json
{
  "message": "Pedido já foi pago",
  "status": "paid"
}
```

---

## 🤖 **Automação Atual**

### ✅ **Automático (implementado):**
- Confirmação de pagamento (webhook Asaas)
- Email de agradecimento (cron job)
- Geração de textos IA (cron job)

### ⏳ **Manual/Frontend (implementado):**
- Criação do pedido
- Geração do PIX
- Consulta de status

### ❌ **Não implementado:**
- Geração de ilustrações
- Merge de assets
- Geração de PDF
- Entrega do produto
- Finalização

---

## 💡 **Próximos Passos para Completar o Sistema**

1. **Implementar API de Face Swap** para ilustrações
2. **Criar endpoint de merge** de assets
3. **Desenvolver gerador de PDF** 
4. **Configurar sistema de entrega** via email
5. **Adicionar cron jobs** para automatizar os novos endpoints

O sistema atual está **50% completo** - funcionando até a geração de textos personalizados! 🎯