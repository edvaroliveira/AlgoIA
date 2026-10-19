<?php

/**
 * Imita POST /v1/chat/completions da OpenAI para os testes de integração
 * (php -S com este arquivo como roteador). Nunca é usado em produção.
 * - Grava cada requisição em FAKE_OPENAI_LOG, para o teste conferir o prompt.
 * - Responde 400 quando o prompt contém FALHA_SIMULADA.
 */

declare(strict_types=1);

$body = (string) file_get_contents('php://input');
$log = getenv('FAKE_OPENAI_LOG');
if (is_string($log) && $log !== '') {
  file_put_contents($log, $body . "\n", FILE_APPEND);
}

header('Content-Type: application/json');

if (str_contains($body, 'FALHA_SIMULADA')) {
  http_response_code(400);
  echo '{"error":{"message":"falha simulada"}}';
  return;
}

$content = json_encode([
  'score' => 6,
  'feedback' => 'Faltou tratar o vetor vazio.',
  'correct' => false,
  'deduction_reasons' => ['missing_concept'],
]);
echo json_encode(['choices' => [['message' => ['content' => $content]]]]);
