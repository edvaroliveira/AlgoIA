<?php

declare(strict_types=1);

return [
  'host'     => \Core\env('DB_HOST', 'localhost'),
  'database' => \Core\env('DB_DATABASE', ''),
  'username' => \Core\env('DB_USERNAME', ''),
  'password' => \Core\env('DB_PASSWORD', ''),
];
