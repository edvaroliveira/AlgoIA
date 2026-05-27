# PRD do Sistema IAProg - 2026-05-27

## Visao do produto

IAProg e uma plataforma para operacao de atividades avaliativas em cursos de logica, algoritmos e programacao, com foco em reduzir carga operacional do docente e acelerar retorno pedagogico ao aluno por meio de correcao automatica assistida por IA.

## Problema que o produto resolve

- docentes perdem tempo com triagem manual de respostas abertas
- instituicoes pequenas ou medias carecem de um fluxo simples para turmas, atividades e auditoria
- alunos recebem feedback tardio ou inconsistente em exercicios discursivos e de pseudocodigo
- administracao academica precisa de trilha de auditoria, moderacao e governanca do uso da IA

## Objetivos de produto

### Objetivo principal

Permitir que exercicios de algoritmos sejam publicados, respondidos e corrigidos com rapidez, mantendo controle pedagogico e governanca administrativa.

### Objetivos secundarios

- reduzir tempo de correcao docente
- acelerar feedback ao aluno
- dar visibilidade operacional a pendencias e falhas
- permitir aprovacao e moderacao centralizadas
- manter base preparada para uso institucional

## Personas

### Admin academico

Precisa controlar quem entra no sistema, garantir conformidade, acompanhar pendencias e intervir quando houver problema operacional ou pedagogico.

### Docente

Precisa criar turmas e exercicios com rapidez, acompanhar desempenho e evitar retrabalho na correcao de respostas abertas.

### Aluno

Precisa acessar atividades da propria turma, responder dentro do prazo e receber retorno claro sobre acertos e falhas.

## Proposta de valor

- para docentes: autoria simples e ganho de escala na correcao
- para alunos: feedback mais rapido e consistente
- para administracao: controle, moderacao, trilha de auditoria e exportacao

## Escopo funcional da versao atual

### Incluido

- autenticacao e controle de papeis
- cadastro publico de aluno
- solicitacao publica de cadastro docente
- aprovacao de alunos em turmas
- criacao e publicacao de exercicios por turma
- tentativa com autosave
- correcao automatica por IA
- fila assincrona basica de correcao
- reprocessamento de tentativas pendentes
- exportacoes operacionais e auditoria
- moderacao administrativa de exercicios e questoes

### Fora do escopo atual

- notificacoes por email ou push
- rubricas configuraveis por curso
- dashboard analitico avancado por competencia
- integracao com LMS externo
- provas objetivas, banco de questoes compartilhado ou autoria colaborativa

## Jornada principal

### Jornada do docente

1. cria turma
2. recebe alunos pendentes e aprova
3. cria exercicio e cadastra questoes
4. conclui e publica para turmas com janela e limite de tentativas
5. acompanha resultados e pendencias
6. reprocessa tentativas quando necessario

### Jornada do aluno

1. cadastra conta com chave da turma
2. aguarda aprovacao
3. acessa exercicios abertos
4. inicia tentativa e salva respostas
5. envia tentativa
6. consulta resultado quando a tentativa estiver corrigida

### Jornada do admin

1. acompanha dashboard global
2. aprova ou rejeita docentes
3. monitora usuarios, turmas, exercicios e tentativas pendentes
4. modera conteudos sensiveis ou inadequados
5. exporta dados e consulta auditoria

## Requisitos de produto

### PR-01 Governanca de acesso

O sistema deve distinguir claramente os papeis admin, teacher e student, com redirecionamento e permissoes coerentes.

### PR-02 Publicacao pedagogica controlada

Cada exercicio deve ser publicavel por turma com datas e limite de tentativas independentes.

### PR-03 Feedback automatizado

Toda tentativa submetida deve receber correcao automatica quando a IA estiver disponivel, ou permanecer pendente para reprocessamento sem perda de dados.

### PR-04 Seguranca operacional

O produto deve suportar reset de senha, troca obrigatoria e auditoria de eventos criticos.

### PR-05 Moderacao e compliance

Administradores devem conseguir bloquear ou sinalizar conteudo antes e depois da publicacao.

## Indicadores de sucesso sugeridos

### Operacionais

- tempo medio entre submit e resultado
- taxa de tentativas que ficam pendentes por falha de IA
- volume de reprocessamentos por semana
- taxa de aprovacao de solicitacoes docentes

### Pedagogicos

- percentual de alunos que concluem ao menos uma tentativa por atividade
- distribuicao de nota por turma e por exercicio
- frequencia de deduction_reasons por questao

### Administrativos

- numero de eventos auditados por categoria
- tempo medio para tratar pendencias administrativas

## Riscos de produto

### Alto

- dependencia de servico externo de IA no caminho critico da entrega de resultado
- exposicao inadequada de credenciais compromete operacao e custo

### Medio

- falta de notificacoes reduz previsibilidade para aluno e docente
- ausencia de analytics limita uso gerencial do produto
- ausencia de testes automatizados aumenta risco de regressao

## Roadmap recomendado

### Fase 1 - Estabilizacao operacional

- rotacao de segredos e higiene de ambiente
- testes de fumaca dos fluxos principais
- telemetria basica de falha e latencia da IA
- ajustes finais na politica de moderacao e injection logs

### Fase 2 - Escala academica

- monitoramento e refinamento da fila assincrona de correcao
- notificacao de tentativa corrigida
- dashboard de desempenho por turma, exercicio e questao

### Fase 3 - Maturidade de produto

- configuracao de rubricas por disciplina
- historico comparativo do aluno
- integracoes institucionais e SSO

## Criterios de aceite para a fase atual

- admin opera o sistema sem acesso direto ao banco
- docente consegue publicar e acompanhar atividades sem suporte tecnico
- aluno consegue concluir uma tentativa completa e consultar resultado corrigido
- falha da IA nao apaga tentativa nem bloqueia reprocessamento
- toda acao administrativa relevante fica auditada

## Decisoes de produto a formalizar na proxima iteracao

- SLA para aprovacao de alunos e docentes
- politica de exibicao do gabarito apos encerramento
- politica de tratamento para suspected prompt injection
- politicas de retencao de auditoria e dados academicos
