<?php

declare(strict_types=1);

/**
 * Banco de algoritmos: população mínima de teste.
 * 6 tópicos × 10 Exercícios × 5 Questões = 300 Questões únicas.
 *
 * Rigidez de correção (como a Correção por IA a interpreta):
 *  - flexivel: enunciado aberto; a referência lista os conceitos essenciais e
 *    uma solução correta por outro caminho recebe a Pontuação máxima;
 *  - padrao:   a referência traz a solução completa (Algoritmo/VAR/INICIO/FIM.)
 *    e os conceitos exigidos;
 *  - rigida:   o enunciado exige a abordagem ("deve usar", "obrigatoriamente"),
 *    o que desliga a promoção à Pontuação máxima por abordagem diferente.
 * Pontos: 1.0 básica, 2.0 intermediária, 3.0 elaborada.
 *
 * Estrutura de cada tópico:
 *   ['tema' => string, 'exercicios' => [[
 *      'titulo' => string, 'descricao' => string,
 *      'questoes' => [['rigidez' => string, 'pontos' => float, 'enunciado' => string, 'referencia' => string], ...]
 *   ], ...]]
 */
function banco_algoritmos_dados(): array
{
  $files = glob(__DIR__ . '/banco_algoritmos/*.php') ?: [];
  sort($files);

  return array_map(static fn(string $file): array => require $file, $files);
}
