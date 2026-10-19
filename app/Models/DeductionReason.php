<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Motivos de desconto da Correção por IA: rótulo amigável e dica de estudo.
 * Fonte única para as telas do Aluno, do Docente e do Admin. Os códigos são os
 * mesmos aceitos pelo OpenAIService::normalizeDeductionReasons().
 */
final class DeductionReason
{
  private const REASONS = [
    'missing_concept' => [
      'Faltou um conceito essencial',
      'Releia o enunciado e liste os conceitos que ele pede; depois confira se cada um aparece na sua solução.',
    ],
    'logic_error' => [
      'Há um erro na lógica',
      'Faça um teste de mesa: simule o algoritmo à mão com valores pequenos e confira o resultado de cada passo.',
    ],
    'contradiction' => [
      'Partes da resposta se contradizem',
      'Releia a resposta inteira e verifique se cada passo é coerente com os anteriores.',
    ],
    'inefficiency' => [
      'A solução funciona, mas faz trabalho a mais',
      'Procure passos repetidos ou laços desnecessários e veja se dá para chegar ao resultado com menos operações.',
    ],
    'approach_difference' => [
      'A técnica pedida no enunciado não foi usada',
      'O enunciado pede uma estrutura ou técnica específica; reescreva a solução usando exatamente o que foi pedido.',
    ],
    'incomplete_explanation' => [
      'A explicação ficou incompleta',
      'Explique o porquê de cada passo, e não só o resultado final.',
    ],
  ];

  /** @return string[] códigos aceitos na resposta da IA */
  public static function codes(): array
  {
    return array_keys(self::REASONS);
  }

  public static function label(string $code): string
  {
    return self::REASONS[$code][0] ?? '';
  }

  public static function tip(string $code): string
  {
    return self::REASONS[$code][1] ?? '';
  }

  /** Códigos válidos, sem repetição, a partir do JSON gravado na Resposta. */
  public static function fromJson(?string $json): array
  {
    $decoded = $json !== null && $json !== '' ? json_decode($json, true) : null;
    if (!is_array($decoded)) {
      return [];
    }

    $codes = array_filter($decoded, static fn($code): bool => is_string($code) && isset(self::REASONS[$code]));
    return array_values(array_unique($codes));
  }
}
