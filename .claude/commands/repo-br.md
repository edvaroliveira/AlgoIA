Cria um Pull Request no GitHub com os arquivos modificados do repositório em /Users/edvar/Documents/codes/IAProg.

Siga este fluxo obrigatório:

1. Valide o ambiente executando:
   - `git -C /Users/edvar/Documents/codes/IAProg rev-parse --show-toplevel`
   - `gh auth status`

2. Detecte as mudanças com:
   - `git -C /Users/edvar/Documents/codes/IAProg status --short`
   - `git -C /Users/edvar/Documents/codes/IAProg diff`
   - `git -C /Users/edvar/Documents/codes/IAProg diff --staged`

3. Com base no diff, gere automaticamente:
   - Mensagem de commit no padrão Conventional Commits (feat/fix/docs/refactor/chore)
   - Nome de branch descritivo em kebab-case
   - Título e corpo completo do PR em Markdown com tabela de arquivos

4. Apresente um resumo completo ao usuário e aguarde confirmação explícita antes de executar qualquer comando.

5. Após confirmação, execute em sequência:
   git -C /Users/edvar/Documents/codes/IAProg checkout -b <branch>
   git -C /Users/edvar/Documents/codes/IAProg add -A
   git -C /Users/edvar/Documents/codes/IAProg commit -m "<mensagem>"
   git -C /Users/edvar/Documents/codes/IAProg push -u origin <branch>
   gh pr create --base main --head <branch> --title "<título>" --body "<corpo>"

6. Exiba a URL do PR criado ao final.

Parâmetros opcionais: base=<branch> para mudar a branch de destino, draft para criar como rascunho.
