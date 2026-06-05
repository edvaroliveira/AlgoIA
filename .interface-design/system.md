# Sistema de Design — AlgoIA

Plataforma educacional de exercícios de programação com correção assistida por IA.
Papéis: aluno, professor, admin. Stack: PHP + Bootstrap 5.3 + `public/assets/css/app.css`
(arquivo único de design system; as views compartilham as classes — refinar o CSS propaga
para todas as 32 views).

## Direção e feel
**Verde-acadêmico, calmo e confiável.** Mundo: lousa/giz de sala, caderno, papel marfim,
verdes institucionais da Amazônia (manual de identidade AlgoIA 2025). Não é terminal frio,
não é marketing. O aluno abre às 7h para ler enunciado, responder e ver veredito da IA.

## Intent (referência para cada componente)
- **Quem:** aluno/professor em contexto acadêmico, foco em ler e responder sem ruído.
- **O que:** ler enunciado → responder → ver feedback correto/errado por questão.
- **Sentir:** sóbrio, com respiro, contraste limpo, presença institucional.

## Estratégia de profundidade
**UMA só: sombras sutis + tint de superfície.** Sombras tingidas de verde-tinta
(`rgba(23,63,49,...)`), nunca azul. Bordas sussurram. Sem drop-shadows dramáticos.
Squint test: hierarquia perceptível por superfície/sombra leve, nada salta.

## Tokens (`:root` em app.css) — fonte única de verdade
- **Espaçamento:** base 4px → `--space-1..7` (.25rem → 3rem).
- **Superfícies (elevação por lightness, mesmo matiz quente):**
  `--surface-base` #f8f6ee (canvas) → `--surface-1` #fdfbf5 (card) →
  `--surface-2` #fffefa (elevado: dropdown/pill/topo) → `--surface-sunken` #f1eee4 (inset).
- **Texto 4 níveis:** `--text` #21362c · `--text-2` #4a5b51 · `--text-muted` #6c7c73 ·
  `--text-faint` #9aa69d (placeholder/disabled).
- **Bordas progressivas:** `--border` #dde1d5 · `--border-soft` rgba(35,57,47,.07) ·
  `--border-strong` rgba(35,57,47,.16) · `--focus-ring` rgba(34,132,79,.26).
- **Controles:** `--control-bg` #fffdf8 · `--control-border` #d2d8c9.
- **Marca:** `--brand` #22844f · `--brand-dark` #173f31 · `--brand-darker` #102e24 (stop
  profundo de gradiente) · `--brand-ink` #15372b · `--accent` #3a9a61 ·
  `--accent-bright` #65b07c (realce claro em gradiente, ex.: status dot) ·
  `--accent-label` #4c9e69 (verde de kicker/sobrelinha) · `--brand-wash` rgba(34,132,79,.08).
- **Semântica:** `--success` #23774a · `--warning` #9a7a2f · `--error` #b65244 · `--info` #1b5d3f.
- **Raio:** `--radius-sm` .65rem (input/botão) · `--radius-md` 1rem (campo) ·
  `--radius-lg` 1.5rem (card/painel) · `--radius-pill` 999px.
- **Sombra:** `--shadow-sm` / `--shadow-md` / `--shadow-lg` (sutis) ·
  `--shadow-hero` (elevação forte de hero/guest). Todas verde-tinta. **Nenhuma sombra/cor
  hardcoded fora do `:root`** (auditado 2026-06-05).
- **Fontes:** `--font-sans` "Avenir Next" · `--font-mono` p/ dados/chaves (tabular).

## Regras de uso
- **Cor sempre verde-família.** Nunca reintroduzir azul (`rgba(12,47,71)`,
  `rgba(216,227,232)`, `#0c4f79`, `#4d6573` etc.) em qualquer lugar — incl. badges/chips
  semânticos. `.badge--info` é verde (`#e4f1ea`/`#1b5d3f`), `.badge--neutral` é cinza quente
  (`#f1eee4`/`#5e6d64`). (Eram azuis; corrigido 2026-06-05.)
- Borda = sempre `var(--border*)`; sombra = sempre `var(--shadow-*)`.
- Inputs usam tokens de controle (`--control-bg`/`--control-border`), não os de superfície.
- Sidebar mesmo mundo do conteúdo: separa por borda, não por cor disruptiva.

## Estados (obrigatórios em interativos)
- **Botões:** `:hover` (por variante), `:active` (translateY 1px), `:disabled`
  (opacity .55, not-allowed), `:focus-visible` (anel `--focus-ring`).
- **Inputs:** `:hover` (borda forte), `:focus` (anel `--focus-ring`), `:disabled`
  (fundo sunken), `::placeholder` (`--text-faint`).
- **Links/nav/summary:** `:focus-visible` com anel.

## Assinaturas do produto (manter e destacar)
- **Chave de acesso da turma** em mono grande (`.big-key`, `.overview-card__value--mono`,
  `.form-input--key` com letter-spacing).
- **Feedback da IA por questão:** `.answer-card--correct/--wrong` (borda lateral 6px) +
  `.feedback-block--ok/--err` (fundo tingido verde/vermelho suave).
- **Linhas de tabela por status:** `.table-row--compatible/--incompatible`.
- **Deadline-badge** e **hero-panel** por papel (`--teacher`/`--student`).
- **Identidade do usuário na topbar** (`.topbar-user`): chip pill `--surface-2` no canto
  superior direito, junto do selo de modo (`.topbar-actions`). Avatar circular
  (`.topbar-user__avatar`, `--radius-pill`) — foto se houver, senão inicial em gradiente
  `--brand→--accent`. O chip (`.topbar-user__identity`) linka para `/conta`. **Não** voltar a
  pôr esse card no rodapé da sidebar (afundava em páginas longas).
- **Foto de perfil** (`/conta`, `.account-avatar` 120px round): mesmo slot circular da topbar;
  upload reencodado para quadrado (inicial é o fallback). Mesma estética em ambos os lugares.

## Responsivo
Cascata única e limpa: `991px` → `app-shell` vira coluna (`grid→1fr`), sidebar empilha;
`767px` → grids viram 1 coluna, tabelas com scroll-x. **Sem `display:block !important`**
e sem cascatas duplicadas (já removidas).

## Verificado
Preview estático (Playwright) em 1440px e 390px: layout coerente, cor verde unificada,
estados visíveis, responsivo empilhando sem quebra. — 2026-06-05.

Audit 2026-06-05 (2ª passada): zero azul restante; badges `--info`/`--neutral` recolorados
para verde/cinza-quente; todos os verdes/sombras decorativos tokenizados no `:root`
(`--brand-darker`, `--accent-bright`, `--accent-label`, `--shadow-hero`). Sem hex/sombra
hardcoded fora do `:root`.

## Valores dinâmicos sob CSP (sem `unsafe-inline`)
`style-src` não tem mais `'unsafe-inline'` (audit S4). Nunca usar `style="..."` inline nas
views. Para valor dinâmico de CSS (ex.: percentual de um anel), renderizar `data-*` no HTML e
setar a custom property por **`el.style.setProperty('--x', ...)`** em `app.js` — o caminho
CSSOM por propriedade é permitido pela CSP; `setAttribute('style')`/`cssText` **não**.
Utilitárias para layout: `.form-group--end`, `.u-inline`, `.u-mt-2`, `.row-between`.

## Próximos refinos sugeridos
- ✅ **Anel de pontuação** (`.score-ring`) na tela de resultado do aluno: preenchimento
  `score/máximo` via `conic-gradient(var(--ring-color) calc(var(--pct)*1%), var(--surface-sunken))`,
  cor por faixa (≥60 `--success`, ≥40 `--warning`, abaixo `--error`), número central na mesma
  cor. `--pct`/`--ring-color` vêm do JS (ver seção acima). Padrão para futuras métricas com razão.
- Estender o mesmo storytelling a `overview-card` **onde houver razão** (X de Y); contadores
  absolutos seguem número-label.
- Aplicar `--text-2` aos secundários hoje em `--text-muted` (hierarquia mais rica).
- ✅ **Input inset:** `--control-bg` agora `#f6f3ea` (mais escuro que o card `#fdfbf5`),
  sinalizando "recebe conteúdo"; `:focus` levanta para `#fffefb`. Antes era mais claro que o card.
