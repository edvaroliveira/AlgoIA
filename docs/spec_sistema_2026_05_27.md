# SPEC do Sistema IAProg - 2026-05-27

## Objetivo

Esta SPEC descreve o comportamento tecnico esperado do IAProg conforme o sistema implementado hoje e os ajustes necessarios para sua evolucao controlada.

## Escopo do sistema

IAProg e uma aplicacao web de apoio academico para:

- gerenciar usuarios com perfis distintos
- operar turmas com entrada por chave e aprovacao
- permitir autoria e publicacao de exercicios avaliativos
- receber respostas de alunos
- corrigir respostas automaticamente com IA
- manter rastreabilidade administrativa e auditoria

## Perfis e autorizacao

### Admin

- acesso total a dashboards, usuarios, turmas, exercicios, auditoria e tentativas pendentes
- pode aprovar ou rejeitar solicitacoes docentes
- pode moderar exercicios e questoes
- pode alterar janela de publicacao de exercicios ja ativos
- pode reprocessar tentativas pendentes

### Teacher

- gerencia apenas suas turmas, exercicios, questoes e tentativas relacionadas
- aprova ou rejeita alunos nas suas turmas
- cria exercicios, conclui e publica para turmas proprias
- reprocessa tentativas submitted de seus exercicios

### Student

- acessa apenas exercicios disponiveis nas turmas em que esta ativo
- inicia e envia tentativas dentro da janela aberta
- consulta resultados de tentativas graded

## Requisitos funcionais

### RF-01 Autenticacao

- login por email e senha
- logout com encerramento de sessao
- validacao de status da conta a cada requisicao autenticada
- redirecionamento por perfil apos login
- bloqueio operacional temporario para excesso de tentativas de login

### RF-02 Cadastro de aluno

- cadastro publico com nome, email, senha e chave da turma
- validacao de senha forte
- usuario nasce como student e status pending
- matricula em student_turma nasce como pending

### RF-03 Cadastro de docente

- rota publica separada para solicitacao docente
- disponibilidade controlada por configuracao teacher_registration_enabled
- usuario nasce como teacher e status pending
- aprovacao ou rejeicao deve ocorrer por admin

### RF-04 Troca e reset de senha

- obrigatoriedade de troca de senha quando must_change_password for verdadeiro
- reset por token com expiracao
- token persistido em hash, nunca em claro

### RF-05 Gestao de turmas

- teacher cria turma com chave de acesso
- teacher pode regenerar chave
- student entra em turma pela chave
- teacher aprova ou rejeita solicitacao do aluno
- admin pode visualizar situacao global de turmas

### RF-06 Gestao de exercicios

- exercicio possui estados draft, ready e active
- draft permite editar metadados e questoes
- complete move draft para ready quando houver pelo menos uma questao
- activate publica exercicio para uma ou mais turmas e move para active
- exercicio blocked por moderacao nao pode ser publicado

### RF-07 Questoes

- questoes pertencem a um exercicio
- cada questao possui enunciado, expected_answer_hint, ordem e nota maxima
- exclusao de questao deve respeitar propriedade do exercicio

### RF-08 Publicacao por turma

- cada relacao exercise_turmas deve armazenar opens_at, closes_at e max_attempts
- um exercicio pode ter parametros diferentes por turma
- aluno so visualiza exercicio quando houver publicacao aberta em turma ativa do aluno

### RF-09 Tentativas

- student inicia tentativa apenas com publicacao aberta
- sistema pode reutilizar tentativa in_progress do mesmo contexto de turma
- autosave de resposta por questao via endpoint autenticado
- submit grava todas as respostas da tentativa
- submit enfileira job de correcao em grading_jobs sem depender da OpenAI no request do aluno
- tentativa muda de in_progress para submitted e depois para graded

### RF-10 Correcao por IA

- correcao automatica e processada por worker/cron a partir de grading_jobs
- correcao ocorre por questao, nao apenas por tentativa agregada
- prompt deve separar system prompt e resposta do aluno
- respostas suspeitas de injection devem ser registradas em injection_logs
- retorno da IA deve ser JSON valido com score, feedback, correct e deduction_reasons
- score final da tentativa e a soma dos scores por questao

### RF-11 Reprocessamento

- admin e teacher podem reprocessar tentativas em status submitted
- reprocessamento sobrescreve score e feedback anteriores se houver nova avaliacao bem-sucedida

### RF-12 Auditoria

- acoes administrativas e eventos sensiveis devem ser registrados em audit_logs
- exportacao de auditoria em CSV e JSON deve permanecer disponivel

### RF-13 Moderacao

- exercicios e questoes podem receber admin_review_status
- status blocked deve impedir exposicao do conteudo ao aluno e impedir publicacao

## Requisitos nao funcionais

### RNF-01 Seguranca

- validacao CSRF em rotas POST autenticadas e publicas sensiveis
- senhas com hash bcrypt
- revalidacao de sessao contra banco para refletir role, status e must_change_password
- segredos devem ficar apenas em ambiente e jamais em artefatos compartilhados

### RNF-02 Confiabilidade

- falha de avaliacao automatica nao pode perder tentativa do aluno
- tentativa com falha de IA deve permanecer submitted para reprocessamento posterior
- operacoes transacionais devem proteger escrita de notas e estado da tentativa

### RNF-03 Observabilidade

- falhas de avaliacao devem registrar auditoria e error_log
- dashboards administrativos devem destacar pendencias operacionais

### RNF-04 Compatibilidade operacional

- aplicacao deve suportar deploy em subpasta
- banco deve seguir ordem oficial de migrations documentada

## Modelo de dominio

### Entidades principais

- users
- turmas
- student_turma
- exercises
- exercise_turmas
- questions
- attempts
- answers
- grading_jobs
- audit_logs
- system_settings
- injection_logs

### Relacoes relevantes

- user teacher possui muitas turmas
- user student participa de muitas turmas via student_turma
- exercise pertence a um teacher e pode ser publicado em muitas turmas
- attempt pertence a student, exercise e opcionalmente a um contexto de turma
- answer pertence a attempt e question

## Regras de negocio criticas

- aluno nao pode responder fora da janela de publicacao
- max_attempts igual a zero significa tentativas ilimitadas
- tentativa graded e a unica que pode ter resultado final mostrado ao aluno
- resposta vazia recebe nota zero por questao com feedback padrao
- blocked remove exercicio do fluxo do aluno
- pending teacher requests so devem ser resolvidas por admin

## Integracoes externas

### OpenAI

- endpoint de chat completions
- modelo configuravel por OPENAI_MODEL
- timeout padrao de 30 segundos
- retries exponenciais em 429 e 5xx

## Limites tecnicos atuais

- correcao depende de worker/cron operacional para consumir grading_jobs
- nao ha monitoramento externo dedicado para fila e latencia de avaliacao
- controllers acumulam parte das regras de orquestracao
- views server-rendered sao a estrategia predominante de interface

## Backlog tecnico recomendado

### ST-01 Alta prioridade

- reforcar monitoramento e alerta do processamento assincrono de correcao
- rotacionar credenciais e reforcar politica de segredo operacional
- padronizar testes de fumaca para login, publicacao e submit

### ST-02 Media prioridade

- centralizar regras de dominio mais criticas em services dedicados
- criar trilhas de monitoramento para latencia e falha de IA
- definir politica operacional para eventos de injection_logs

### ST-03 Baixa prioridade

- ampliar exportacoes analiticas
- reduzir repeticao de consultas administrativas com camadas de consulta especializadas
