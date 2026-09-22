<?php

declare(strict_types=1);

return [
  'api_key'     => \Core\env('OPENAI_API_KEY', ''),
  'model'       => \Core\env('OPENAI_MODEL', 'gpt-4o'),
  'timeout'     => max(1, (int) \Core\env('OPENAI_TIMEOUT_SECONDS', 30)),
  'max_retries' => max(1, (int) \Core\env('OPENAI_MAX_RETRIES', 3)),
];
