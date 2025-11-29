# Merge de Texto na Imagem - Documentação Completa

## Visão Geral

O sistema de merge de texto na imagem (`webhook-assets-merge.php`) é responsável por mesclar o texto gerado para cada página do livro com a ilustração correspondente, criando as páginas finais do livro personalizado.

**Endpoint:** `GET /wp-json/trinitykitcms-api/v1/webhook/merge-assets`

**Processo:**
1. Busca pedidos com status `created_assets_illustration`
2. Para cada página do pedido, mescla o texto gerado com a ilustração
3. Salva a imagem mesclada na biblioteca de mídia do WordPress
4. Atualiza o status do pedido para `created_assets_merge`

---

## Opções de Posicionamento de Texto

O sistema suporta **8 posições diferentes** para colocar o texto sobre a imagem. A posição é definida no template do livro (campo `text_position` em cada página do template) e aplicada automaticamente durante o merge.

### Posições Disponíveis

#### 1. `top_right` (Superior Direito)
- **Área:** Metade direita da imagem, parte superior
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem (altura reduzida para posições top/bottom)
- **Uso:** Texto curto no canto superior direito

#### 2. `center_right` (Centro Direito) ⭐ Padrão
- **Área:** Metade direita da imagem, centralizada verticalmente
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~55% da altura da imagem (altura maior para posições center)
- **Uso:** Texto principal centralizado à direita (posição padrão)

#### 3. `bottom_right` (Inferior Direito)
- **Área:** Metade direita da imagem, parte inferior
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem
- **Uso:** Texto no canto inferior direito

#### 4. `top_left` (Superior Esquerdo)
- **Área:** Metade esquerda da imagem, parte superior
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem
- **Uso:** Texto no canto superior esquerdo

#### 5. `center_left` (Centro Esquerdo)
- **Área:** Metade esquerda da imagem, centralizada verticalmente
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~55% da altura da imagem
- **Uso:** Texto centralizado à esquerda

#### 6. `bottom_left` (Inferior Esquerdo)
- **Área:** Metade esquerda da imagem, parte inferior
- **Dimensões:**
  - **Largura:** 50% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem
- **Uso:** Texto no canto inferior esquerdo

#### 7. `top_center` (Superior Centralizado)
- **Área:** Largura completa da imagem, parte superior
- **Dimensões:**
  - **Largura:** 100% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem
- **Uso:** Texto longo no topo centralizado

#### 8. `bottom_center` (Inferior Centralizado)
- **Área:** Largura completa da imagem, parte inferior
- **Dimensões:**
  - **Largura:** 100% da largura da imagem (menos padding)
  - **Altura:** ~40% da altura da imagem
- **Uso:** Texto longo na parte inferior centralizado

### Fallback
Se uma posição desconhecida for encontrada, o sistema usa `center_right` como padrão.

---

## Cálculo Responsivo de Área de Texto

O sistema calcula dinamicamente a área de texto baseado nas dimensões da imagem:

### Padding Responsivo
- **Fórmula:** `max(30px, min(3% da largura, 3% da altura))`
- **Mínimo:** 30 pixels
- **Máximo:** 3% da menor dimensão (largura ou altura)

### Dimensões da Área

#### Largura
- **Posições left/right:** 50% da largura da imagem - (padding × 2)
- **Posições center:** 100% da largura da imagem - (padding × 2)

#### Altura
- **Posições top/bottom:** `(altura_imagem / 2.5) - padding` (~40% da altura)
- **Posições center:** `(altura_imagem / 1.8) - padding` (~55% da altura)

---

## Sistema de Fontes

O sistema suporta dois métodos de renderização de texto:

### 1. Fonte TTF (SourGummy.ttf) ⭐ Preferencial

**Caminho:** `wp-content/themes/[tema]/assets/fonts/SourGummy.ttf`

**Vantagens:**
- ✅ Suporte completo a UTF-8 (acentos, caracteres especiais)
- ✅ Tamanho de fonte exato e responsivo
- ✅ Melhor qualidade tipográfica
- ✅ Line height otimizado (140% do tamanho da fonte)

**Como funciona:**
- Se a fonte TTF for encontrada, é usada automaticamente
- Tamanho da fonte calculado dinamicamente baseado na área de texto
- Encoding UTF-8 nativo

### 2. Fonte Built-in (Fallback)

**Quando usado:**
- Quando a fonte TTF não está disponível no sistema

**Características:**
- Usa fonte built-in do PHP GD (fonte 5 - maior disponível)
- Escalonamento automático para aumentar tamanho
- Encoding ISO-8859-1 (pode ter problemas com acentos especiais)

**Escala:**
- Factor de escala: `max(2, intval(tamanho_desejado / 13))`
- Limite máximo: 4x o tamanho original

---

## Cálculo de Tamanho de Fonte

O tamanho da fonte é calculado dinamicamente baseado nas dimensões da área de texto:

### Fórmula
```php
$font_size = max(22, min($text_area_width / 18, $text_area_height / 10));
```

**Parâmetros:**
- **Tamanho mínimo:** 22 pixels (otimizado para SourGummy semi-bold)
- **Baseado em largura:** `largura_área / 18`
- **Baseado em altura:** `altura_área / 10`
- **Resultado:** Menor valor entre as duas opções, garantindo mínimo de 22px

**Exemplo:**
- Área de 900x400 pixels:
  - `900 / 18 = 50px`
  - `400 / 10 = 40px`
  - **Tamanho final:** 40px (menor valor, acima do mínimo)

---

## Processamento e Formatação de Texto

### Limpeza de Texto

1. **Remove tags HTML:** `strip_tags()`
2. **Decodifica entidades HTML:** `html_entity_decode()` (UTF-8)
3. **Remove espaços extras:** `trim()`

### Correção de Encoding

O sistema corrige automaticamente problemas comuns de encoding:

**Mapeamento de caracteres corrigidos:**
- `â€™` → `'`
- `â€œ` / `â€` → `"`
- `â€"` → `-`
- `Ã¡` → `á`, `Ã©` → `é`, `Ã­` → `í`, `Ã³` → `ó`, `Ãº` → `ú`
- `Ã ` → `à`, `Ãª` → `ê`, `Ã§` → `ç`, `Ã±` → `ñ`
- E outros caracteres especiais

### Validação de Encoding

- Verifica se o texto está em UTF-8: `mb_check_encoding()`
- Converte automaticamente se necessário: `mb_convert_encoding()`

---

## Word Wrapping (Quebra de Linha)

O sistema quebra automaticamente o texto em múltiplas linhas para caber na área disponível.

### Cálculo de Caracteres por Linha

#### Com Fonte TTF:
1. Calcula largura real de caracteres usando `imagettfbbox()`
2. Usa texto de amostra "Ag" para medir dimensões
3. Fórmula: `(largura_área - padding_responsivo) / largura_caractere`
4. Padding responsivo: `max(20px, 5% da largura da área)`

#### Com Fonte Built-in:
1. Usa `imagefontwidth()` × factor de escala
2. Calcula caracteres baseado na largura escalada

**Mínimo de caracteres por linha:** 8 (garantindo legibilidade mesmo em áreas pequenas)

### Lógica de Quebra

1. **Quebra por palavras:** Prioriza quebrar entre palavras
2. **Quebra de palavras longas:** Se uma palavra é muito longa, quebra ela também com hífen
3. **Alinhamento:** Texto sempre centralizado horizontalmente na área
4. **Centralização vertical:** Texto centralizado verticalmente na área disponível

---

## Estilização do Texto

### Cores

- **Texto principal:** Branco (`RGB: 255, 255, 255`)
- **Borda/Shadow:** Preto (`RGB: 0, 0, 0`)

### Borda Preta (Contorno)

**Tamanho:** 8 pixels ao redor de todo o texto

**Implementação:**
- Para TTF: Desenha o texto em preto em 8 pontos ao redor (a cada 45 graus)
- Para Built-in: Escala a borda proporcionalmente

**Objetivo:** Máximo contraste e legibilidade sobre qualquer fundo

### Line Height (Espaçamento Entre Linhas)

#### Com Fonte TTF:
- **140% do tamanho da fonte** (padrão tipográfico profissional)
- Exemplo: Fonte de 40px → Line height de 56px

#### Com Fonte Built-in:
- `altura_caractere + 8 pixels`

### Anti-aliasing

- **Ativado:** `imageantialias($image, true)`
- Melhora a qualidade visual do texto renderizado

---

## Formatos de Imagem Suportados

O sistema suporta os seguintes formatos de entrada:

1. **JPEG** (`image/jpeg`)
2. **PNG** (`image/png`)
3. **GIF** (`image/gif`)

### Formato de Saída

- **Formato:** JPEG
- **Qualidade:** 90% (balanço entre qualidade e tamanho de arquivo)

---

## Fluxo Completo do Processo

### 1. Disparo do Webhook
```
GET /wp-json/trinitykitcms-api/v1/webhook/merge-assets
```

### 2. Busca de Pedidos
- Busca todos os pedidos com status `created_assets_illustration`
- Para cada pedido, obtém:
  - Nome da criança
  - Template do livro
  - Páginas geradas (texto + ilustração)

### 3. Processamento de Cada Página

Para cada página do livro:

1. **Extrai dados:**
   - Texto gerado (`generated_text_content`)
   - ID da ilustração (`generated_illustration`)
   - Posição do texto do template (`text_position`)

2. **Mapeamento de posição:**
   - Converte valores legados (português) para inglês
   - Fallback para `center_right` se não encontrada

3. **Obtém arquivo de ilustração:**
   - Tenta obter caminho local do arquivo
   - Se não encontrado, baixa da URL do anexo
   - Cria arquivo temporário se necessário

4. **Limpa e prepara o texto:**
   - Remove HTML tags
   - Corrige encoding
   - Valida UTF-8

5. **Calcula área de texto:**
   - Baseado nas dimensões da imagem
   - Baseado na posição escolhida
   - Calcula padding responsivo

6. **Processa fonte:**
   - Verifica disponibilidade da fonte TTF
   - Calcula tamanho da fonte
   - Define método de renderização

7. **Quebra texto em linhas:**
   - Calcula caracteres por linha
   - Quebra por palavras
   - Trata palavras muito longas

8. **Renderiza texto na imagem:**
   - Desenha borda preta (8px)
   - Desenha texto branco por cima
   - Centraliza horizontal e verticalmente

9. **Salva imagem mesclada:**
   - Gera nome profissional
   - Salva como JPEG (90% qualidade)
   - Valida criação do arquivo

10. **Salva no WordPress:**
    - Cria attachment na biblioteca de mídia
    - Adiciona metadados profissionais
    - Retorna URL do anexo

11. **Atualiza pedido:**
    - Atualiza campo `final_page_with_text` no repeater
    - Limpa arquivos temporários

### 4. Atualização de Status

Se todas as páginas foram processadas com sucesso:
- Atualiza status do pedido para `created_assets_merge`
- Adiciona log de transição

### 5. Resposta do Webhook

```json
{
  "message": "Processamento de merge concluído. X pedidos processados.",
  "processed": 5,
  "total": 5,
  "errors": []
}
```

---

## Mapeamento de Posições Legadas

O sistema suporta valores antigos em português e converte automaticamente:

| Português (Legado) | Inglês (Atual) |
|-------------------|----------------|
| `direito_superior` | `top_right` |
| `direito_centralizado` | `center_right` |
| `direito_inferior` | `bottom_right` |
| `esquerda_superior` | `top_left` |
| `esquerda_centralizado` | `center_left` |
| `esquerda_inferior` | `bottom_left` |
| `superior_centralizado` | `top_center` |
| `inferior_centralizado` | `bottom_center` |

**Nota:** Valores em inglês também são aceitos diretamente.

---

## Metadados Salvos no WordPress

Cada imagem mesclada salva os seguintes metadados:

### Metadados do Post (Attachment)
- **Título:** `"Página Final Mesclada - {nome_criança} - Página {número}"`
- **Conteúdo:** Descrição completa com informações do pedido
- **Excerpt:** Resumo formatado
- **Status:** `inherit`

### Custom Fields (Post Meta)
- `_trinitykitcms_order_id`: ID do pedido
- `_trinitykitcms_child_name`: Nome da criança
- `_trinitykitcms_page_number`: Número da página (1-based)
- `_trinitykitcms_page_index`: Índice da página (0-based)
- `_trinitykitcms_text_position`: Posição do texto aplicada
- `_trinitykitcms_generation_method`: `text_image_merge`
- `_trinitykitcms_generation_date`: Data/hora da geração
- `_trinitykitcms_file_source`: `webhook_merge_assets`
- `_trinitykitcms_asset_type`: `final_merged_page`
- `_wp_attachment_image_alt`: Texto alternativo para acessibilidade

### Nome do Arquivo

Formato: `merged-pedido-{order_id}-{nome_criança}-pagina-{número}-{timestamp}.jpg`

Exemplo: `merged-pedido-123-João-Silva-pagina-1-2024-01-15_14-30-45.jpg`

---

## Requisitos Técnicos

### Extensões PHP Necessárias
- **GD Library:** Obrigatória para manipulação de imagens
- **Multibyte String (mbstring):** Para suporte UTF-8

### Permissões
- Diretório de uploads do WordPress deve ter permissão de escrita
- Diretório temporário do sistema deve ser acessível

### Dependências WordPress
- Advanced Custom Fields (ACF) para campos customizados
- WordPress REST API habilitada

---

## Tratamento de Erros

### Erros Comuns e Ações

1. **Fonte TTF não encontrada:**
   - ✅ Usa fonte built-in como fallback
   - ⚠️ Log de aviso no error_log

2. **Imagem não encontrada localmente:**
   - ✅ Tenta baixar da URL do anexo
   - ❌ Se falhar, pula a página e registra erro

3. **Texto vazio:**
   - ❌ Pula a página e registra erro

4. **Ilustração inválida:**
   - ❌ Pula a página e registra erro

5. **Erro ao salvar imagem:**
   - ❌ Pula a página, limpa arquivos temporários e registra erro

6. **Erro ao criar attachment:**
   - ❌ Pula a página e registra erro

### Validações Realizadas

- Verifica extensão GD carregada
- Valida informações da imagem (`getimagesize()`)
- Verifica se arquivo foi realmente criado e não está vazio
- Valida criação do attachment no WordPress

---

## Logs e Debug

O sistema gera logs detalhados para debug:

### Prefixo dos Logs
`[TrinityKit]`

### Informações Logadas

1. **Cálculo de área de texto:**
   - Posição utilizada
   - Coordenadas calculadas (x, y, width, height)

2. **Processamento de fonte:**
   - Caminho da fonte verificada
   - Confirmação de uso da fonte TTF
   - Tamanho da fonte calculado
   - Dimensões de caracteres

3. **Renderização:**
   - Aplicação de borda preta
   - Prévia do texto renderizado

4. **Erros:**
   - Todos os erros são registrados com contexto completo

### Visualizar Logs

No WordPress, os logs geralmente ficam em:
- Arquivo de erro do PHP (`error_log`)
- Ou definido pela configuração `WP_DEBUG_LOG`

---

## Exemplos de Uso

### Exemplo 1: Página com Texto Centralizado à Direita

```php
// Dados de entrada
$image_path = '/path/to/illustration.jpg';
$text = "Era uma vez, em uma terra distante, uma criança muito especial chamada João.";
$text_position = 'center_right';

// Resultado: Texto branco com borda preta, centralizado na metade direita da imagem
```

### Exemplo 2: Página com Texto no Topo Centralizado

```php
// Dados de entrada
$image_path = '/path/to/illustration.jpg';
$text = "O início da aventura...";
$text_position = 'top_center';

// Resultado: Texto na parte superior, ocupando toda a largura, centralizado
```

### Exemplo 3: Página com Texto no Canto Inferior Esquerdo

```php
// Dados de entrada
$image_path = '/path/to/illustration.jpg';
$text = "Fim.";
$text_position = 'bottom_left';

// Resultado: Texto pequeno no canto inferior esquerdo
```

---

## Configurações Ajustáveis

### No Código Fonte

As seguintes configurações podem ser ajustadas no código:

| Parâmetro | Localização | Valor Atual | Descrição |
|-----------|-------------|-------------|-----------|
| Padding mínimo | `calculate_text_area()` | `30px` | Padding mínimo da área de texto |
| Padding percentual | `calculate_text_area()` | `3%` | Padding baseado em % da menor dimensão |
| Altura top/bottom | `calculate_text_area()` | `/ 2.5` | Divisor para altura em posições top/bottom |
| Altura center | `calculate_text_area()` | `/ 1.8` | Divisor para altura em posições center |
| Tamanho fonte mínimo | `add_text_overlay_to_image()` | `22px` | Tamanho mínimo da fonte |
| Divisor largura fonte | `add_text_overlay_to_image()` | `/ 18` | Divisor para cálculo baseado em largura |
| Divisor altura fonte | `add_text_overlay_to_image()` | `/ 10` | Divisor para cálculo baseado em altura |
| Tamanho borda | `add_text_overlay_to_image()` | `8px` | Espessura da borda preta |
| Line height TTF | `add_text_overlay_to_image()` | `1.4` (140%) | Multiplicador de line height para TTF |
| Qualidade JPEG | `add_text_overlay_to_image()` | `90` | Qualidade de compressão JPEG (0-100) |
| Padding responsivo | `add_text_overlay_to_image()` | `max(20, 5%)` | Padding para cálculo de caracteres por linha |

---

## Boas Práticas

1. **Posição do Texto:**
   - Use `center_right` ou `center_left` para textos principais
   - Use `top_center` ou `bottom_center` para textos longos
   - Use cantos (`top_right`, `bottom_left`, etc.) para textos curtos

2. **Comprimento do Texto:**
   - Textos muito longos podem resultar em muitas linhas pequenas
   - Considere dividir textos muito longos em múltiplas páginas

3. **Fonte:**
   - Sempre mantenha a fonte TTF (`SourGummy.ttf`) disponível para melhor qualidade
   - A fonte deve estar no caminho correto: `assets/fonts/SourGummy.ttf`

4. **Imagens:**
   - Use imagens de alta resolução para melhor qualidade final
   - Formatos PNG ou JPEG são recomendados

5. **Testes:**
   - Teste diferentes posições para ver qual funciona melhor com cada ilustração
   - Verifique legibilidade do texto sobre diferentes fundos

---

## Resumo das Opções Disponíveis

### ✅ 8 Posições de Texto
1. `top_right`
2. `center_right` (padrão)
3. `bottom_right`
4. `top_left`
5. `center_left`
6. `bottom_left`
7. `top_center`
8. `bottom_center`

### ✅ 2 Sistemas de Fonte
1. TTF (SourGummy.ttf) - Preferencial
2. Built-in - Fallback

### ✅ Recursos de Estilização
- Texto branco
- Borda preta de 8px
- Anti-aliasing
- Centralização automática
- Word wrapping inteligente

### ✅ Formatação de Texto
- Remoção de HTML
- Correção de encoding
- Suporte UTF-8 completo
- Quebra de linha automática

### ✅ Formatos Suportados
- **Entrada:** JPEG, PNG, GIF
- **Saída:** JPEG (90% qualidade)

---

## Conclusão

O sistema de merge de texto na imagem é altamente configurável e oferece diversas opções para personalizar a apresentação do texto sobre as ilustrações. Com 8 posições diferentes, suporte a fontes TTF, e renderização inteligente, é possível criar páginas profissionais e legíveis para os livros personalizados.

Para mais informações sobre outros aspectos do sistema, consulte:
- `ciclo-vida-pedidos.md` - Ciclo de vida dos pedidos
- `transicoes-status-pedidos.md` - Transições de status
- `webhook-deliver-pdf.md` - Entrega de PDF final
