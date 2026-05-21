<?php

declare(strict_types=1);

return [
  'name'  => \Core\env('APP_NAME', 'AlgoIA'),
  'env'   => \Core\env('APP_ENV', 'production'),
  'url'   => \Core\env('APP_URL', 'http://localhost'),
  'debug' => \Core\env('APP_DEBUG', 'false') === 'true',
];
