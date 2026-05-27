# Revisao Completa do Sistema - 2026-05-27

Este documento consolida a revisao estatica do sistema com base no codigo atual, rotas, models, services, migrations e documentacao operacional existentes.

## Resumo Executivo

O IAProg e uma plataforma web educacional para gestao de turmas, distribuicao de exercicios e correcao automatica com IA. O produto atual ja sustenta um fluxo operacional relevante para tres perfis de usuario:

- admin: governanca global, moderacao, auditoria e operacao
- teacher: autoria pedagogica, gestao de turmas e acompanhamento
- student: participacao em turmas, resolucao e consulta de resultados

O sistema esta organizado em MVC artesanal em PHP, com bootstrap enxuto, roteamento proprio, sessao server-side e persistencia em MySQL/MariaDB. O dominio principal esta relativamente consistente no codigo, especialmente nos fluxos de autenticacao, tentativa, correcao, publicacao por turma e auditoria.

## O que o sistema entrega hoje

- autenticacao com perfis admin, teacher e student
- cadastro publico de aluno com vinculo inicial a turma por chave
- cadastro publico de docente sujeito a aprovacao administrativa
- troca obrigatoria de senha e reset por token
- gestao de turmas por docente, com aprovacao ou rejeicao de alunos
- criacao de exercicios em ciclo draft -> ready -> active
- publicacao de exercicios por turma com janela independente
- limite de tentativas por publicacao
- correcao automatica por IA por questao
- reprocessamento manual de tentativas pendentes por teacher/admin
- auditoria administrativa e exportacoes operacionais
- moderacao administrativa de exercicios e questoes

## Arquitetura observada

### Camadas

- public/index.php inicializa ambiente, sessoes e roteador
- routes/web.php concentra todas as rotas HTTP
- app/Controllers contem orquestracao de caso de uso
- app/Models concentra queries SQL e regras de acesso a dados
- app/Services concentra integracoes e servicos transacionais
- views contem layouts e telas por perfil
- database/migrations contem schema base consolidado e historico incremental

### Componentes centrais

- Core\Auth revalida sessao no banco e aplica gate por perfil
- ExerciseController controla ciclo de vida e publicacao pedagogica
- AttemptController controla inicio, autosave, envio e reprocessamento
- AttemptGradingService executa o pipeline de correcao transacional
- OpenAIService encapsula prompt, chamada externa e validacao estrutural da resposta
- AdminController concentra operacao global, exportacao, presets e moderacao

## Fluxos de negocio confirmados

### 1. Aluno entra no sistema

- usuario se cadastra com chave de turma
- conta nasce como pending
- vinculo student_turma nasce como pending
- docente aprova o aluno
- aluno passa a ver exercicios abertos da turma

### 2. Docente cria e publica atividade

- cria exercicio em draft
- adiciona pelo menos uma questao
- conclui para ready
- ativa para uma ou mais turmas com configuracao por turma
- sistema grava janela de abertura, fechamento e maximo de tentativas por turma

### 3. Aluno responde e recebe avaliacao

- inicia tentativa em publicacao aberta
- respostas sao salvas por autosave
- submit persiste todas as respostas e muda tentativa para submitted
- sistema cria job em grading_jobs para correcao assincrona
- worker executa AttemptGradingService, chama OpenAIService por resposta e grava nota/feedback em answers e attempts

### 4. Operacao administrativa

- admin acompanha dashboard com pendencias prioritarias
- filtra usuarios, turmas, exercicios, auditoria e tentativas pendentes
- exporta dados em CSV e JSON
- modera conteudo e solicitacoes docentes
- teacher/admin reprocessam tentativas submitted quando a IA falha

## Pontos fortes do estado atual

- modelo de publicacao por turma e mais maduro do que um agendamento global por exercicio
- isolamento razoavel da integracao com IA em servico dedicado
- validacao de formato da resposta da IA reduz acoplamento com texto livre
- trilha de auditoria cobre boa parte das acoes sensiveis
- moderacao administrativa evita publicacao de conteudo bloqueado
- deploy operacional ja esta parcialmente documentado

## Gaps e riscos identificados

### P0

- segredo operacional exposto no ambiente local: o arquivo .env presente no workspace contem credenciais reais de banco e API, o que exige rotacao imediata se tiver sido compartilhado fora do ambiente previsto
- dependencia operacional de worker/cron para consumir a fila de correcao: se o processo parar, tentativas ficam submitted ate reprocessamento

### P1

- monitoramento de fila ainda precisa evoluir para alertar latencia, falhas e backlog acumulado
- o produto ainda depende fortemente de views server-rendered e operacoes administrativas centralizadas, o que aumenta custo de manutencao de UX
- regras de negocio estao razoavelmente espalhadas entre controller e model; isso ainda funciona, mas dificulta evolucao modular
- ha historico de complexidade em migrations, apesar de a documentacao recente ter reduzido o risco operacional

### P2

- faltam indicadores pedagogicos mais sofisticados, como analise por questao, progresso por turma e recorrencia de erros
- nao ha evidencias de suite automatizada de testes para os fluxos principais

## Ambiguidades de produto

- o conceito de aprovacao de aluno esta funcional, mas o PRD precisa fixar SLA, notificacoes e politicas de reenvio
- a moderacao administrativa existe no codigo, mas a politica operacional de flagged versus blocked precisa ser formalizada
- o sistema registra injection_logs, mas o tratamento operacional desses eventos ainda nao esta definido como fluxo de produto

## Recomendacoes objetivas

1. Formalizar o contrato de produto e tecnologia em uma SPEC unica para desenvolvimento e manutencao.
2. Formalizar um PRD orientado a operacao academica, com metas, papeis, KPI e backlog priorizado.
3. Tratar gestao de segredos como acao imediata, com rotacao de credenciais e endurecimento do processo de deploy.
4. Planejar observabilidade da fila de correcao antes de escalar uso institucional.
5. Definir indicadores de sucesso pedagogico e operacional para a fase seguinte do produto.

## Saida desta revisao

Esta revisao deu origem aos documentos:

- docs/spec_sistema_2026_05_27.md
- docs/prd_sistema_2026_05_27.md
