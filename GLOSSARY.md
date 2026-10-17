# AlgoIA

Apoio acadêmico em que docentes publicam exercícios discursivos para suas turmas e as respostas dos alunos são corrigidas por IA, com moderação e auditoria administrativa.

Os termos canônicos estão em português. O identificador usado no código aparece entre parênteses só quando difere do termo.

## Pessoas e acesso

**Usuário** (`users`):
Pessoa com conta no sistema. Tem exatamente um Papel e um status de conta: *pendente*, *ativo*, *inativo* ou *recusado*.
_Evitar_: Conta (como sinônimo de pessoa), membro

**Admin**:
Papel com visão e controle sobre todo o sistema, responsável por moderação, aprovação de docentes e auditoria.
_Evitar_: Administrador do sistema, superusuário

**Docente** (`teacher`):
Papel de quem cria turmas e exercícios e acompanha as tentativas dos próprios alunos.
_Evitar_: Professor, teacher

**Aluno** (`student`):
Papel de quem participa de turmas e responde exercícios. O Aluno se torna *ativo* quando sua primeira Matrícula é aprovada, e continua ativo mesmo que depois fique sem nenhuma Matrícula.
_Evitar_: Estudante, student

**Solicitação docente**:
Pedido público de cadastro como Docente, que só vale depois de aprovado por um Admin. O pedido só fica disponível quando o Admin liberou o cadastro docente.
_Evitar_: Convite de professor

## Turmas

**Turma**:
Grupo de alunos conduzido por um Docente, no qual exercícios são publicados. Uma Turma *inativa* foi suspensa pela administração e bloqueia o acesso dos alunos e as alterações do docente.
_Evitar_: Classe, curso, grupo

**Chave de acesso** (`access_key`):
Código da Turma que o Aluno informa para pedir Matrícula.
_Evitar_: Código de convite, senha da turma

**Matrícula** (`student_turma`):
Vínculo entre um Aluno e uma Turma. Está *pendente* até o Docente aprovar e *ativa* depois disso. Recusar ou desvincular remove a Matrícula. Não existe Matrícula recusada.
_Evitar_: Inscrição, vínculo

## Exercícios

**Exercício**:
Conjunto ordenado de Questões criado por um Docente. Passa por *Rascunho* (editável), *Concluído* (pronto, com ao menos uma Questão) e *Publicado* (com pelo menos uma Publicação).
_Evitar_: Exercício ativo, prova, atividade, avaliação

**Questão**:
Enunciado discursivo de um Exercício, com Pontuação máxima e Resposta de referência.
_Evitar_: Pergunta, item

**Resposta de referência** (`expected_answer_hint`):
Conceitos que o Docente espera ver na resposta. Orientam a correção e só são mostrados ao Aluno depois do encerramento da Publicação.
_Evitar_: Dica, hint, gabarito

**Publicação** (`exercise_turmas`):
Disponibilização de um Exercício para uma Turma específica, com janela (abertura e encerramento) e limite de tentativas próprios. É a unidade que abre e encerra. "Encerrar o exercício" significa encerrar todas as suas Publicações.
_Evitar_: Exercício aberto/fechado, liberação

**Limite de tentativas** (`max_attempts`):
Quantas Tentativas um Aluno pode iniciar numa Publicação. Zero significa ilimitado.
_Evitar_: Número de chances

## Moderação

**Moderação** (`admin_review_status`):
Avaliação administrativa de um Exercício ou Questão. O resultado é *Aprovado*, *Sinalizado* ou *Bloqueado*.
_Evitar_: Revisão, censura

**Sinalizado** (`flagged`):
Resultado de Moderação que só avisa o Docente. Não afeta publicação nem visibilidade.
_Evitar_: Suspeito, em análise

**Bloqueado** (`blocked`):
Resultado de Moderação que impede publicar e encerra as Publicações abertas do Exercício afetado.
_Evitar_: Removido, censurado

## Respostas e correção

**Tentativa** (`attempt`):
Uma rodada de respostas de um Aluno a um Exercício, no contexto de uma Publicação. Passa por *Em andamento*, *Enviada* (aguardando a correção, mostrada como "Em correção") e *Corrigida*.
_Evitar_: Submissão, entrega, resposta (para a rodada inteira)

**Resposta** (`answer`):
O texto de um Aluno para uma Questão dentro de uma Tentativa, junto com a Pontuação e o Feedback que recebeu.
_Evitar_: Solução

**Correção por IA**:
Avaliação automática de cada Resposta à luz da Questão e da Resposta de referência. Produz Pontuação, Feedback e Motivos de desconto.
_Evitar_: Avaliação automática, grading

**Pontuação** (`score`):
Valor atribuído a uma Resposta, até a Pontuação máxima da Questão. A Pontuação da Tentativa é a soma das Pontuações das Respostas.
_Evitar_: Nota (como termo técnico)

**Motivo de desconto** (`deduction_reasons`):
Razão explícita pela qual uma Resposta recebeu menos que a Pontuação máxima.
_Evitar_: Penalidade, erro

**Feedback**:
Comentário textual da Correção por IA sobre uma Resposta, dirigido ao Aluno.
_Evitar_: Parecer, observação

**Fila de correção** (`grading_jobs`):
Lista de Tentativas Enviadas que aguardam a Correção por IA, processada fora da interação do Aluno.
_Evitar_: Job, tarefa de correção

**Reprocessamento** (`regrade`):
Nova Correção por IA de uma Tentativa que ainda está Enviada, depois de uma falha. Não existe Reprocessamento de Tentativa Corrigida.
_Evitar_: Recorreção, regrade, revisão de nota

**Suspeita de injeção** (`injection_logs`):
Registro de que uma Resposta continha um padrão de tentativa de manipular a Correção por IA.
_Evitar_: Fraude, ataque

## Rastreabilidade

**Auditoria** (`audit_logs`):
Histórico de quem fez cada ação sensível, sobre o quê e quando.
_Evitar_: Log, histórico de atividades
