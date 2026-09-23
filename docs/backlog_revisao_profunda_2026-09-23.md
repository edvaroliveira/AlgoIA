# Backlog — Revisão profunda 2026-09-23

Revisão focada nas fronteiras de autorização, estado de turmas, tentativas,
fila de correção, sessão, CSRF, rotas e cobertura de testes. Os testes existentes
passaram, mas não cobrem a transição de uma turma ativa para inativa.

## Resumo

| ID | Severidade | Tema | Status |
|----|-----------|------|--------|
| RP-23-01 | 🔴 Alto | Turma inativa ainda libera acesso e envio de exercícios | ✅ Corrigido |
| RP-23-02 | 🟠 Médio | Docente ainda publica e administra turma inativa | ✅ Corrigido |
| RP-23-03 | 🟠 Médio | Falta de testes negativos autenticados por papel e turma | 🟡 Testes adicionados; integração pendente |

## RP-23-01 — Turma inativa ainda libera acesso e envio

**Evidência:** as consultas de acesso do aluno em `Exercise` fazem `JOIN`
com `turmas`, mas não exigem `t.active = 1`. O mesmo vale para as validações
transacionais em `AttemptStartService` e `AttemptSubmissionService`.

**Cenário:** aluno aprovado e exercício publicado; administrador inativa a
turma; o aluno ainda consegue visualizar o exercício, iniciar tentativa e
enviar resposta enquanto a janela de publicação estiver aberta.

**Impacto:** a inativação administrativa não funciona como barreira de acesso
e pode permitir novas respostas em uma turma suspensa.

**Arquivos envolvidos:** `app/Models/Exercise.php`,
`app/Services/AttemptStartService.php` e
`app/Services/AttemptSubmissionService.php`.

**Correção aplicada:** as consultas de acesso exigem `t.active = 1`, e as
transações de início e envio revalidam a turma ativa. Tentativas já iniciadas
não são apagadas, mas não podem mais salvar ou enviar respostas.

**Aceite:** depois da inativação, listagem, detalhe, início, auto-save e envio
retornam bloqueio; uma tentativa concorrente com a inativação não pode concluir
como `submitted`.

## RP-23-02 — Operações docentes permanecem disponíveis em turma inativa

**Evidência:** `Turma::belongsToTeacher()` e `ExerciseController::validateActivation()`
validam apenas o vínculo do docente com a turma, sem considerar `active`. Os
endpoints de aprovação/rejeição de alunos e regeneração de chave também usam
apenas a propriedade da turma.

**Impacto:** um docente pode publicar exercício, aprovar aluno ou alterar a
chave de uma turma que o administrador desativou, criando estado inconsistente
e podendo reabrir o fluxo suspenso.

**Arquivos envolvidos:** `app/Models/Turma.php`,
`app/Controllers/ExerciseController.php` e
`app/Controllers/TurmaController.php`.

**Decisão aplicada:** publicação, aprovação, rejeição e regeneração de chave
ficam bloqueadas em turma inativa. Consulta, inativação e reativação continuam
disponíveis para administração.

`Turma::belongsToTeacher()` passou a exigir turma ativa, os endpoints docentes
registram a tentativa bloqueada em auditoria e `Exercise::activate()` trava e
revalida as turmas selecionadas dentro da transação.

**Aceite:** a política escolhida é aplicada no controller e no model/query,
com resposta negativa e auditoria para cada operação bloqueada.

## RP-23-03 — Cobertura insuficiente de autorização negativa

**Evidência:** os testes HTTP autenticados não cobrem combinações de dois
docentes, dois alunos e duas turmas. Também não há cenário que inative uma
turma depois da matrícula/publicação e valide as rotas de aluno e docente.

**Impacto:** regressões de IDOR, mistura de contexto de turma e bypass por
estado administrativo podem passar pela CI mesmo com os testes unitários e de
concorrência atuais verdes.

**Arquivos envolvidos:** `bin/run_integration_tests.php` e suíte de testes
relacionada.

**Correção aplicada:** foram adicionadas verificações de integração MariaDB
para:

- aluno tentando listar e abrir exercício após a inativação;
- aluno tentando iniciar e enviar tentativa após a inativação;
- publicação docente em turma inativa;
- preservação da tentativa em `in_progress` após rejeição.

**Aceite:** os cenários negativos retornam o código esperado e nenhuma
alteração indevida é persistida; todos rodam na CI com MariaDB real quando a
suíte de integração estiver habilitada.

## Validações realizadas

- `php bin/run_tests.php`: 63 testes passaram.
- `php bin/run_db_tests.php`: 58 testes passaram.
- `php bin/smoke_static.php`: OK.
- `php -l` em todos os PHP da aplicação: sem erros.
- `php bin/run_integration_tests.php`: execução pendente neste ambiente; o
	script recusou operar no banco configurado (`edvard29_algoia`) porque ele não
	tem nome de teste e a suíte trunca todas as tabelas.

Os cenários RP-23 foram adicionados a `bin/run_integration_tests.php` e devem
ser executados na CI ou com `DB_DATABASE` apontando para um banco descartável
de teste. A confirmação destrutiva não foi forçada contra o banco local.
