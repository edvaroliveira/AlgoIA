<?php

/**
 * Worker de e-mail dos Avisos. Rodar no cron a cada 5 minutos:
 *   php bin/send_notification_emails.php [limite]
 * Saída: 0 = ok; 1 = algum e-mail falhou; 2 = configuração inválida (nada enviado).
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

$mailer = new App\Services\Mailer();
$errors = $mailer->configErrors();
if ($errors !== []) {
  fwrite(STDERR, 'E-mail não configurado: ' . implode(' ', $errors) . PHP_EOL);
  exit(2);
}

$result = (new App\Services\NotificationMailer($mailer))->run(max(1, (int) ($argv[1] ?? 200)));
echo "E-mails enviados: {$result['sent']}; falhas: {$result['failed']}." . PHP_EOL;
exit($result['failed'] > 0 ? 1 : 0);
