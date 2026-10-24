<?php

/**
 * Imita POST /v1/chat/completions da OpenAI para os testes de integração
 * (php -S com este arquivo como roteador). Nunca é usado em produção.
 * - Grava cada requisição em FAKE_OPENAI_LOG, para o teste conferir o prompt.
 * - Responde 400 quando o prompt contém FALHA_SIMULADA.
 * - Juiz de similaridade do banco (prompt pede "mesma_questao"): sempre "mesma Questão".
 * - Resposta em Python (prompt pede "resposta_python"): código fixo; INVALIDO_PYTHON → sem código.
 */

declare(strict_types=1);

$body = (string) file_get_contents('php://input');
$log = getenv('FAKE_OPENAI_LOG');
if (is_string($log) && $log !== '') {
  file_put_contents($log, $body . "\n", FILE_APPEND);
}

header('Content-Type: application/json');

// GET /v1/models/{modelo}: teste de chave e modelo da tela de Configurações.
$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if (preg_match('#/v1/models/([^/]+)$#', $uri, $m) === 1) {
  $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
  if ($auth === 'Bearer chave-invalida') {
    http_response_code(401);
    echo '{"error":{"message":"Incorrect API key provided"}}';
  } elseif ($m[1] === 'modelo-inexistente') {
    http_response_code(404);
    echo '{"error":{"message":"The model does not exist"}}';
  } else {
    echo json_encode(['id' => $m[1], 'object' => 'model']);
  }
  return;
}

if (str_contains($body, 'FALHA_SIMULADA')) {
  http_response_code(400);
  echo '{"error":{"message":"falha simulada"}}';
  return;
}

// Resposta em Python (prompt pede "resposta_python"): código fixo; INVALIDO_PYTHON devolve JSON sem código.
if (str_contains($body, 'resposta_python')) {
  $code = str_contains($body, 'INVALIDO_PYTHON') ? '' : "# Maior de idade (resposta simulada)\nidade = int(input('Idade: '))\nprint(idade >= 18)";
  echo json_encode([
    'choices' => [['message' => ['content' => json_encode(['resposta_python' => $code])]]],
    'usage' => ['prompt_tokens' => 400, 'completion_tokens' => 120, 'total_tokens' => 520],
  ]);
  return;
}

if (str_contains($body, 'mesma_questao')) {
  echo json_encode([
    'choices' => [['message' => ['content' => json_encode([
      'mesma_questao' => true,
      'motivo' => 'Os dois pedem para verificar a maioridade aos 18 anos (resposta simulada).',
    ])]]],
    'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 40, 'total_tokens' => 340],
  ]);
  return;
}

$content = json_encode([
  'score' => 6,
  'feedback' => 'Faltou tratar o vetor vazio.',
  'correct' => false,
  'deduction_reasons' => ['missing_concept'],
]);
echo json_encode([
  'choices' => [['message' => ['content' => $content]]],
  'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 200, 'total_tokens' => 1200],
]);
