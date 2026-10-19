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
Vínculo entre um Aluno e uma Turma. Está *pendente* até o Docente aprovar e *ativa* depois disso. Não existe Matrícula recusada.
_Evitar_: Inscrição, vínculo

**Recusar**:
Remover uma Matrícula *pendente*. O Aluno pode pedir de novo com a Chave de acesso.
_Evitar_: Rejeitar aluno, remover (para pendentes)

**Desvincular**:
Remover uma Matrícula *ativa*, preservando o cadastro e o histórico do Aluno. Pode valer para uma Turma só ou para todas as Turmas do Docente.
_Evitar_: Expulsar, excluir aluno, recusar (para ativos)

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

## Banco de exercícios

**Banco de exercícios**:
Acervo institucional de Exercícios curado pelo Admin, do qual qualquer Docente pode Importar Questões. Cada item é uma cópia independente do Exercício de origem.
_Evitar_: Biblioteca (já é o nome da lista de Exercícios do Docente), repositório, compartilhados

**Tema**:
Rótulo definido pelo Admin para classificar itens do Banco de exercícios e filtrá-los.
_Evitar_: Tag, categoria, assunto

**Sugestão ao banco**:
Pedido de um Docente para que um Exercício seu, Concluído ou Publicado, entre no Banco de exercícios. O Admin aprova (com Temas) ou recusa (com motivo).
_Evitar_: Compartilhamento, publicação no banco

**Importar**:
Copiar Questões escolhidas, do Banco de exercícios ou de um Exercício próprio, para um Rascunho do Docente. A origem nunca muda, e Questões Bloqueadas não são copiadas.
_Evitar_: Clonar, vincular, reutilizar (para a operação)

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

**Revisão de nota** (`score_reviews`):
Alteração, pelo Docente, da Pontuação que a Correção por IA deu a uma Resposta, sempre com justificativa mostrada ao Aluno. A nota anterior fica registrada, e a Pontuação da Tentativa é recalculada. Também é a resposta do Docente a um Pedido de revisão, que pode alterar ou manter a nota.
_Evitar_: Correção manual, override, Reprocessamento (que é refazer a Correção por IA)

**Pedido de revisão**:
Solicitação do Aluno, com uma explicação, para que o Docente reveja a Pontuação de uma Resposta que não tirou a Pontuação máxima. Um por Resposta.
_Evitar_: Recurso, contestação, reclamação

**Aviso** (`notifications`):
Mensagem curta dentro do app para um Usuário sobre um acontecimento que pede atenção: Publicação nova, prazo encerrando, Tentativa Corrigida, Matrícula aprovada, Pedido de revisão recebido ou respondido. Fica não lido até o Usuário abrir a página de avisos.
_Evitar_: Notificação push, e-mail, Mural

**Agenda**:
Lista, para o Aluno, das Publicações abertas e futuras de todas as suas Turmas, ordenadas pelo prazo, com as Tentativas já usadas.
_Evitar_: Calendário, cronograma

**Prática** (`practice_answers`):
Treino do Aluno com Questões do Banco de exercícios, fora de Turma e de Publicação, sem valer nota. Cada resposta recebe Correção por IA na hora, com Pontuação, Feedback e Motivos de desconto, dentro de um limite diário de correções. Não gera Tentativa.
_Evitar_: Simulado, treino livre, Tentativa de prática

**Progresso por Tema**:
Média percentual do Aluno em cada Tema, somando as correções da Prática e as Respostas corrigidas de Tentativas cujas Questões vieram do Banco de exercícios. Classifica o Tema como ponto forte, em evolução ou a melhorar quando há correções suficientes.
_Evitar_: Boletim, ranking, nota do Tema

**Suspeita de injeção** (`injection_logs`):
Registro de que uma Resposta continha um padrão de tentativa de manipular a Correção por IA.
_Evitar_: Fraude, ataque

## Rastreabilidade

**Auditoria** (`audit_logs`):
Histórico de quem fez cada ação sensível, sobre o quê e quando.
_Evitar_: Log, histórico de atividades
