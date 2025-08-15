# Dashboard Protagonizei CMS

## 📊 Visão Geral

Dashboard interativo criado para acompanhar métricas de vendas, cupons e estatísticas dos pedidos da Protagonizei. O dashboard substitui a página inicial (home) e oferece uma visão completa do negócio.

## 🚀 Funcionalidades Implementadas

### 📈 Métricas Principais
- **Total de Vendas**: Número total de pedidos criados
- **Receita Total**: Soma dos valores de pedidos pagos (R$)
- **Cupons Utilizados**: Quantidade total de cupons aplicados
- **Ticket Médio**: Valor médio por pedido pago

### 📊 Gráficos Interativos
1. **Pedidos por Status** (Gráfico de Rosca)
   - Visualização da distribuição dos pedidos por status
   - Cores personalizadas para cada status
   
2. **Receita por Mês** (Gráfico de Linha)
   - Evolução da receita ao longo do tempo
   - Apenas pedidos pagos são considerados

3. **Top Cupons Utilizados**
   - Ranking dos cupons mais usados
   - Mostra quantidade de usos e receita gerada

4. **Funil de Conversão**
   - Visualização do processo: Criado → Aguardando Pagamento → Pago → Concluído
   - Barras de progresso com percentuais

### 🔍 Filtros e Funcionalidades
- **Filtros por Data**: Data início e fim
- **Filtro por Status**: Todos os status disponíveis
- **Atualização em Tempo Real**: Botão para refresh dos dados
- **Tabela de Pedidos Recentes**: Últimos 10 pedidos com links diretos

## 🛠️ Arquivos Criados/Modificados

### Novos Arquivos
1. **`endpoints/api-dashboard-stats.php`**
   - Endpoint REST API para fornecer dados do dashboard
   - Rota: `/wp-json/protagonizei/v1/dashboard/stats`
   - Suporta filtros por data e status

2. **`dashboard-home.php`**
   - Interface principal do dashboard
   - Layout responsivo com Tailwind CSS
   - Integração com Alpine.js e Chart.js

3. **`assets/dashboard.js`**
   - Lógica JavaScript do dashboard
   - Gerenciamento de estado com Alpine.js
   - Configuração dos gráficos Chart.js

4. **`assets/dashboard.css`**
   - Estilos personalizados
   - Animações e efeitos visuais
   - Responsividade mobile

### Arquivos Modificados
1. **`functions.php`**
   - Adicionado require para o novo endpoint
   
2. **`index.php`**
   - Redirecionamento para o dashboard

## 🎨 Tecnologias Utilizadas

- **Frontend**: HTML5, Tailwind CSS, Alpine.js
- **Gráficos**: Chart.js
- **Backend**: PHP, WordPress REST API
- **Database**: WordPress + ACF (Advanced Custom Fields)
- **Icons**: Font Awesome

## 📱 Responsividade

O dashboard é totalmente responsivo e funciona em:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (320px - 767px)

## 🔐 Segurança

- Verificação de permissões: apenas usuários com `edit_posts`
- Validação de nonce em formulários
- Sanitização de dados de entrada
- Escape de dados de saída

## 📊 Dados Utilizados

O dashboard utiliza os seguintes campos ACF dos pedidos:
- `order_status`: Status do pedido
- `payment_amount`: Valor do pagamento
- `applied_coupon`: Cupom aplicado
- `buyer_name`: Nome do comprador
- `child_name`: Nome da criança
- `payment_date`: Data do pagamento
- `book_template`: Template do livro

## 🚀 Como Usar

1. Acesse a página inicial do tema (será redirecionado automaticamente)
2. Use os filtros para refinar os dados exibidos
3. Clique em "Atualizar" para refresh manual dos dados
4. Navegue pelos gráficos e métricas
5. Acesse pedidos específicos através da tabela

## 🔄 Atualizações Futuras Sugeridas

- [ ] Exportação de relatórios em PDF
- [ ] Notificações push para novos pedidos
- [ ] Comparação entre períodos
- [ ] Métricas de performance de templates
- [ ] Integração com Google Analytics
- [ ] Dashboard mobile dedicado

## 🐛 Solução de Problemas

### Gráficos não aparecem
- Verifique se o Chart.js está carregando
- Confirme se há dados na resposta da API

### Dados não carregam
- Verifique permissões do usuário
- Confirme se o endpoint está registrado
- Verifique logs de erro do WordPress

### Layout quebrado
- Confirme se o Tailwind CSS está carregando
- Verifique se não há conflitos de CSS

## 📞 Suporte

Para dúvidas ou problemas, verifique:
1. Logs de erro do WordPress
2. Console do navegador para erros JavaScript
3. Network tab para problemas de API

---

**Desenvolvido para Protagonizei CMS** 🎯
