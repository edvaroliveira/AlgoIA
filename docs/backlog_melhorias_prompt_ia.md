# Backlog de Melhorias do Prompt de IA

Este backlog cobre ajustes recomendados para tornar a avaliacao automatica mais consistente, auditavel e adequada ao contexto educacional do AlgoIA.

## P0 - Adicionar rubrica objetiva de pontuacao ao prompt

**Status:** implementado.

**Problema:** a IA atribui nota de forma livre, sem faixas claras de pontuacao. Isso pode gerar variacao entre respostas parecidas.

**Objetivo:** tornar a nota mais previsivel e consistente.

**Escopo:**
- Inserir rubrica por faixas no prompt do sistema.
- Definir criterios para 90-100%, 70-89%, 40-69%, 10-39% e 0%.
- Orientar a IA a sempre relacionar a nota ao grau de cobertura dos conceitos esperados.
- Evitar que respostas apenas com palavras-chave recebam nota alta.

**Criterios de aceite:**
- Prompt contem rubrica objetiva por faixa.
- Respostas vagas ou apenas com palavras-chave nao recebem nota alta.
- Feedback explica, de forma curta, por que a resposta ficou naquela faixa.
- JSON final continua no formato esperado pelo sistema.

**Risco:** alto, pois afeta diretamente notas dos alunos.

## P0 - Reequilibrar o prompt para reduzir benevolencia excessiva

**Status:** implementado.

**Problema:** o prompt atual repete muitas vezes que abordagens alternativas nao devem ser penalizadas, o que pode deixar a avaliacao permissiva demais.

**Objetivo:** manter justica para solucoes equivalentes sem afrouxar criterios tecnicos.

**Escopo:**
- Reduzir repeticoes sobre nao penalizar abordagem alternativa.
- Reforcar que conceitos centrais do gabarito precisam aparecer de forma logicamente correta.
- Diferenciar resposta alternativa correta de resposta incompleta, vaga ou superficial.
- Reforcar que detalhes extras nao compensam ausencia da logica principal.

**Criterios de aceite:**
- Prompt aceita solucoes tecnicamente equivalentes.
- Prompt nao aceita resposta vaga como correta.
- O texto fica mais curto e menos redundante.
- A avaliacao continua didatica, mas mais rigorosa.

**Risco:** alto, pois muda o comportamento de correcao.

## P1 - Salvar motivos de desconto no banco

**Status:** implementado.

**Problema:** `deduction_reasons` e retornado pela IA, mas nao e persistido em `answers`.

**Objetivo:** permitir auditoria pedagogica, filtros e analise de padroes de erro.

**Escopo:**
- Criar migration para adicionar `deduction_reasons_json` em `answers`.
- Atualizar `Answer::updateAiResult`.
- Atualizar `AttemptGradingService` para persistir os motivos.
- Exibir motivos no detalhe do resultado ou em tela administrativa, se fizer sentido.

**Criterios de aceite:**
- Motivos retornados pela IA sao salvos.
- Dados sao armazenados como JSON valido.
- Respostas antigas continuam funcionando sem motivos salvos.
- Futuras telas podem consultar esses dados.

**Risco:** medio, pois altera schema e fluxo de persistencia.

## P1 - Exibir alerta de possivel prompt injection para professor/admin

**Status:** implementado.

**Problema:** tentativas de prompt injection sao registradas em `injection_logs`, mas nao aparecem de forma operacional para quem revisa resultados.

**Objetivo:** tornar tentativas suspeitas visiveis sem expor o conteudo sensivel da resposta.

**Escopo:**
- Associar logs de injection a respostas/tentativas.
- Exibir indicador no resultado da tentativa para docente/admin.
- Criar filtro ou card administrativo de tentativas com injection detectado.
- Manter privacidade: nao mostrar a resposta completa no log.

**Criterios de aceite:**
- Professor/admin ve quando uma resposta foi marcada como suspeita.
- Aluno nao ve alertas internos de seguranca.
- Conteudo sensivel permanece omitido em logs.
- Registro continua resiliente mesmo se logging falhar.

**Risco:** medio, pois envolve seguranca e moderacao.

## P1 - Tornar modelo configuravel via ambiente

**Status:** implementado.

**Problema:** o modelo esta fixo em `config/openai.php` como `gpt-4o`.

**Objetivo:** permitir troca de modelo sem editar codigo.

**Escopo:**
- Ler modelo de `OPENAI_MODEL` no `.env`.
- Manter valor padrao seguro.
- Documentar variavel no arquivo de exemplo, se existir.
- Evitar quebrar ambientes que nao tenham a variavel definida.

**Criterios de aceite:**
- `config/openai.php` usa `env('OPENAI_MODEL', ...)`.
- Sistema continua funcionando sem `OPENAI_MODEL`.
- Troca de modelo pode ser feita apenas por configuracao.

**Risco:** baixo.

## P2 - Criar testes/casos de avaliacao do prompt

**Status:** implementado em base manual em `docs/casos_avaliacao_prompt_ia.md`.

**Problema:** nao ha conjunto de exemplos para verificar se mudancas no prompt melhoram ou pioram a avaliacao.

**Objetivo:** criar uma base manual ou automatizada de casos de resposta.

**Escopo:**
- Criar arquivo com questoes, gabaritos e respostas exemplo.
- Incluir respostas corretas, parcialmente corretas, vagas, erradas e com prompt injection.
- Definir nota esperada aproximada por caso.
- Usar esses casos para revisar alteracoes no prompt antes de deploy.

**Criterios de aceite:**
- Existe um conjunto minimo de casos de avaliacao.
- Cada caso tem faixa de nota esperada.
- Casos cobrem algoritmo, pseudocodigo, explicacao textual e injection.
- Mudancas futuras no prompt podem ser comparadas com essa base.

**Risco:** baixo, mas aumenta muito a confianca.

## Ordem Sugerida

1. Adicionar rubrica objetiva de pontuacao.
2. Reequilibrar o prompt para reduzir benevolencia excessiva.
3. Tornar modelo configuravel via ambiente.
4. Salvar motivos de desconto no banco.
5. Exibir alerta de possivel prompt injection para professor/admin.
6. Criar testes/casos de avaliacao do prompt.

## Observacoes

- Os itens P0 devem ser avaliados com exemplos reais de respostas antes de publicar em producao.
- As mudancas de prompt nao exigem migration, mas podem alterar notas futuras.
- Salvar `deduction_reasons` e exibir injection exigem alteracoes de schema e UI.
