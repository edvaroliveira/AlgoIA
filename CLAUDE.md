# Configuração do Claude Code — Projeto IAProg

## Comando `/repo-br` — Criar Pull Request no GitHub

Sempre que o usuário digitar `/repo-br` ou frases como "cria um PR", "abre pull request",
"versionar arquivos", "subir alterações para o GitHub", "commitar e abrir PR", siga o
fluxo abaixo obrigatoriamente e nesta ordem.

---

### 1. Validar ambiente

```bash
git -C /Users/edvar/Documents/codes/IAProg rev-parse --show-toplevel
gh auth status
git -C /Users/edvar/Documents/codes/IAProg branch --show-current
git -C /Users/edvar/Documents/codes/IAProg remote get-url origin
```

Se qualquer um falhar: informe o problema e interrompa.

---

### 2. Detectar arquivos modificados

```bash
git -C /Users/edvar/Documents/codes/IAProg status --short
```

Classifique: `M` = modificado · `A`/`??` = novo · `D` = deletado · `R` = renomeado.

Se não houver modificações: informe "Nenhuma alteração detectada." e encerre.

---

### 3. Ler o diff completo

```bash
git -C /Users/edvar/Documents/codes/IAProg diff --staged
git -C /Users/edvar/Documents/codes/IAProg diff
```

Para arquivos novos (untracked), leia o conteúdo com `cat <arquivo>`.

Com base no diff, gere automaticamente:

**a) Mensagem de commit** — padrão Conventional Commits:
```
<tipo>(<escopo>): <descrição curta em português>
```
Tipos aceitos: `feat`, `fix`, `docs`, `refactor`, `chore`, `test`, `style`, `perf`

**b) Nome da branch:**
```
<tipo>/<descricao-curta-kebab-case>
```

**c) Título do PR** — igual ao subject do commit

**d) Corpo do PR** em Markdown:
```markdown
## O que foi alterado
<resumo em 2-3 frases>

## Arquivos modificados
| Arquivo | Tipo de alteração |
|---------|------------------|
| path/arquivo.ext | Modificado / Novo / Removido |

## Detalhes das alterações
<descrição técnica baseada no diff>

## Como testar
<passos para verificar as mudanças>
```

---

### 4. Apresentar resumo e aguardar confirmação

Antes de executar qualquer comando git ou gh, mostre:

```
📋 RESUMO DO PR A SER CRIADO

🌿 Branch:   <nome-da-branch>
💬 Commit:   <mensagem-de-commit>
📁 Arquivos: <N> arquivo(s) — <X> modificados, <Y> novos
🎯 Base:     main

📝 Título: <título do PR>
📄 Corpo:  [exibe o corpo formatado]

Confirma? (responda "sim" para prosseguir ou sugira ajustes)
```

Não execute nada sem confirmação explícita do usuário.

---

### 5. Executar — somente após confirmação

```bash
git -C /Users/edvar/Documents/codes/IAProg checkout -b <branch>
git -C /Users/edvar/Documents/codes/IAProg add -A
git -C /Users/edvar/Documents/codes/IAProg commit -m "<mensagem>"
git -C /Users/edvar/Documents/codes/IAProg push -u origin <branch>
gh pr create --base main --head <branch> --title "<título>" --body "<corpo>"
```

---

### 6. Confirmar resultado

```
✅ Pull Request criado com sucesso!

🔗 URL:    <url do PR>
🌿 Branch: <nome-da-branch>
📦 Commit: <hash curto>
```

Se houver erro: mostre a mensagem completa e sugira como corrigir manualmente.

---

### Parâmetros opcionais aceitos após `/repo-br`

| Sintaxe | Comportamento |
|---------|--------------|
| `/repo-br` | Usa `/Users/edvar/Documents/codes/IAProg` |
| `/repo-br base=develop` | PR contra `develop` em vez de `main` |
| `/repo-br draft` | Cria como Draft PR |
| `/repo-br base=develop draft` | Combinação |

---

### Regras de segurança

- Nunca execute git add/commit/push ou gh pr create sem confirmação explícita
- Nunca force push sem avisar sobre os riscos
- Se a branch já existir no remote, pergunte se deve reutilizá-la ou criar outra
- Não inclua arquivos listados no `.gitignore`
