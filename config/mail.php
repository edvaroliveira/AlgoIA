<?php

declare(strict_types=1);

// Avisos por e-mail (worker bin/send_notification_emails.php).
// MAIL_DRIVER: "smtp" (servidor SMTP autenticado, ex.: Titan), "mail"
// (mail() do PHP / sendmail do servidor) ou "log" (uma linha JSON por e-mail
// em MAIL_LOG_PATH; usado nos testes).
return [
  'driver'    => (string) \Core\env('MAIL_DRIVER', 'mail'),
  'smtp_host'       => trim((string) \Core\env('MAIL_SMTP_HOST', '')),
  'smtp_port'       => (int) \Core\env('MAIL_SMTP_PORT', 465),
  'smtp_encryption' => strtolower(trim((string) \Core\env('MAIL_SMTP_ENCRYPTION', 'ssl'))),
  'smtp_user'       => (string) \Core\env('MAIL_SMTP_USER', ''),
  'smtp_password'   => (string) \Core\env('MAIL_SMTP_PASSWORD', ''),
  'smtp_timeout'    => max(1, (int) \Core\env('MAIL_SMTP_TIMEOUT', 20)),
  'from'      => trim((string) \Core\env('MAIL_FROM', '')),
  'from_name' => (string) \Core\env('MAIL_FROM_NAME', 'AlgoIA'),
  'log_path'  => (string) \Core\env('MAIL_LOG_PATH', ''),
  'app_url'   => rtrim((string) \Core\env('APP_URL', 'http://localhost'), '/'),
];
