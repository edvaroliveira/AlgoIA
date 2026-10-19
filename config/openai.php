<?php

declare(strict_types=1);

// Endereço configurável só para apontar a uma IA falsa local nos testes:
// aceita https ou http em 127.0.0.1/localhost; qualquer outro valor cai no oficial.
$apiUrl = (string) \Core\env('OPENAI_API_URL', '');
if (preg_match('#^(https://|http://(127\.0\.0\.1|localhost)(:\d+)?/)#', $apiUrl) !== 1) {
  $apiUrl = 'https://api.openai.com/v1/chat/completions';
}

return [
  'api_url'     => $apiUrl,
  'api_key'     => \Core\env('OPENAI_API_KEY', ''),
  'model'       => \Core\env('OPENAI_MODEL', 'gpt-4o'),
  'timeout'     => max(1, (int) \Core\env('OPENAI_TIMEOUT_SECONDS', 30)),
  'max_retries' => max(1, (int) \Core\env('OPENAI_MAX_RETRIES', 3)),
];
