<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Similaridade entre enunciados de Questões (0–100), calculada no próprio
 * sistema, sem IA: determinística, instantânea e sem custo. Serve para barrar
 * cópias e quase-cópias no Banco de exercícios.
 *
 * Medida: coeficiente de Dice sobre trigramas de caracteres do texto
 * normalizado (minúsculas, sem acento, sem pontuação, espaços colapsados).
 * Tolera acento, caixa, pontuação e pequenas edições; não reconhece a mesma
 * ideia escrita com outras palavras (paráfrase).
 */
final class QuestionSimilarity
{
  /** Acentos do português (e afins) → letra base. Sem iconv/intl de propósito. */
  private const ACCENTS = [
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'ç' => 'c', 'ñ' => 'n',
  ];

  public static function normalize(string $text): string
  {
    $text = strtr(mb_strtolower($text, 'UTF-8'), self::ACCENTS);
    // Qualquer coisa que não seja letra ou dígito vira espaço (pontuação, travessão, tabulação…)
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

    return trim($text);
  }

  /**
   * Conjunto de trigramas (chaves do array) do texto normalizado.
   *
   * @return array<string, true>
   */
  public static function trigrams(string $text): array
  {
    $norm = self::normalize($text);
    if ($norm === '') {
      return [];
    }
    $padded = ' ' . $norm . ' ';
    $chars = mb_str_split($padded, 1, 'UTF-8');
    $grams = [];
    for ($i = 0, $n = count($chars) - 2; $i < $n; $i++) {
      $grams[$chars[$i] . $chars[$i + 1] . $chars[$i + 2]] = true;
    }

    return $grams;
  }

  /** Similaridade (0–100, uma casa decimal) entre dois enunciados. */
  public static function score(string $a, string $b): float
  {
    return self::diceOf(self::trigrams($a), self::trigrams($b));
  }

  /**
   * Candidatos com similaridade ≥ $minPct, do maior para o menor.
   * Cada candidato precisa de 'text' (e pode trazer 'grams' já calculado);
   * o resultado é o próprio candidato acrescido de 'pct'.
   *
   * @param iterable<array{text?: string, grams?: array<string, true>}> $candidates
   * @return list<array<string, mixed>>
   */
  public static function bestMatches(string $text, iterable $candidates, float $minPct, int $limit = 3): array
  {
    $grams = self::trigrams($text);
    if ($grams === []) {
      return [];
    }
    $size = count($grams);
    $found = [];
    foreach ($candidates as $candidate) {
      $other = $candidate['grams'] ?? self::trigrams((string) ($candidate['text'] ?? ''));
      $otherSize = count($other);
      if ($otherSize === 0) {
        continue;
      }
      // Dice ≤ 2·menor/(soma): descarta sem comparar quando nem o máximo possível alcança o mínimo
      if (200.0 * min($size, $otherSize) / ($size + $otherSize) < $minPct) {
        continue;
      }
      $pct = self::diceOf($grams, $other);
      if ($pct >= $minPct) {
        unset($candidate['grams']);
        $found[] = $candidate + ['pct' => $pct];
      }
    }
    usort($found, static fn(array $x, array $y): int => $y['pct'] <=> $x['pct']);

    return array_slice($found, 0, max(0, $limit));
  }

  /**
   * @param array<string, true> $a
   * @param array<string, true> $b
   */
  private static function diceOf(array $a, array $b): float
  {
    $total = count($a) + count($b);
    if ($total === 0 || $a === [] || $b === []) {
      return 0.0;
    }

    return round(200.0 * count(array_intersect_key($a, $b)) / $total, 1);
  }
}
