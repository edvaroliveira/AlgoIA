# Backlog de Ajustes Pos-Revisao

Este backlog cobre os ajustes identificados apos a segunda revisao do sistema, ja considerando as acoes 1 a 5 implementadas.

## P0 - Criar reprocessamento de tentativas pendentes de correcao

**Status:** implementado.

**Problema:** quando a avaliacao por IA falha, a tentativa fica com status `submitted`, conta como tentativa usada e nao existe caminho operacional para reprocessar.

**Objetivo:** garantir que nenhuma tentativa enviada fique presa sem nota, sem feedback e sem acao possivel.

**Escopo:**
- Criar metodo de reprocessamento para tentativas `submitted`.
- Permitir que admin ou docente reexecute a avaliacao automatica.
- Listar tentativas pendentes em uma tela operacional.
- Preservar respostas originais do aluno.
- Registrar auditoria quando uma tentativa for reprocessada.
- Definir mensagem clara para aluno enquanto a tentativa estiver pendente.

**Criterios de aceite:**
- Tentativas com status `submitted` aparecem para admin/docente.
- Admin ou docente consegue reprocessar uma tentativa pendente.
- Ao reprocessar com sucesso, a tentativa muda para `graded`.
- Em nova falha, a tentativa permanece `submitted` e o erro fica rastreavel em log/auditoria.
- O aluno nao perde respostas nem precisa reenviar manualmente.

**Risco:** alto, pois afeta notas, limite de tentativas e suporte.

## P0 - Corrigir cadeia de migrations e schema base

**Problema:** as migrations atuais estao inconsistentes para instalacao limpa, com dois arquivos `002` e uma migration tentando adicionar coluna ja criada no schema inicial.

**Objetivo:** tornar instalacao e atualizacao de banco previsiveis.

**Escopo:**
- Revisar todas as migrations existentes.
- Definir se o projeto usa `001_create_tables.sql` como schema base atual ou migrations incrementais desde zero.
- Corrigir duplicidade de numbering `002`.
- Evitar `ADD COLUMN` duplicado para `exercises.status`.
- Atualizar `001_create_tables.sql` ou criar documento de ordem oficial de execucao.
- Garantir que migrations 007 e 008 sejam aplicadas apos bases antigas.

**Criterios de aceite:**
- Uma instalacao limpa executa sem erro.
- Uma base antiga consegue aplicar migrations incrementais sem conflito.
- Existe uma ordem oficial documentada de execucao.
- As colunas `attempts.turma_id`, `users.must_change_password` e `users.password_reset_at` ficam presentes.

**Risco:** alto, pois pode bloquear deploy e ambientes novos.

## P1 - Melhorar reset de senha com token e expiracao

**Problema:** o reset administrativo ainda exibe senha temporaria em flash, mesmo exigindo troca obrigatoria no proximo login.

**Objetivo:** reduzir exposicao da senha temporaria e deixar o reset mais auditavel.

**Escopo:**
- Substituir ou complementar senha temporaria por token de redefinicao.
- Adicionar expiracao para token/reset.
- Criar tela de definicao de nova senha por token.
- Invalidar token apos uso.
- Manter auditoria de quem gerou o reset e quando o usuario concluiu a troca.
- Evitar exibir senha temporaria em mensagens persistidas.

**Criterios de aceite:**
- Admin consegue iniciar reset sem conhecer a senha definitiva do usuario.
- Token expira apos prazo definido.
- Token usado uma vez nao pode ser reutilizado.
- Usuario define nova senha seguindo a politica minima.
- Auditoria registra geracao e conclusao do reset.

**Risco:** medio, pois afeta autenticacao.

## P1 - Criar painel de correcoes pendentes

**Problema:** tentativas `submitted` ficam visiveis para o aluno como "Em correcao", mas nao ha visao administrativa/docente consolidada.

**Objetivo:** facilitar suporte e acompanhamento de falhas ou atrasos na correcao automatica.

**Escopo:**
- Adicionar contagem de tentativas pendentes no dashboard do admin.
- Adicionar lista de pendencias por docente/exercicio/turma.
- Permitir filtro por data de envio e tempo pendente.
- Linkar cada item para detalhe da tentativa ou reprocessamento.

**Criterios de aceite:**
- Admin visualiza total de tentativas pendentes.
- Docente visualiza pendencias dos seus exercicios.
- Itens pendentes exibem aluno, exercicio, turma e data de envio.
- Itens podem ser reprocessados a partir da lista, quando o item P0 de reprocessamento estiver pronto.

**Risco:** medio, pois melhora operacao e suporte.

## P2 - Remover ou renomear metodos legados de tentativa

**Problema:** `Attempt::submit()` ainda existe, mas o fluxo novo usa `markSubmitted()` e `markGraded()`. O nome antigo pode induzir uso incorreto.

**Objetivo:** reduzir ambiguidade para manutencao futura.

**Escopo:**
- Verificar se `Attempt::submit()` ainda possui chamadas.
- Remover o metodo se estiver sem uso.
- Se for mantido, renomear para deixar claro que marca como corrigida.
- Ajustar comentarios e nomes para distinguir envio de correcao.

**Criterios de aceite:**
- Nao existe metodo com nome ambiguo para envio/correcao.
- Fluxo de tentativa usa nomes consistentes: enviada, pendente e corrigida.
- Busca no codigo nao encontra chamadas obsoletas.

**Risco:** baixo, pois e limpeza de manutencao.

## Ordem Sugerida

1. Criar reprocessamento de tentativas pendentes.
2. Corrigir cadeia de migrations e schema base.
3. Criar painel de correcoes pendentes.
4. Melhorar reset de senha com token e expiracao.
5. Remover ou renomear metodos legados de tentativa.

## Observacoes

- O item de reprocessamento deve vir antes do painel se for preciso entregar valor rapido; o painel pode inicialmente listar pendencias com acao simples.
- A correcao de migrations deve ser feita antes de qualquer deploy em ambiente novo.
- O reset com token pode exigir nova tabela ou novas colunas em `users`.
