<?php

declare(strict_types=1);

return [
  'api_key' => \Core\env('OPENAI_API_KEY', ''),
  'model'   => \Core\env('OPENAI_MODEL', 'gpt-4o'),
  'timeout' => 30,
];
