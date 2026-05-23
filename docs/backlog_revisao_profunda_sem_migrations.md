# Backlog da Revisao Profunda - Exceto Migrations

Este backlog consolida os achados da revisao profunda do codigo, excluindo o item de migrations/idempotencia por solicitacao.

## P0 - Garantir que questoes sem resposta contem na correcao e na nota maxima

**Status:** implementado.

**Problema:** no envio da tentativa, o sistema salva apenas respostas nao vazias. Como a correcao e a tela de resultado usam as linhas existentes em `answers`, questoes deixadas em branco podem sumir da correcao, do detalhe e ate do calculo da nota maxima exibida.

**Impacto:** aluno pode ter uma tentativa incompleta apresentada com denominador menor, prejudicando a confiabilidade da nota e da auditoria pedagogica.

**Evidencias:**
- `app/Controllers/AttemptController.php`
- `app/Models/Answer.php`
- `app/Services/AttemptGradingService.php`
- `views/student/results/show.php`

**Escopo:**
- No submit, criar ou atualizar uma resposta para toda questao da tentativa, mesmo quando o texto estiver vazio.
- Permitir persistir `student_answer` vazio ou padronizar como string vazia.
- Garantir que resposta vazia receba nota `0` e feedback claro, ou seja exibida como nao respondida.
- Calcular `maxScore` pelo conjunto de questoes do exercicio, nao apenas pelas respostas existentes.
- Exibir todas as questoes no resultado, incluindo as nao respondidas.

**Criterios de aceite:**
- Tentativa com questao em branco mostra essa questao no resultado.
- Questao em branco conta no valor maximo total.
- Questao em branco nao chama IA desnecessariamente.
- Nota total final considera zero para questoes sem resposta.
- Manipular o POST removendo campos de resposta nao reduz o denominador da avaliacao.

**Risco:** alto, pois afeta nota, resultado e justica da avaliacao.

## P1 - Fazer revalidacao de sessao falhar de forma segura

**Status:** implementado.

**Problema:** a revalidacao de sessao consulta o banco em `Core\Auth`, mas se a consulta falhar, o catch apenas registra log e deixa a sessao antiga continuar.

**Impacto:** em caso de falha de banco, alteracoes administrativas recentes, como inativacao, troca de role ou exigencia de troca de senha, podem nao ser aplicadas naquela requisicao.

**Evidencias:**
- `core/Auth.php`

**Escopo:**
- Alterar `refreshSessionUser()` para falhar fechado em erro de consulta.
- Encerrar sessao ou redirecionar para login quando nao for possivel confirmar usuario ativo.
- Opcionalmente exibir mensagem generica de indisponibilidade, sem detalhes tecnicos.
- Manter logs tecnicos no `error_log`.

**Criterios de aceite:**
- Se a consulta ao usuario falhar, a requisicao autenticada nao continua com dados antigos.
- Usuario inexistente, inativo ou rejeitado perde acesso imediatamente.
- Fluxos publicos de login, reset e cadastro continuam funcionando.
- Erros tecnicos nao vazam para a interface.

**Risco:** medio-alto, pois afeta seguranca operacional e disponibilidade.

## P1 - Liberar gabarito para professor/admin no detalhe interno da tentativa

**Status:** implementado.

**Problema:** o detalhe interno de tentativa reutiliza a regra do aluno para exibir a resposta esperada. Em exercicios ainda abertos, professor/admin podem ver "Resposta esperada indisponivel", mesmo estando em contexto de revisao interna.

**Impacto:** dificulta revisao pedagogica, auditoria de correcao e verificacao dos motivos de desconto.

**Evidencias:**
- `app/Controllers/AttemptController.php`
- `views/student/results/show.php`

**Escopo:**
- Quando `internalReview` for verdadeiro, sempre habilitar exibicao da resposta esperada.
- Ajustar textos da view, se necessario, para diferenciar contexto do aluno e contexto interno.
- Garantir que aluno continue vendo gabarito apenas nas regras atuais.

**Criterios de aceite:**
- Professor/admin ve resposta esperada no detalhe da tentativa corrigida.
- Aluno nao ganha acesso antecipado ao gabarito.
- A mesma view continua funcionando para os dois contextos.

**Risco:** medio, pois envolve visibilidade de gabarito.

## P2 - Refinar classificacao historica de solicitacoes docentes

**Status:** implementado.

**Problema:** a classificacao inicial de `registration_source` considera docente com `registration_note` como cadastro publico. Isso pode classificar incorretamente docentes criados manualmente com observacao, ou deixar fora solicitacoes antigas sem nota.

**Impacto:** historico de solicitacoes docentes pode ficar parcialmente impreciso.

**Evidencias:**
- `database/migrations/012_user_registration_source.sql`
- `app/Models/User.php`
- `views/admin/teacher_requests/history.php`

**Escopo:**
- Revisar criterios para preencher `registration_source` em dados existentes.
- Avaliar uso de auditoria `auth.teacher_registration_request` para identificar solicitacoes reais.
- Criar ajuste manual ou script auxiliar para bases antigas, se necessario.
- Documentar que novos registros usam origem correta automaticamente.

**Criterios de aceite:**
- Novos cadastros docentes publicos entram como `teacher_public`.
- Historico de solicitacoes nao mistura docentes manuais recentes.
- Bases antigas possuem caminho documentado para correcao de classificacao.

**Risco:** baixo, pois afeta historico e relatorio, nao o fluxo principal.

## Ordem Sugerida

1. Garantir que questoes sem resposta contem na correcao e na nota maxima.
2. Fazer revalidacao de sessao falhar de forma segura.
3. Liberar gabarito para professor/admin no detalhe interno da tentativa.
4. Refinar classificacao historica de solicitacoes docentes.

## Observacoes

- O primeiro item deve ser tratado antes de novas avaliacoes em producao, pois pode alterar resultado de nota.
- O segundo item melhora seguranca, mas deve ser testado em ambiente onde o banco esteja estavel para evitar logout em massa por falhas temporarias.
- O item de gabarito precisa preservar cuidadosamente a diferenca entre visao do aluno e visao interna.
