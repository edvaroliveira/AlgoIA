<?php

declare(strict_types=1);

// Avisos por e-mail (worker bin/send_notification_emails.php).
// MAIL_DRIVER: "mail" (mail() do PHP / sendmail do servidor) ou "log"
// (grava cada e-mail como uma linha JSON em MAIL_LOG_PATH; usado nos testes).
return [
  'driver'    => (string) \Core\env('MAIL_DRIVER', 'mail'),
  'from'      => trim((string) \Core\env('MAIL_FROM', '')),
  'from_name' => (string) \Core\env('MAIL_FROM_NAME', 'AlgoIA'),
  'log_path'  => (string) \Core\env('MAIL_LOG_PATH', ''),
  'app_url'   => rtrim((string) \Core\env('APP_URL', 'http://localhost'), '/'),
];
