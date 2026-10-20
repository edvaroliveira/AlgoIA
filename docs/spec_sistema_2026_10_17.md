# SPEC do Sistema AlgoIA — 2026-10-17

Descreve o comportamento que o AlgoIA implementa hoje. Substitui a SPEC de
2026-05-27, mantida como histórico. A linguagem segue o [GLOSSARY.md](../GLOSSARY.md),
e as decisões de arquitetura estão em [docs/adr/](adr/). Quando o texto cita um
identificador de código entre parênteses, é só para localizar o conceito.

## Escopo

O AlgoIA é uma aplicação web de apoio acadêmico em que:

- Docentes criam Turmas e Exercícios discursivos e publicam esses Exercícios para as próprias Turmas;
- Alunos entram nas Turmas pela Chave de acesso e respondem às Publicações abertas;
- as Respostas são avaliadas pela Correção por IA, fora da interação do Aluno;
- Admins moderam o conteúdo, aprovam Docentes e acompanham tudo pela Auditoria.

Atende uma única instituição de porte pequeno a médio, em hospedagem
compartilhada (ADR 0001).

## Papéis e fronteiras de acesso

Todo Usuário tem exatamente um papel. Cada fronteira é aplicada tanto no
controller quanto na consulta ao banco.

| Papel | Pode | Não pode |
|---|---|---|
| **Admin** | ver e operar todo o sistema; moderar; aprovar ou recusar Solicitações docentes; inativar e reativar Turmas; ajustar e encerrar Publicações; reprocessar Tentativas; exportar dados e Auditoria | — |
| **Docente** | operar só as próprias Turmas, Exercícios, Questões e as Tentativas dos próprios Alunos | ver ou alterar recursos de outro Docente (403); alterar Turma inativa |
| **Aluno** | ver Exercícios com Publicação em Turma ativa onde tem Matrícula ativa; responder dentro da janela; ver os próprios resultados | ver Exercício de outra Turma (404/403); escrever ou ler Tentativa de outro Aluno (403) |

Um Usuário que acessa uma rota de outro papel é redirecionado para o próprio
painel. A sessão é revalidada no banco a cada 60 segundos: papel, status da
conta e troca de senha. Uma conta que não está *ativa*, ou cuja senha mudou
depois do login, perde a sessão.

## Requisitos funcionais

### RF-01 Autenticação

- Login por e-mail e senha. Depois de 5 falhas em 5 minutos para o mesmo e-mail e IP, o login fica bloqueado temporariamente.
- Contas *pendente*, *recusado* ou *inativo* não entram, e cada caso tem sua mensagem.
- Quando a troca de senha é obrigatória, o Usuário só acessa a tela de troca até concluí-la.
- Logout encerra a sessão.

### RF-02 Senhas

- Senha forte: de 10 a 72 caracteres, com letra maiúscula, letra minúscula e número. Armazenada com bcrypt.
- O Admin gera um link de redefinição válido por 60 minutos. O token é guardado só como hash e só pode ser usado uma vez.

### RF-03 Cadastro de Aluno

- Cadastro público com nome, e-mail, senha e Chave de acesso. O Usuário nasce *pendente* e a Matrícula nasce *pendente*.
- O Aluno se torna *ativo* quando a primeira Matrícula é aprovada, e continua ativo mesmo que depois fique sem Matrícula.
- Um Aluno logado pode pedir Matrícula em outra Turma pela Chave. Há limite de tentativas, para impedir varredura de chaves.

### RF-04 Solicitação docente

- O cadastro público de Docente nasce desabilitado. O Admin liga ou desliga o cadastro, e cada mudança é auditada.
- A solicitação cria um Docente *pendente*, que só o Admin aprova ou recusa.

### RF-05 Turmas e Matrículas

- O Docente cria Turmas, cada uma com Chave de acesso própria, e pode gerar uma nova Chave.
- **Aprovar** torna *ativa* uma Matrícula *pendente*.
- **Recusar** remove uma Matrícula *pendente*. Tentar recusar uma Matrícula que não está pendente é negado e auditado como `reject_denied`.
- **Desvincular** remove uma Matrícula *ativa* e preserva o cadastro e o histórico do Aluno. Vale para uma Turma (`detach_turma`) ou para todas as Turmas do Docente (`detach`).
- **Turma inativa** (decisão do Admin): os Alunos perdem acesso aos Exercícios dela, Tentativas em andamento não podem mais salvar nem ser enviadas, e o Docente não consegue publicar, aprovar, recusar, desvincular nem gerar nova Chave. Cada uma dessas tentativas é auditada como `<ação>.blocked`. O Admin pode reativar a Turma.

### RF-06 Exercícios e Questões

- Estados do Exercício: **Rascunho** (editável) → **Concluído** (pronto, exige ao menos uma Questão) → **Publicado** (tem ao menos uma Publicação).
- Cada Questão tem enunciado, Resposta de referência, Pontuação máxima e ordem.
- Um Exercício só pode ser excluído enquanto não foi publicado e não tem Tentativas.

### RF-07 Publicação

- Publicar cria uma Publicação para cada Turma escolhida (ativa e do próprio Docente), cada uma com abertura, encerramento e Limite de tentativas próprios. Limite zero significa ilimitado. Ver ADR 0003.
- O Exercício não tem janela própria. "Encerrar o exercício" significa encerrar todas as suas Publicações.
- O Admin pode ajustar a janela e o limite de uma Publicação, encerrar e reabrir Publicações individualmente ou em lote, e encerrar todas as Publicações de uma Turma.
- O Aluno só vê um Exercício quando há Publicação para uma Turma ativa em que ele tem Matrícula ativa. Sem Publicação, a tela informa que não há publicação disponível e não mostra prazos.

### RF-08 Tentativas

- O Aluno só inicia uma Tentativa com a Publicação aberta e abaixo do Limite de tentativas. O início é serializado no banco, para impedir que dois inícios simultâneos ultrapassem o limite.
- Toda Tentativa pertence a uma Publicação (Exercício × Turma). Tentativas usadas, melhor Pontuação e Tentativa em andamento são sempre calculadas por Publicação.
- Uma Tentativa *Em andamento* da mesma Publicação é retomada em vez de criar outra.
- As Respostas são salvas automaticamente, Questão por Questão.
- Enviar grava todas as Respostas, muda a Tentativa para *Enviada* (mostrada como "Em correção") e a coloca na Fila de correção. O envio não depende da OpenAI. Enviar de novo uma Tentativa já enviada não tem efeito.
- Moderação *Bloqueado*, Publicação encerrada, Matrícula removida ou Turma inativa impedem salvar e enviar. A Tentativa em andamento é preservada.

### RF-09 Correção por IA

- Um worker agendado via cron processa a Fila de correção (ADR 0002). O padrão é até 3 tentativas com backoff, e jobs presos em processamento por mais de 15 minutos são recuperados.
- Cada Resposta é avaliada à luz da Questão e da Resposta de referência. O prompt do sistema fica separado do texto do Aluno.
- O retorno precisa ser JSON válido com Pontuação, Feedback, indicador de acerto e Motivos de desconto.
- Resposta vazia recebe Pontuação zero, com o Feedback "Questão não respondida." e Motivos de desconto padrão, sem chamada à IA.
- Quando a Questão não exige eficiência nem abordagem específica, uma Resposta julgada correta cujos únicos Motivos de desconto sejam ineficiência ou abordagem diferente recebe a Pontuação máxima.
- A Pontuação da Tentativa é a soma das Pontuações das Respostas. Com todas avaliadas, a Tentativa passa a *Corrigida*.
- Texto do Aluno com padrão de manipulação gera uma Suspeita de injeção, que guarda só um resumo, não o texto completo.

### RF-10 Reprocessamento

- Admin e Docente (este, só para os próprios Exercícios) podem reprocessar uma Tentativa *Enviada*, devolvendo-a à Fila de correção.
- Não existe Reprocessamento de Tentativa *Corrigida*. Se a correção já está em andamento, o pedido é recusado e auditado como `.skipped`.

### RF-11 Resultados

- O Aluno só vê o resultado de uma Tentativa *Corrigida*, e apenas das próprias Tentativas.
- A Resposta de referência só aparece ao Aluno depois do encerramento da Publicação ou quando ele esgota o Limite de tentativas.
- O Aluno vê a Pontuação, o Feedback e, nas Respostas sem Pontuação máxima, os Motivos de desconto em linguagem amigável, cada um com uma dica de estudo. Docente e Admin veem os Motivos de desconto de todas as Respostas.

### RF-12 Moderação

- O Admin modera Exercícios e Questões com o resultado *Aprovado*, *Sinalizado* ou *Bloqueado*, e pode registrar uma nota.
- *Sinalizado* é só um aviso ao Docente.
- *Bloqueado* impede publicar, encerra as Publicações abertas do Exercício afetado e tira o conteúdo do fluxo do Aluno.

### RF-13 Auditoria e exportação

- Toda ação sensível (administração, moderação, matrícula, publicação, correção, operações negadas e bloqueadas) gera um registro com ator, papel, ação, entidade, metadados, IP e user agent.
- O Admin consulta a Auditoria com filtros e a exporta em CSV e JSON. Usuários, Turmas e Exercícios também podem ser exportados.

### RF-14 Conta

- O Usuário pode enviar uma foto de perfil (JPEG, PNG ou WebP, até 2 MB) e removê-la. O envio tem limite de frequência.

### RF-15 Banco de exercícios

- O Docente cria um Exercício do zero ou parte do Banco de exercícios: na tela "Criar exercício", e também a partir de um Rascunho ("+ Do banco").
- O banco tem duas abas: **Banco institucional**, com os itens ativos curados pelo Admin, e **Meus exercícios**, com os Exercícios Concluídos ou Publicados do próprio Docente. A busca cobre título, descrição e enunciado das Questões. No banco institucional também há filtro por Tema.
- **Importar** copia só as Questões marcadas para um Rascunho novo (com título editável) ou para um Rascunho existente do Docente, depois das Questões que ele já tem. Depois disso, o Docente pode excluir Questões e acrescentar novas. A origem nunca muda, e Questões Bloqueadas não são copiadas.
- O Docente envia uma **Sugestão ao banco** a partir de um Exercício seu Concluído ou Publicado, desde que a Moderação esteja *Aprovado*. Uma Sugestão repetida não duplica.
- O Admin aprova a Sugestão (definindo os Temas) ou a recusa (com motivo mostrado ao Docente). Ele também pode incluir qualquer Exercício direto no banco, editar Temas e arquivar itens. Só Sugestões podem ser aprovadas. Tudo é auditado.
- Cada item do banco é uma cópia independente, feita no momento da Sugestão ou da inclusão direta. Apagar o Exercício de origem não afeta o banco. O banco não guarda janela nem Limite de tentativas, que continuam sendo da Publicação (ADR 0003).

### RF-16 Revisão de nota

- Na página de resultado, o Docente pode revisar a Pontuação de qualquer Resposta corrigida dos próprios Exercícios: nova Pontuação de 0 até o máximo da Questão e justificativa obrigatória, mostrada ao Aluno. A Pontuação da Tentativa é recalculada, a nota anterior fica registrada e a ação é auditada.
- O Aluno pode enviar um Pedido de revisão, com uma explicação, para cada Resposta própria que não tirou a Pontuação máxima, uma única vez por Resposta. Ele acompanha o pedido (aguardando, nota revisada ou nota mantida) e a justificativa do Docente.
- Os pedidos em aberto aparecem no painel do Docente ("Revisões pedidas"). O Docente responde alterando a nota ou mantendo-a com uma justificativa.

### RF-17 Avisos

- Um sino no topo mostra quantos Avisos não lidos o Usuário tem. A página de avisos lista os 50 mais recentes, com link para o assunto, e marca todos como lidos ao abrir.
- Geram Aviso: a Publicação de um Exercício (para os Alunos com Matrícula ativa nas Turmas escolhidas), a Tentativa Corrigida (para o Aluno), a Matrícula aprovada (para o Aluno), o Pedido de revisão (para o Docente) e a Revisão de nota, alterando ou mantendo a nota (para o Aluno).
- Também geram Aviso: o pedido de Matrícula (para o Docente) e a decisão do cadastro docente, aprovado ou recusado (para quem pediu).
- Prazo encerrando: ao abrir o app, o Aluno recebe um Aviso para cada Publicação aberta que encerra em até 24 h e na qual ainda não enviou Tentativa. Não há tarefa agendada.
- O mesmo Aviso não se repete (mesmo evento para o mesmo Usuário). Uma falha ao gerar Aviso nunca impede a ação que o originou nem derruba a página.

### RF-17a Avisos por e-mail

- Cada Aviso não lido criado nas últimas 24 h é enviado uma vez por e-mail a quem deixou a opção ligada em Minha conta (ligada por padrão). Aviso já lido no app não vira e-mail.
- Um worker no cron (a cada 5 minutos) gera antes o lembrete de prazo para todos os Alunos, mesmo os que não abriram o app, e então envia. Envio pelo mail() do PHP; sem remetente configurado nada é enviado.
- A decisão do cadastro docente é enviada também a quem foi recusado. Todo e-mail traz o link completo do assunto e o caminho para desligar os avisos.

### RF-18 Agenda do Aluno

- O Aluno vê, numa única lista, as Publicações abertas e futuras de todas as Turmas ativas em que tem Matrícula ativa, ordenadas pelo prazo, com os mesmos filtros de acesso da lista de Exercícios (Moderação e Questão Bloqueada).
- Cada item mostra a Turma, o prazo (ou a data de abertura, se futura) e os destaques: "Vence em menos de 24 h", "Ainda não tentado", "Tentativa em andamento" e "X de Y tentativas usadas" (Tentativas Enviadas ou Corrigidas).

### RF-19 Prática

- O Aluno escolhe um Tema do Banco de exercícios e recebe uma Questão sorteada entre os itens ativos, de preferência uma que ainda não praticou. Pode pedir outra Questão a qualquer momento.
- Ao enviar a resposta, a Correção por IA acontece na hora (sem a Fila de correção), e o Aluno vê a Pontuação, o Feedback e os Motivos de desconto com dica de estudo. As correções anteriores da mesma Questão ficam listadas.
- Não vale nota, não cria Tentativa e não é vista pelo Docente.
- Limite de 10 correções por Aluno por dia, para conter o custo da IA. Só correções bem-sucedidas contam. Se a IA falhar, o Aluno é avisado e o texto da resposta é mantido para reenviar.
- A detecção de injeção também vale para a prática.

### RF-20 Progresso por Tema

- O Aluno vê, para cada Tema do banco, quantas respostas já teve corrigidas e a média percentual, somando a Prática e as Tentativas Corrigidas com Questões vindas do banco. Questões criadas do zero não têm Tema e ficam fora.
- Classificação: com menos de 3 respostas corrigidas, "Poucos dados"; com média de 70% ou mais, "Ponto forte"; abaixo de 50%, "A melhorar"; entre as duas, "Em evolução". Cada Tema tem atalho para praticar.
- Importar guarda a Questão de origem no banco (também ao copiar de um Exercício próprio que veio do banco). Importações anteriores foram ligadas pela migration 027 por enunciado e Resposta de referência idênticos.

### RF-20a Evolução do Aluno

- Em Progresso, o Aluno vê cada Publicação em que tem duas ou mais Tentativas Corrigidas: nota da primeira, nota da última (em ordem de envio), diferença e "Melhorou", "Manteve" ou "Caiu". Tentativa em andamento ou aguardando correção não conta.

### RF-21 Desempenho da Turma

- Na Turma, o Docente abre "Desempenho": grade com os Alunos de Matrícula ativa e os Exercícios publicados nela, com a melhor nota de cada Aluno. Também mostra "Pendente" (Tentativa Enviada aguardando correção) e "Em andamento". Só contam Tentativas feitas nesta Turma.
- Média do Aluno: média percentual das melhores notas que ele tem. Média da Turma: média das médias dos Alunos. Ficam destacados quem está abaixo da média da Turma e quem nunca tentou.
- Ao escolher um Exercício, a página mostra, por Questão, a média percentual e até três Motivos de desconto mais comuns, considerando a melhor Tentativa Corrigida de cada Aluno.
- Ao escolher um Exercício, a página também mostra a Evolução de quem refez: primeira e última Tentativa Corrigida de cada Aluno ativo e quantos melhoraram.
- "Exportar CSV" gera as mesmas colunas da grade (separador ";", vírgula decimal). A exportação é auditada. Só o Docente dono da Turma acessa.

## Requisitos não funcionais

### RNF-01 Segurança

- CSRF validado em todo POST (403 sem token válido).
- Content-Security-Policy com nonce, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy` em todas as respostas.
- Cookie de sessão `HttpOnly` e `SameSite=Lax`. Sob HTTPS, também `Secure` com prefixo `__Host-`.
- Segredos só no ambiente (`.env`), nunca versionados.

### RNF-02 Confiabilidade

- Uma falha da IA nunca perde a Tentativa: ela continua *Enviada*, recuperável por nova tentativa automática ou por Reprocessamento.
- Escrita de Pontuação e estado da Tentativa, início e envio de Tentativa, consumo de token de redefinição e remoção do último Admin são transacionais, com lock de linha.

### RNF-03 Observabilidade

- Falhas de correção geram Auditoria e `error_log`, com latência por Resposta e por Tentativa.
- O painel do Admin resume a Fila de correção (em fila, em processamento, falhas, atrasos, concluídos recentes) e as pendências operacionais.
- O worker sai com código diferente de zero quando algum job do lote falha.

### RNF-04 Operação

- PHP 8.0 ou mais novo, sem dependências externas (ADR 0001). A CI testa no 8.3 e no 8.5, e o smoke estático proíbe as APIs deprecated no 8.5. Funciona em subpasta.
- Banco de produção: Percona Server 5.7, com `sql_mode` não estrito. A CI de integração usa o mesmo motor e o mesmo modo.
- Instalação limpa: aplicar só `001_create_tables.sql`, a linha de base do schema (2026-10-19). Bases existentes: aplicar em ordem as migrations incrementais posteriores (a partir da `023`), conforme `docs/deploy_operacional.md`.

## Integrações externas

### OpenAI

- API de chat completions, com modelo configurável (`OPENAI_MODEL`, padrão `gpt-4o`).
- Timeout padrão de 30 segundos (`OPENAI_TIMEOUT_SECONDS`) e até 3 tentativas (`OPENAI_MAX_RETRIES`) com backoff exponencial em 429 e 5xx.
- Sem chave configurada, a correção falha de imediato, sem gastar tentativas.

## Verificação

| Suíte | O que garante |
|---|---|
| `bin/smoke_static.php` | invariantes de código (consultas de acesso, moderação) |
| `bin/run_tests.php` | regras unitárias sem banco |
| `bin/run_db_tests.php` | camada de dados em SQLite |
| `bin/run_integration_tests.php` | models, services e locks reais no banco; fluxo HTTP autenticado de autorização negativa |
| `bin/smoke_schema.php` | schema esperado, incluindo a ausência de colunas legadas |

## Limites conhecidos

- Sem cron configurado, nenhuma Tentativa é corrigida: elas ficam paradas em *Enviada*.
- Não há monitoramento externo da fila nem da latência da IA. A visibilidade fica no painel e no `error_log`.
- A interface é renderizada no servidor e não tem testes de navegador.
