# Backlog — Revisão de modelo de domínio 2026-10-17

Revisão do modelo de domínio e da divergência entre a SPEC/PRD de 2026-05-27 e o
código atual. Ela não repete a auditoria de segurança de 2026-09-23. Os termos
vêm de [GLOSSARY.md](../GLOSSARY.md) e as decisões de [docs/adr/](adr/).

Linha de base: `php bin/run_tests.php` (63 testes) e `php bin/smoke_static.php` passando.

## Resumo

| ID | Severidade | Tema | Status |
|----|-----------|------|--------|
| RD-01 | 🟠 Médio | "Recusar" apaga Matrícula ativa e audita mesmo sem efeito | ⬜ Aberto |
| RD-02 | 🟡 Baixo | Colunas legadas de janela/limite em `exercises` | ⬜ Aberto |
| RD-03 | 🟡 Baixo | Caminho de Tentativa sem Turma (`turma_id IS NULL`) ainda ativo | ⬜ Aberto |
| RD-04 | 🟡 Baixo | SPEC/PRD de 05-27 desatualizadas em relação ao glossário | ⬜ Aberto |

## RD-01 — "Recusar" apaga Matrícula ativa

**Evidência:** `TurmaController::rejectStudent()` chama `Turma::rejectStudent()`,
que executa `DELETE FROM student_turma WHERE student_id = ? AND turma_id = ?`
sem filtrar `status = 'pending'`. O controller registra
`teacher.student.reject` sem verificar se alguma linha foi removida.

**Cenário:** um Docente envia um POST para `/teacher/turmas/{id}/reject/{studentId}`
com um aluno já aprovado. A Matrícula ativa é removida, o que na prática é um
*desvincular* de uma única turma, e a Auditoria registra uma "recusa". Com um
`studentId` que não pertence à turma, nada é removido, mas a auditoria registra
a recusa mesmo assim.

**Regra (glossário):** *Recusar* vale só para Matrícula pendente. Matrícula
ativa sai apenas por *Desvincular*.

**Correção proposta:** em `Turma::rejectStudent()`, adicionar
`AND status = 'pending'` e retornar o número de linhas afetadas. No controller,
quando nada for removido, registrar `teacher.student.reject_denied` e avisar o
docente, no mesmo padrão de `approveStudent`.

**Aceite:** um teste de integração recusa uma Matrícula ativa e verifica que ela
continua existindo e que a auditoria registra `reject_denied`.

## RD-02 — Colunas legadas em `exercises`

**Evidência:** `exercises.turma_id`, `opens_at`, `closes_at` e `max_attempts`
(esta última com o comentário "legado" no schema) não são gravadas pelo app:
`Exercise::create()` insere só `teacher_id, title, description, status`. Mas são
**lidas implicitamente**: as consultas de Exercício usam `SELECT e.*`, e
`Exercise::applyPublicationContext()` devolve o Exercício inalterado quando não
há Publicação. Assim os controllers de aluno (lista, detalhe, dashboard) passam
a usar `turma_id`, `opens_at` e `max_attempts` do legado. A configuração passou
para `exercise_turmas` na migration 003 (ver ADR 0003).

**Impacto:** confunde quem lê o schema, porque parece que o Exercício tem janela
própria. Também alimenta o ramo `turma_id IS NULL` do RD-03 quando não há
Publicação. Os dois itens precisam ser feitos juntos.

**Correção proposta:** criar uma migration incremental que remove as quatro
colunas e a FK `fk_ex_turma`, refletir a mudança em `001_create_tables.sql` e
nos dois scripts `000_reset_*`, e atualizar `bin/smoke_schema.php`.

**Aceite:** `smoke_schema` passa numa base limpa e numa base migrada, e um `rg`
não encontra referência às colunas em `app/`.

## RD-03 — Tentativa sem Turma

**Evidência:** `Attempt::getInProgress()`, `countUsedAttempts()` e
`getBestScore()` aceitam `?int $turmaId = null` e, nesse caso, filtram por
`turma_id IS NULL`. Pelo modelo atual, toda Tentativa pertence a uma Publicação.

**Impacto:** quem chamar sem `turmaId` conta tentativas do contexto errado e
pode liberar tentativas além do limite da Publicação.

**Correção proposta:** primeiro, contar em produção quantas linhas têm
`attempts.turma_id IS NULL`. Se houver, decidir com o negócio o destino delas
(atribuir a uma turma ou arquivar). Depois, tornar `turmaId` obrigatório nos
três métodos e eliminar o ramo `IS NULL`.

**Aceite:** a assinatura passa a exigir `int $turmaId`, e um teste cobre a
contagem por Publicação.

## RD-04 — SPEC/PRD desatualizadas

**Evidência:** a SPEC chama de "active" o Exercício publicado, não menciona o
bloqueio por Turma inativa (RP-23-01/02) nem a assimetria entre Recusar e
Desvincular, e usa "teacher/student" no lugar de Docente/Aluno.

**Feito agora:** aviso de documento histórico no topo da SPEC e do PRD, e links
para GLOSSARY e ADRs no README.

**Pendente:** reescrever a SPEC com a terminologia do glossário quando houver
mudança funcional relevante. Não vale reescrever só por reescrever.
