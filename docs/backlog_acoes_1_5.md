# Backlog de Correcoes Prioritarias

Este backlog cobre as acoes 1 a 5 identificadas na revisao do sistema AlgoIA.

## P0 - Bloquear submissao fora da janela do exercicio

**Status:** implementado.

**Problema:** o sistema valida se o exercicio esta aberto ao iniciar a tentativa, mas nao revalida no envio final nem no autosave.

**Objetivo:** impedir que respostas sejam salvas ou submetidas depois do fechamento, salvo se houver uma regra explicita de tolerancia.

**Escopo:**
- Revalidar janela de publicacao no `AttemptController::submit`.
- Revalidar janela no `AttemptController::saveAnswer`.
- Definir mensagem clara quando o prazo tiver encerrado.
- Garantir que uma tentativa iniciada antes do prazo nao possa ser enviada depois do fechamento sem regra formal.

**Criterios de aceite:**
- Dado um exercicio fechado, o aluno nao consegue salvar novas respostas.
- Dado um exercicio fechado, o aluno nao consegue submeter a tentativa.
- O aluno recebe mensagem explicando que o prazo encerrou.
- Tentativas ja avaliadas continuam visiveis no resultado.

**Risco:** alto, pois afeta integridade de avaliacao.

## P0 - Trocar exclusao global de aluno por desvinculacao segura

**Status:** implementado.

**Problema:** um professor pode remover um aluno do sistema inteiro, apagando vinculos, tentativas e dados que podem pertencer a outras turmas/docentes.

**Objetivo:** permitir que o professor remova o aluno apenas do seu contexto, preservando historico global.

**Escopo:**
- Substituir `deleteStudentWithRelations` por uma operacao de desvinculo.
- Remover somente registros `student_turma` das turmas daquele professor.
- Preservar `users`, `attempts`, `answers` e historico de outras turmas.
- Atualizar textos da interface de "excluir aluno" para "remover da turma" ou "desvincular".
- Reservar exclusao total para administradores.

**Criterios de aceite:**
- Professor remove aluno apenas das suas turmas.
- O aluno permanece no sistema se tiver outras turmas.
- Tentativas e respostas anteriores nao sao apagadas por acao do professor.
- Auditoria registra o desvinculo com professor, aluno e turma(s).

**Risco:** alto, pois envolve perda de dados.

## P1 - Fixar regras de tentativa por turma/publicacao

**Status:** implementado.

**Problema:** quando o aluno pertence a mais de uma turma com o mesmo exercicio, o sistema agrega regras com `MAX`, podendo aplicar prazo ou limite de tentativas incorreto.

**Objetivo:** aplicar regras da publicacao correta para cada tentativa.

**Escopo:**
- Definir regra de selecao da publicacao quando o aluno tiver mais de uma turma elegivel.
- Adicionar `turma_id` ou referencia equivalente em `attempts`.
- Ao iniciar tentativa, gravar a turma/publicacao usada.
- Usar essa turma/publicacao para limite de tentativas, prazo, resultado e exibicao do gabarito.
- Ajustar consultas em `Exercise` e `Attempt` que hoje agregam `MAX(et.max_attempts)` e `MAX(et.closes_at)`.

**Criterios de aceite:**
- Aluno em duas turmas nao recebe automaticamente o maior limite de tentativas.
- Resultado usa a data de fechamento da publicacao vinculada a tentativa.
- Contagem de tentativas respeita a regra da turma/publicacao correta.
- Consultas de dashboard continuam exibindo exercicios sem duplicidade.

**Risco:** medio/alto, pois muda contrato de dados e consultas principais.

## P1 - Remover chamadas OpenAI de dentro da transacao

**Status:** implementado.

**Problema:** a transacao de banco fica aberta enquanto o sistema aguarda chamadas externas para avaliacao por IA.

**Objetivo:** reduzir lock, falhas parciais e lentidao percebida no envio.

**Escopo:**
- Salvar respostas antes da avaliacao.
- Mudar tentativa para um estado intermediario, como `submitted` ou `grading`.
- Executar chamadas OpenAI fora de transacao longa.
- Persistir notas e feedbacks em uma transacao curta.
- Definir comportamento em falha: manter tentativa enviada aguardando reprocessamento ou permitir nova tentativa de correcao.

**Criterios de aceite:**
- Nenhuma chamada HTTP externa ocorre com transacao de banco aberta.
- Falha da OpenAI nao perde respostas do aluno.
- Tentativa fica com status rastreavel quando a avaliacao falha.
- O aluno ve uma mensagem clara de avaliacao pendente ou indisponivel.

**Risco:** medio, pois afeta fluxo de submissao e estado da tentativa.

## P1 - Criar fluxo seguro de reset e troca obrigatoria de senha

**Problema:** o admin gera senha temporaria e ela aparece em mensagem flash, sem troca obrigatoria no proximo login.

**Objetivo:** tornar redefinicao de senha mais segura e auditavel.

**Escopo:**
- Criar campo para exigir troca de senha no proximo login, por exemplo `must_change_password`.
- Evitar exibir senha temporaria em flash permanente.
- Criar tela de troca obrigatoria apos login.
- Invalidar sessoes antigas apos reset, se aplicavel.
- Registrar reset e troca de senha na auditoria.

**Criterios de aceite:**
- Usuario com senha resetada e obrigado a trocar a senha ao entrar.
- A nova senha segue a politica minima ja usada no cadastro.
- O admin nao precisa conhecer a senha definitiva do usuario.
- Auditoria registra quem resetou e quando o usuario concluiu a troca.

**Risco:** medio, pois afeta autenticacao e experiencia de login.

## Ordem Sugerida

1. Bloquear submissao fora da janela.
2. Trocar exclusao global por desvinculacao segura.
3. Fixar regras por turma/publicacao.
4. Remover OpenAI de transacao longa.
5. Criar reset seguro com troca obrigatoria.

## Observacoes Tecnicas

- Antes de implementar os itens P1 que alteram schema, criar migrations novas e evitar editar migrations antigas ja aplicadas em producao.
- Adicionar testes manuais documentados se o projeto continuar sem suite automatizada.
- Priorizar preservacao de dados e mensagens claras para aluno/professor.
