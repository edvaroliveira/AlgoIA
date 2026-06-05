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
- **Marca:** `--brand` #22844f · `--brand-dark` #173f31 · `--brand-ink` #15372b ·
  `--accent` #3a9a61 · `--brand-wash` rgba(34,132,79,.08).
- **Semântica:** `--success` #23774a · `--warning` #9a7a2f · `--error` #b65244 · `--info` #1b5d3f.
- **Raio:** `--radius-sm` .65rem (input/botão) · `--radius-md` 1rem (campo) ·
  `--radius-lg` 1.5rem (card/painel) · `--radius-pill` 999px.
- **Sombra:** `--shadow-sm` / `--shadow-md` / `--shadow-lg` (sutis, verde-tinta).
- **Fontes:** `--font-sans` "Avenir Next" · `--font-mono` p/ dados/chaves (tabular).

## Regras de uso
- **Cor sempre verde-família.** Nunca reintroduzir azul (`rgba(12,47,71)`,
  `rgba(216,227,232)` etc.) em sombra/borda/hover/foco.
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

## Responsivo
Cascata única e limpa: `991px` → `app-shell` vira coluna (`grid→1fr`), sidebar empilha;
`767px` → grids viram 1 coluna, tabelas com scroll-x. **Sem `display:block !important`**
e sem cascatas duplicadas (já removidas).

## Verificado
Preview estático (Playwright) em 1440px e 390px: layout coerente, cor verde unificada,
estados visíveis, responsivo empilhando sem quebra. — 2026-06-05.

## Próximos refinos sugeridos (ainda não feitos)
- Aplicar `--text-2` aos secundários hoje em `--text-muted` (hierarquia mais rica).
- `stat-card`/`overview-card` com storytelling (anel de progresso, delta de tendência)
  em vez de número-label puro.
- Avaliar input "inset" levemente mais escuro que o card (skill sugere; hoje é mais claro).
