# Janela e limite de tentativas pertencem à Publicação, não ao Exercício

Abertura, encerramento e limite de tentativas ficam em `exercise_turmas` (a Publicação), não em `exercises`. O mesmo Exercício é usado em turmas com calendários diferentes, e cada turma precisa do próprio prazo e do próprio número de tentativas. Por isso, "encerrar um exercício" não é um estado do Exercício: é antecipar o `closes_at` de todas as suas Publicações. Bloquear uma Questão na moderação faz a mesma coisa.

## Consequences

- As colunas `exercises.turma_id`, `opens_at`, `closes_at` e `max_attempts`, assim como Tentativas com `turma_id NULL`, são resíduo do modelo anterior (migration 003) e não devem receber código novo. A remoção está registrada em `docs/backlog_revisao_2026_10_17.md`.
- Toda consulta de acesso do aluno precisa partir da Publicação e da Turma ativa, nunca só do Exercício.
