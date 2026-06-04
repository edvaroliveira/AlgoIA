# Smoke Test Multinivel

Este documento define um plano de smoke test em camadas para validar o AlgoIA com foco em disponibilidade funcional, fluxos criticos por perfil, controles de permissao e um checklist reproduzivel.

## Objetivo

O smoke test deve responder rapidamente quatro perguntas:

1. O sistema sobe e autentica corretamente.
2. Cada perfil acessa apenas as areas esperadas.
3. Os fluxos CRUD minimos continuam operacionais.
4. As trilhas de auditoria e estados principais permanecem coerentes apos a execucao.

## Quando Executar

- Apos deploy em homologacao ou producao.
- Apos mudancas em autenticacao, sessoes, autorizacao, dashboards, turmas, exercicios, tentativas ou auditoria.
- Apos aplicar migrations.
- Antes de liberar uma revisao funcional para usuarios finais.

## Escopo por Nivel

### Nivel 0 - Sanidade Tecnica

Confirma que a base minima do ambiente esta integra antes do teste funcional.

Validar:

1. Aplicacao responde na URL base.
2. Schema esperado esta disponivel.
3. Codigo nao quebrou invariantes basicas.
4. Login publico carrega sem erro visual ou HTTP 500.

Comandos recomendados:

```bash
php bin/smoke_static.php
php bin/smoke_schema.php
```

Critério de saida: nenhum erro bloqueante nesses comandos e pagina de login acessivel.

### Nivel 1 - Acesso e Navegacao Basica

Confirma que os pontos de entrada principais funcionam.

Validar:

1. Login de administrador.
2. Login de docente.
3. Login de discente.
4. Logout em cada perfil.
5. Redirecionamento pos-login para o dashboard correto.
6. Carregamento dos menus principais sem erro.

Critério de saida: todos os perfis autenticam, navegam e encerram sessao sem falha.

### Nivel 2 - CRUD Operacional Minimo por Dominio

Confirma que as entidades principais ainda suportam o ciclo minimo esperado.

Validar:

1. Usuarios: listar, abrir detalhe, editar dado nao sensivel, alterar status e resetar senha via admin.
2. Turmas: criar, visualizar, regenerar chave, aprovar ou rejeitar ingresso e reabrir ou inativar quando aplicavel.
3. Exercicios: criar, editar, ativar, publicar para turma, fechar, reabrir e excluir quando permitido.
4. Questoes: criar e remover questao dentro de exercicio docente.
5. Tentativas: iniciar, salvar resposta, submeter e visualizar resultado quando houver correcao.

Critério de saida: cada dominio executa pelo menos um ciclo feliz curto sem erro de permissao, persistencia ou navegacao.

### Nivel 3 - Permissoes Cruzadas

Confirma isolamento entre perfis e aderencia basica de autorizacao.

Validar:

1. Aluno nao acessa rotas de docente ou admin.
2. Docente nao acessa rotas de admin.
3. Usuario deslogado nao acessa rotas autenticadas.
4. Docente nao manipula turma, exercicio ou tentativa fora do seu escopo.
5. Aluno nao acessa resultado de tentativa que nao lhe pertence.
6. Aluno nao acessa resultado ainda nao corrigido.

Critério de saida: o sistema redireciona, bloqueia ou responde com mensagem segura sem expor dados indevidos.

### Nivel 4 - Coerencia de Estado e Auditoria

Confirma que o sistema nao apenas responde, mas registra e reflete as acoes corretamente.

Validar:

1. Acoes administrativas relevantes geram trilha em auditoria.
2. Mudancas de status de usuario refletem na proxima requisicao autenticada.
3. Criacao ou atualizacao de turma aparece na listagem correta.
4. Exercicios publicados aparecem apenas para alunos da turma e dentro da janela esperada.
5. Correcao pendente aparece como pendencia para docente ou admin quando aplicavel.
6. Exportacoes CSV ou JSON principais respondem sem erro.

Critério de saida: os dados vistos nas telas batem com os efeitos esperados das acoes executadas.

### Nivel 5 - Resiliencia Operacional Curta

Opcional para smoke expandido, mas recomendado apos mudancas sensiveis.

Validar:

1. Reset de senha por admin ou token.
2. Cadastro publico de docente habilitado ou desabilitado conforme configuracao.
3. Reprocessamento de fila de correcao quando existir job pendente.
4. Exportacoes e filtros administrativos com parametros basicos.

Critério de saida: recursos operacionais criticos continuam acionaveis sem regressao evidente.

## Matriz de Cobertura

| Nivel | Foco | Perfis principais | Bloqueia deploy? |
| --- | --- | --- | --- |
| 0 | Sanidade tecnica | operador | Sim |
| 1 | Acesso e navegacao | admin, docente, aluno | Sim |
| 2 | CRUD essencial | admin, docente, aluno | Sim |
| 3 | Autorizacao | admin, docente, aluno, anonimo | Sim |
| 4 | Coerencia e auditoria | admin, docente | Recomendado |
| 5 | Operacao estendida | admin, docente | Recomendado |

## Preparacao do Ambiente

Antes de iniciar:

1. Confirmar URL alvo.
2. Confirmar se o ambiente usa dados reais, mascarados ou massa de teste.
3. Garantir tres contas ativas: administrador, docente e discente.
4. Garantir ao menos uma turma ativa para o docente.
5. Garantir ao menos um aluno vinculado a uma turma.
6. Decidir se o teste criara dados temporarios ou reutilizara massa fixa.
7. Se houver criacao de dados, definir prefixo padrao, por exemplo: SMOKE 2026-06-02.

## Massa Minima Recomendada

Para o smoke completo render valor real, o ambiente ideal deve ter:

1. 1 administrador ativo.
2. 1 docente ativo com pelo menos 1 turma.
3. 1 aluno ativo vinculado a essa turma.
4. 1 exercicio em rascunho.
5. 1 exercicio ativo ou publicavel.
6. Opcionalmente 1 tentativa em correcao e 1 tentativa corrigida.

## Checklist Reproduzivel

Use a planilha abaixo em toda execucao. Marque PASS, FAIL ou NA e registre evidencia curta.

### Execucao Parcial - 2026-06-02

Ambiente validado: https://edvar.pro.br/algoia/public

Escopo executado nesta rodada:

1. Nivel 0 parcial.
2. Nivel 1 completo.
3. Nivel 3 parcial.
4. Leitura inicial de telas do Nivel 2 sem alteracao de dados.

Observacoes desta rodada:

1. `php bin/smoke_static.php` passou no repositório local.
2. `php bin/smoke_schema.php` falhou no ambiente local do workspace por falta de conexao com banco, sem impedir a validacao funcional remota do site publicado.
3. A massa atual tem 3 usuarios, 1 turma e 0 exercicios; por isso parte do CRUD de exercicios ficou pendente ou nao aplicavel nesta rodada.

### Execucao Expandida - 2026-06-02

Massa criada nesta rodada:

1. Exercício `SMOKE 2026-06-02 - Fluxo Basico`.
2. 1 questão discursiva com pontuação 10.
3. Publicação para a turma `Algoritmo 2026.1` com 1 tentativa e janela imediata.
4. 1 tentativa do aluno `edvar.oliveira@ufra.edu.br`, submetida e enviada para correção.

Observacoes adicionais:

1. O clique de submissao do aluno registrou erro de CSP no console para inline event handler, mas a submissao foi concluida no backend e a tentativa entrou em `Em correção`.
2. O dashboard do docente refletiu imediatamente `Correções pendentes: 1` e `Fila de IA: 1`.
3. O bloqueio de acesso ao resultado pendente funcionou conforme esperado, com redirecionamento de volta ao detalhe do exercício e mensagem informando que a correção ainda está em andamento.

### Execucao de Limpeza - 2026-06-02

Rollback validado nesta rodada:

1. Admin confirmou o exercício em `/admin/exercises`, o detalhe em `/admin/exercises/1` e a tentativa em `/admin/attempts/pending`.
2. Docente excluiu o exercício de smoke pela zona de perigo.
3. Aluno voltou a enxergar `Nenhum exercício disponível no momento`.
4. Admin voltou a enxergar `0` exercícios, `0` correções pendentes e fila vazia.

Observacoes de limpeza:

1. O clique de exclusão também gerou erro de CSP no console para inline event handler, mas a exclusão foi concluída no backend e a massa foi removida com sucesso.
2. A limpeza confirmou o comportamento esperado de cascade funcional no fluxo da aplicação: o exercício sumiu da biblioteca docente, das listagens administrativas e das pendências de correção.

### Execucao Admin Usuarios - 2026-06-02

Validacoes adicionais desta rodada:

1. Admin editou o nome do usuario `aluno` para `aluno smoke` e depois reverteu para `aluno`.
2. Admin alterou o status do usuario `aluno` para `Inativo` e restaurou para `Ativo`, com confirmacao visual na listagem global.
3. Admin acionou `Resetar senha` do aluno e o sistema exibiu link temporario valido por 60 minutos na propria interface.
4. No primeiro login apos o reset, o sistema forçou a troca de senha em `/password/change`.
5. O rollback foi concluido com um segundo reset administrativo e a senha original `Aled2001@@` foi restaurada com sucesso.

Observacoes especificas:

1. O reset de senha nao apenas gera link; ele tambem marca o usuario para troca obrigatoria no proximo login.
2. O clique em `Resetar senha` repetiu o erro de CSP para inline event handler no console, mas a acao chegou ao backend e foi efetivada.

### Execucao Acesso Cruzado e Ingresso - 2026-06-02

Validacoes adicionais desta rodada:

1. Um novo aluno de smoke foi cadastrado com nome `Aluno Smoke 02 06`, e-mail `aluno.smoke.0206@algoia.test` e chave da turma `2BA5AF`.
2. O sistema aceitou o pre-cadastro e exibiu a mensagem de que o acesso dependeria de aprovacao do docente.
3. O dashboard docente e o detalhe da turma `Algoritmo 2026.1` passaram a mostrar `1` aprovacao pendente para o novo aluno.
4. O docente aprovou o ingresso e a turma passou a ter `2` alunos ativos.
5. O novo aluno autenticou com sucesso e recebeu `403 Acesso negado` ao tentar abrir `/student/attempts/1/result`, pertencente ao outro aluno da base.
6. Ao final, o docente desvinculou o aluno de smoke da turma, preservando cadastro e historico conforme mensagem da interface.

Observacoes especificas:

1. Esta rodada fechou a validacao funcional de ingresso por chave seguido de aprovacao docente.
2. O clique em `Desvincular` repetiu o erro de CSP para inline event handler no console, mas a acao foi executada no backend e a limpeza foi concluida.

### Execucao Resultado Corrigido - 2026-06-02

Validacoes adicionais desta rodada:

1. O docente criou o exercício `SMOKE 2026-06-02 - Resultado Corrigido` com 1 questão discursiva de 10 pontos e o publicou para a turma `Algoritmo 2026.1`.
2. O aluno `edvar.oliveira@ufra.edu.br` iniciou a tentativa `2`, respondeu a questão e submeteu o exercício com sucesso.
3. A tentativa apareceu no painel docente como `Na fila`, com `Correções pendentes: 1` e `Fila de IA: 1`.
4. O docente acionou `Reprocessar` no dashboard e o sistema retornou `Tentativa reprocessada com sucesso.`.
5. Após o reprocessamento, o aluno passou a ver `10.0` no painel e o resultado abriu em `/student/attempts/2/result` com nota total, feedback da IA e resposta esperada.
6. Ao final da rodada, o docente excluiu o exercício e o aluno voltou a enxergar `Nenhum exercício disponível no momento`.

Observacoes especificas:

1. O reprocessamento síncrono do docente foi suficiente para transformar a tentativa de `submitted` para `graded`, fechando o cenário de exibição do resultado.
2. O clique de submissao do aluno e o clique de exclusao do exercício repetiram o erro de CSP para inline event handler no console, mas ambas as acoes foram efetivadas no backend.

### Achado Tecnico - CSP e Confirmacoes Inline

Origem observada no código:

1. `views/student/exercises/show.php` usa `onclick="return confirm(...)"` no botão de submissão.
2. `views/teacher/exercises/show.php` usa `onsubmit="return confirm(...)"` na exclusão do exercício.
3. O mesmo padrão aparece em outras telas administrativas e docentes, como reset de senha, ações em lote e exclusões.

Impacto observado no smoke:

1. O navegador bloqueou a execução inline por CSP em pelo menos submissão de exercício e exclusão de exercício.
2. Mesmo com o erro no console, as ações chegaram ao backend e foram efetivadas nesta rodada.
3. O mesmo comportamento foi observado na regeneração de chave da turma: erro de CSP no console, mas a chave mudou de `5B7379` para `2BA5AF`.
4. O mesmo comportamento foi observado na inativação e reativação administrativa da turma: erro de CSP no console, mas a turma mudou para `Inativa` e depois voltou para `Ativa`.
5. O risco permanece, porque esse padrão depende de comportamento do navegador e tende a gerar inconsistência com a política CSP vigente.

### Bloco A - Sanidade Inicial

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| A01 | URL base abre sem erro 500 | PASS | GET em `/` respondeu com tela inicial do AlgoIA |
| A02 | Tela de login carrega logo, campos e botao | PASS | Tela exibiu logo, campos E-mail e Senha e botao Entrar |
| A03 | `php bin/smoke_static.php` sem erro | PASS | Saida local: Smoke static OK |
| A04 | `php bin/smoke_schema.php` sem erro | FAIL | Ambiente local retornou `DB connection failed: SQLSTATE[HY000] [2002] No such file or directory` |

### Bloco B - Autenticacao

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| B01 | Admin faz login e cai em `/admin/dashboard` | PASS | Login com `admin@algoia.test` redirecionou para `/admin/dashboard` |
| B02 | Admin faz logout e volta ao login | PASS | Botao Encerrar sessao retornou para `/login` |
| B03 | Docente faz login e cai em `/teacher/dashboard` | PASS | Login com `edvaroliveira@gmail.com` redirecionou para `/teacher/dashboard` |
| B04 | Docente faz logout e volta ao login | PASS | Logout docente retornou para `/login` |
| B05 | Aluno faz login e cai em `/student/dashboard` | PASS | Login com `edvar.oliveira@ufra.edu.br` redirecionou para `/student/dashboard` |
| B06 | Aluno faz logout e volta ao login | PASS | Logout discente retornou para `/login` |

### Bloco C - CRUD Admin

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| C01 | Listagem de usuarios abre | PASS | `/admin/users` listou 3 usuarios com acoes e filtros |
| C02 | Detalhe de usuario abre | PASS | `/admin/users/1` abriu detalhe de Administrador Teste |
| C03 | Edicao de usuario persiste dado permitido | PASS | Usuario `aluno` foi alterado para `aluno smoke` em `/admin/users/3/edit` e revertido para o valor original na mesma rodada |
| C04 | Alteracao de status de usuario funciona | PASS | Usuario `aluno` foi alterado de `Ativo` para `Inativo` e depois restaurado para `Ativo`, com persistencia confirmada na listagem `/admin/users` |
| C05 | Reset de senha de usuario responde sem erro | PASS | Admin gerou link de redefinicao para o aluno sem erro de backend; o proximo login exigiu troca obrigatoria de senha e o rollback restaurou a senha original `Aled2001@@` |
| C06 | Listagem de turmas abre | PASS | `/admin/turmas` listou a turma Algoritmo 2026.1 |
| C07 | Detalhe de turma abre | PASS | `/admin/turmas/1` abriu resumo, alunos ativos e chave da turma |
| C08 | Inativar ou reativar turma responde sem erro | PASS | Turma `Algoritmo 2026.1` foi para `Inativa` e voltou para `Ativa` na mesma rodada, com mensagens de confirmação em ambas as ações |
| C09 | Listagem de exercicios abre | PASS | `/admin/exercises` carregou corretamente com estado vazio |
| C10 | Detalhe de exercicio abre | PASS | Durante a massa de smoke, `/admin/exercises/1` abriu com docente, publicação, questão e submissão registradas |
| C11 | Auditoria abre e mostra eventos recentes | PASS | `/admin/audit` exibiu 6 eventos recentes com filtros e exportacoes |

### Bloco D - CRUD Docente

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| D01 | Listagem de turmas do docente abre | PASS | `/teacher/turmas` abriu com 1 turma vinculada |
| D02 | Criacao de turma funciona | NA | Rodada concentrou a validacao na turma existente `Algoritmo 2026.1` para evitar massa extra desnecessaria |
| D03 | Detalhe da turma abre | PASS | Tela de turma abriu em rodada anterior via `/teacher/turmas/1` |
| D04 | Regeneracao de chave da turma funciona | PASS | Chave da turma mudou de `5B7379` para `2BA5AF` e a mensagem `Nova chave gerada` foi exibida |
| D05 | Listagem de exercicios abre | PASS | `/teacher/exercises` carregou corretamente com estado vazio |
| D06 | Criacao de exercicio funciona | PASS | Rascunho `SMOKE 2026-06-02 - Fluxo Basico` criado com redirecionamento para cadastro de questões |
| D07 | Edicao de exercicio funciona | NA | Após conclusão e publicação, o exercício ficou corretamente congelado; não houve edição pré-publicação nesta rodada |
| D08 | Criacao de questao funciona | PASS | Questão Q1 adicionada com confirmação `Questão adicionada com sucesso` |
| D09 | Ativacao ou conclusao de exercicio funciona | PASS | Exercício foi concluído e publicado para `Algoritmo 2026.1`, ficando com status `Aberto` |
| D10 | Listagem de alunos abre | PASS | `/teacher/students` listou 1 aluno ativo vinculado |

### Bloco E - Fluxo Discente

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| E01 | Dashboard do aluno abre | PASS | `/student/dashboard` carregou com resumo de turmas e ingresso por chave |
| E02 | Listagem de exercicios abre | PASS | `/student/exercises` carregou corretamente e mostrou estado vazio |
| E03 | Detalhe de exercicio disponivel abre | PASS | Aluno abriu `/student/exercises/1` vendo janela ativa, turma e limite de tentativas |
| E04 | Inicio de tentativa funciona | PASS | `Iniciar tentativa` criou a tentativa `?attempt=1` e abriu a área de resposta |
| E05 | Salvamento de resposta funciona | PASS | Resposta foi aceita no campo da tentativa e permaneceu vinculada até a submissão |
| E06 | Submissao da tentativa funciona | PASS | Tentativa foi enviada com mensagem `Tentativa enviada. A correção automática foi colocada na fila.` |
| E07 | Resultado corrigido abre quando existir | PASS | Tentativa `2` do exercício `SMOKE 2026-06-02 - Resultado Corrigido` foi reprocessada com sucesso e abriu em `/student/attempts/2/result` com nota `10.0 / 10.0`, feedback da IA e resposta esperada |
| E08 | Ingresso em turma por chave responde conforme regra | PASS | Novo aluno `Aluno Smoke 02 06` concluiu pre-cadastro com a chave `2BA5AF`, ficou pendente de aprovacao e acessou a plataforma apos liberacao do docente |

### Bloco F - Permissoes Cruzadas

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| F01 | Anonimo nao acessa `/admin/dashboard` | PASS | Requisicao anonima redirecionou para `/login` |
| F02 | Anonimo nao acessa `/teacher/dashboard` | PASS | Requisicao anonima redirecionou para `/login` |
| F03 | Anonimo nao acessa `/student/dashboard` | PASS | Requisicao anonima redirecionou para `/login` |
| F04 | Docente nao acessa `/admin/users` | PASS | Sessao docente foi redirecionada para `/teacher/dashboard` |
| F05 | Aluno nao acessa `/teacher/turmas` | PASS | Sessao discente foi redirecionada para `/student/dashboard` |
| F06 | Aluno nao acessa `/admin/audit` | PASS | Sessao discente foi redirecionada para `/student/dashboard` |
| F07 | Aluno nao acessa tentativa de outro usuario | PASS | Aluno `Aluno Smoke 02 06` autenticado recebeu `403 Acesso negado` ao abrir `/student/attempts/1/result`, pertencente a outro usuario |
| F08 | Resultado de tentativa pendente nao aparece como nota final | PASS | Acesso direto a `/student/attempts/1/result` redirecionou ao exercício com mensagem de correção em andamento |

### Bloco G - Auditoria e Coerencia

| ID | Item | Resultado | Evidencia |
| --- | --- | --- | --- |
| G01 | Acao admin relevante gera evento em auditoria | PASS | Auditoria administrativa já havia exibido eventos de status de usuário e cadastro docente em rodada anterior |
| G02 | Novo exercicio aparece onde deveria | PASS | Exercício apareceu no dashboard docente e no detalhe do aluno após publicação |
| G03 | Publicacao de exercicio reflete no aluno correto | PASS | Apenas o aluno da turma `Algoritmo 2026.1` passou a ver o exercício no dashboard |
| G04 | Filtros e exportacoes admin respondem sem erro | PASS | Fetch autenticado nos exports JSON de usuários, turmas, exercícios e auditoria retornou 200 com `application/json` e payload `filters`, `exported_at` e `items` |
| G05 | Pendencia de correcao aparece em painel apropriado quando houver | PASS | Dashboard do docente mostrou `Correções pendentes: 1`, `Fila de IA: 1` e a tentativa `Na fila` |

## Sequencia Recomendada de Execucao

Para reduzir tempo e evitar retrabalho, execute nesta ordem:

1. Nivel 0 completo.
2. Nivel 1 completo.
3. Nivel 3 basico de acesso anonimo e cruzado.
4. Nivel 2 nos CRUDs mais sensiveis ao deploy atual.
5. Nivel 4 para confirmar persistencia e auditoria.
6. Nivel 5 apenas quando o escopo alterado tocar esses fluxos.

## Regras de Evidencia

Ao registrar a execucao:

1. Anotar data, ambiente e commit ou versao implantada.
2. Registrar usuario usado em cada passo.
3. Capturar screenshot apenas em falhas ou marcos importantes.
4. Para cada FAIL, anotar rota, acao, mensagem exibida e impacto.
5. Classificar falha como bloqueante, alta, media ou baixa.

## Criterio de Aprovacao

O smoke test e considerado aprovado quando:

1. Todos os itens dos niveis 0 e 1 passam.
2. Nenhum item bloqueante dos niveis 2 e 3 falha.
3. Qualquer falha remanescente de niveis 4 e 5 esta registrada, triada e aceita para o ambiente.

O deploy deve ser bloqueado quando ocorrer qualquer uma destas situacoes:

1. Login falha para algum perfil obrigatorio.
2. Dashboard principal nao carrega.
3. Permissao cruzada expoe tela ou dado indevido.
4. CRUD essencial quebra com erro de servidor ou persistencia incorreta.
5. Migrations ou comandos de sanidade falham.

## Roteiro Enxuto para Regressao Rapida

Quando houver pouco tempo, rode pelo menos:

1. A01 a A04.
2. B01 a B06.
3. C01, C06, C09 e C11.
4. D01, D05 e D06.
5. E01, E02 e E08.
6. F01 a F06.

Esse roteiro reduz cobertura, mas ainda captura regressao grossa em autenticacao, navegacao, permissao e operacao principal.
