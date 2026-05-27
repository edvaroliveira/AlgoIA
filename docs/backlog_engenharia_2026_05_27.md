# Backlog de Engenharia Prioritario - 2026-05-27

Este backlog traduz a revisao do sistema, a SPEC e o PRD em um plano de execucao de engenharia com prioridades, dependencias e criterios de aceite.

## Premissas

- o backlog considera o estado atual do codigo como baseline
- itens P0 tratam risco operacional, seguranca ou indisponibilidade de fluxo principal
- itens P1 tratam escala, manutencao e confiabilidade de medio prazo
- itens P2 tratam evolucao analitica e produtividade futura

## Sequenciamento recomendado

1. estabilizacao operacional e seguranca
2. confiabilidade do pipeline de correcao
3. observabilidade e testes de fumaca
4. modularizacao de regras criticas
5. analytics e produtividade administrativa

## Epic E1 - Seguranca e Higiene Operacional

### Objetivo

Eliminar risco de segredo exposto, formalizar politica de ambiente e reduzir superficie operacional sensivel.

### Prioridade

P0

### Historias

#### E1-H1 Rotacionar credenciais expostas

Como responsavel pela operacao
Quero rotacionar credenciais de banco e API que aparecem em ambiente local
Para reduzir risco de acesso indevido e custo indevido.

Criterios de aceite:

- credenciais atuais de banco e OpenAI sao substituidas por novos valores validos
- nenhum segredo real permanece em arquivos versionados ou compartilhados
- o ambiente volta a operar com as novas credenciais
- o procedimento de rotacao fica documentado

#### E1-H2 Endurecer politica de arquivos de ambiente

Como mantenedor do projeto
Quero garantir que apenas placeholders fiquem em exemplos de ambiente
Para evitar novos vazamentos por copia de configuracao.

Criterios de aceite:

- .env.example contem apenas placeholders
- documentacao operacional descreve como preencher variaveis sem expor segredos
- revisao de arquivos do repositorio nao encontra tokens ou senhas reais

#### E1-H3 Checklist de deploy seguro

Como operador do sistema
Quero um checklist curto de pre-deploy e pos-deploy
Para reduzir erro humano em atualizacao de ambiente.

Criterios de aceite:

- checklist cobre backup, migrations, segredo, smoke test e rollback
- documento fica referenciado em docs operacionais
- fluxo e executavel por outro mantenedor sem consulta ao banco de dados direto

#### E1-H4 Corrigir aprovacao indevida de usuarios por docente

Como mantenedor do sistema
Quero garantir que a aprovacao de aluno valide vinculo, turma, perfil e estado
Para impedir que um docente ative contas pendentes fora do fluxo correto.

Criterios de aceite:

- teacher so consegue aprovar usuario com role student
- aprovacao exige vinculo student_turma pendente na turma pertencente ao teacher autenticado
- usuario so muda para active se a matricula pendente da turma foi atualizada com sucesso
- solicitacoes docentes pendentes nao podem ser ativadas por endpoint de aprovacao de aluno
- tentativa de aprovacao com studentId invalido ou fora da turma retorna erro controlado e registra auditoria quando aplicavel

#### E1-H5 Persistir throttle de login por identidade e origem

Como mantenedor do sistema
Quero limitar tentativas de login por email e origem alem da sessao atual
Para reduzir risco de força bruta por troca de navegador ou sessao.

Criterios de aceite:

- tentativas falhas sao registradas com email normalizado, IP/origem, user agent resumido e timestamp
- bloqueio temporario considera janela por email e IP/origem, nao apenas variavel de sessao
- login bem-sucedido limpa ou invalida tentativas falhas recentes daquele par email/origem
- mensagens ao usuario continuam genericas e nao permitem enumeracao de contas
- existe limpeza ou politica de retencao para registros antigos de tentativa de login

## Epic E2 - Correcao Assincrona e Resiliencia da IA

### Objetivo

Retirar a dependencia da OpenAI do caminho critico do submit e reduzir acoplamento entre UX do aluno e latencia do provedor.

### Prioridade

P0

### Dependencias

- E1-H1 concluida
- definicao minima de telemetria de falha

### Historias

#### E2-H1 Desacoplar submit da correcao

Como aluno
Quero que minha tentativa seja aceita rapidamente mesmo quando a IA estiver lenta
Para nao perder tempo esperando o retorno do provedor.

Criterios de aceite:

- submit persiste respostas e finaliza em tempo previsivel sem depender da chamada de IA no mesmo request
- tentativa fica em estado intermediario claro para o usuario ate a correcao terminar
- falha de worker ou integracao nao perde respostas nem muda a tentativa para estado incorreto

#### E2-H2 Implementar fila de processamento de tentativas

Como time de engenharia
Quero um mecanismo de fila para correcoes pendentes
Para processar tentativas com retentativa controlada.

Criterios de aceite:

- existe estrutura de fila ou tabela de jobs com estados minimos
- jobs podem ser reprocessados sem duplicar nota final
- retentativas sao limitadas e registradas
- falha final deixa tentativa recuperavel por reprocessamento administrativo

#### E2-H3 Expor status operacional da correcao

Como admin e teacher
Quero distinguir tentativa enviada, em processamento, falha tecnica e corrigida
Para atuar corretamente sobre pendencias.

Criterios de aceite:

- painel de tentativas diferencia estados operacionais
- eventos de falha ficam visiveis na auditoria ou telemetria
- usuario final nao interpreta pendencia tecnica como nota zero

## Epic E3 - Observabilidade e Monitoramento

### Objetivo

Dar visibilidade real sobre falhas, latencia, acumulo de pendencias e comportamento do pipeline de correcao.

### Prioridade

P1

### Historias

#### E3-H1 Instrumentar latencia e falha da IA

Como time de operacao
Quero medir latencia, taxa de erro e volume de reprocessamento
Para detectar degradacao antes de virar incidente academico.

Criterios de aceite:

- logs ou metricas registram tempo de avaliacao por tentativa e por resposta
- falhas sao categorizadas ao menos em timeout, rate limit e erro estrutural
- existe visao simples para contagem de falhas por periodo

#### E3-H2 Dashboard de pendencias operacionais

Como admin
Quero ver um resumo operacional objetivo na dashboard
Para priorizar acoes corretivas.

Criterios de aceite:

- dashboard mostra tentativas pendentes, falhas recentes e exercicios com fechamento proximo
- totais batem com consultas operacionais do banco
- filtros preservam navegacao e retorno ao contexto

#### E3-H3 Politica operacional para injection logs

Como admin academico
Quero um fluxo claro para respostas suspeitas de prompt injection
Para decidir quando apenas registrar e quando intervir.

Criterios de aceite:

- eventos suspeitos ficam consultaveis por admin
- politica documenta o que gera alerta operacional
- historico preserva privacidade sem expor resposta integral do aluno

## Epic E4 - Testes de Fumaca e Confiabilidade Basica

### Objetivo

Criar uma malha minima de validacao para os fluxos que sustentam o produto atual.

### Prioridade

P1

### Dependencias

- definicao de ambiente de homologacao ou base de teste repetivel

### Historias

#### E4-H1 Smoke test de autenticacao e autorizacao

Como mantenedor
Quero validar login, logout e gate por perfil
Para evitar regressao em acesso basico do sistema.

Criterios de aceite:

- existe roteiro automatizado ou semi-automatizado cobrindo login por perfil
- usuario inativo ou rejeitado perde acesso na proxima requisicao autenticada
- must_change_password redireciona corretamente
- throttle persistente bloqueia excesso de falhas e nao bloqueia usuario legitimo apos janela expirada

#### E4-H2 Smoke test de fluxo docente

Como time de engenharia
Quero validar criacao de turma, exercicio, questao e publicacao
Para proteger o fluxo pedagogico principal.

Criterios de aceite:

- o roteiro cobre draft, ready e active
- publicacao por turma valida datas e max_attempts
- exercicio blocked nao publica

#### E4-H3 Smoke test de fluxo do aluno

Como time de engenharia
Quero validar cadastro, aprovacao, tentativa e consulta de resultado
Para detectar quebra no ciclo ponta a ponta.

Criterios de aceite:

- aluno consegue se cadastrar com chave valida
- aluno aprovado consegue iniciar tentativa em exercicio aberto
- submit gera tentativa submetida e posteriormente resultado consultavel
- tentativa nao corrigida nao aparece como nota final

#### E4-H4 Teste de regressao para aprovacao de aluno

Como mantenedor
Quero cobrir o fluxo de aprovacao de aluno com cenarios negativos
Para impedir reintroducao de ativacao indevida de usuarios.

Criterios de aceite:

- teacher aprova apenas aluno pendente em turma propria
- teacher nao aprova aluno de turma de outro docente
- teacher nao ativa usuario pending com role teacher usando endpoint de aluno
- teacher nao ativa usuario sem vinculo student_turma pendente
- teste confirma que usuario permanece pending quando a matricula nao e alterada

## Epic E5 - Modularizacao do Dominio Critico

### Objetivo

Reduzir espalhamento de regra de negocio entre controllers e models, melhorando manutencao e seguranca de mudanca.

### Prioridade

P1

### Historias

#### E5-H1 Extrair servico de publicacao de exercicios

Como time de engenharia
Quero centralizar regras de publicacao e validacao por turma
Para evitar duplicacao e divergencia de comportamento.

Criterios de aceite:

- validacoes de janela e max_attempts ficam em servico dedicado
- teacher e admin reutilizam a mesma regra de publicacao
- testes de fumaca continuam passando apos a extracao

#### E5-H2 Extrair servico de estado de tentativa

Como time de engenharia
Quero consolidar regras de transicao de attempts
Para evitar inconsistencias entre submit, regrade e exibicao de resultado.

Criterios de aceite:

- estados validos ficam documentados em um unico ponto
- transicoes invalidas geram erro controlado
- renderizacao de resultado consulta apenas estados permitidos

#### E5-H3 Padronizar auditoria de acoes sensiveis

Como mantenedor
Quero uma convencao unica para nomes e metadata de auditoria
Para facilitar rastreamento e suporte.

Criterios de aceite:

- acoes de auditoria seguem padrao consistente
- metadata minima por entidade fica documentada
- exportacao administrativa continua compativel

## Epic E6 - Analytics Pedagogico e Operacional

### Objetivo

Transformar os dados ja capturados em leitura gerencial e pedagogica util.

### Prioridade

P2

### Historias

#### E6-H1 Analise por questao e motivo de desconto

Como docente
Quero enxergar recorrencia de deduction_reasons e desempenho por questao
Para ajustar ensino e enunciados.

Criterios de aceite:

- painel mostra distribuicao de score por questao
- deduction_reasons sao agregados com labels legiveis
- respostas antigas sem dado complementar nao quebram a visualizacao

#### E6-H2 Visao consolidada por turma

Como admin ou teacher
Quero ver progresso e participacao por turma
Para identificar gargalos de adesao e desempenho.

Criterios de aceite:

- painel mostra quantidade de alunos ativos, participacao em exercicios e melhor nota por turma
- filtros por periodo e exercicio funcionam sem quebrar navegacao

#### E6-H3 Exportacao analitica

Como operador academico
Quero exportar dados consolidados de desempenho
Para uso externo em planilhas ou BI.

Criterios de aceite:

- exportacao inclui identificadores essenciais, score, turma, exercicio e timestamps
- formato CSV e JSON seguem padrao coerente com exportacoes existentes

## Epic E7 - Migrations e Base de Evolucao

### Objetivo

Manter o repositorio seguro para novas instalacoes e atualizacoes sem reabrir fragilidade historica de schema.

### Prioridade

P1

### Historias

#### E7-H1 Automatizar verificacao de ordem de migrations

Como mantenedor
Quero uma verificacao objetiva da cadeia de migrations
Para evitar deploy com ordem incorreta.

Criterios de aceite:

- existe documento ou script que aponta ordem oficial de aplicacao
- ambientes limpos e legados seguem caminhos distintos sem ambiguidade

#### E7-H2 Criar roteiro repetivel de base de teste

Como time de engenharia
Quero subir uma base de teste previsivel
Para executar smoke tests e validar evolucoes.

Criterios de aceite:

- existe roteiro claro para resetar banco de teste
- o roteiro produz dados minimos para admin, teacher, student, turma e exercicio
- o processo e suficientemente simples para uso recorrente

## Visao consolidada de prioridades

### O que entra no proximo ciclo

- E1-H4
- E1-H1
- E1-H2
- E1-H5
- E2-H1
- E2-H2
- E2-H3

### O que entra no ciclo seguinte

- E3-H1
- E3-H2
- E4-H1
- E4-H2
- E4-H3
- E4-H4
- E5-H1
- E5-H2
- E7-H2

### O que pode ficar para depois

- E3-H3
- E5-H3
- E6-H1
- E6-H2
- E6-H3
- E7-H1

## Definicao de pronto sugerida

Um item do backlog so deve ser considerado pronto quando:

- comportamento novo estiver implementado
- criterio de aceite estiver demonstrado
- impacto operacional estiver documentado quando aplicavel
- auditoria, logs ou visibilidade minima forem atualizados quando o item mexer em fluxo sensivel
- validacao estreita do slice alterado tiver sido executada

## Status de execucao

### Implementado nesta iteracao

- E1-H4: aprovacao de aluno agora exige role student, vinculo pendente na turma correta e atualizacao transacional
- E1-H5: throttle persistente de login por email e origem com fallback por sessao
- E2-H1: submit do aluno enfileira correcao e nao depende da OpenAI no request
- E2-H2: tabela grading_jobs, worker CLI e retry/backoff basico implementados
- E2-H3: dashboards e paineis exibem status operacional da fila de correcao
- E3-H1 parcial: logs registram latencia por resposta/tentativa e categoria basica de falha do worker
- E3-H2 parcial: dashboards exibem fila de IA, falhas recentes, jobs atrasados e concluidos em 24h
- E4-H4 parcial: smoke estatico cobre invariantes de aprovacao de aluno, fila de correcao e throttle persistente

### Pendente de validacao operacional

- aplicar migrations 013 e 014 em banco de homologacao
- executar smoke funcional com login, aprovacao, submit, worker e consulta de resultado
- configurar cron do worker em ambiente real
- validar metricas de latencia/falha em logs reais
