<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Envio de e-mail sem dependências (ADR 0001): mail() do PHP ou, nos testes,
 * uma linha JSON por e-mail num arquivo. Texto puro em UTF-8.
 */
class Mailer
{
  public const DRIVER_MAIL = 'mail';
  public const DRIVER_LOG  = 'log';

  private array $config;

  public function __construct(?array $config = null)
  {
    $this->config = $config ?? require ROOT_PATH . '/config/mail.php';
  }

  /** Erros de configuração que impedem qualquer envio (lista vazia = pronto). */
  public function configErrors(): array
  {
    $errors = [];
    if (filter_var($this->config['from'], FILTER_VALIDATE_EMAIL) === false) {
      $errors[] = 'MAIL_FROM ausente ou inválido.';
    }
    if (!in_array($this->config['driver'], [self::DRIVER_MAIL, self::DRIVER_LOG], true)) {
      $errors[] = 'MAIL_DRIVER deve ser "mail" ou "log".';
    }
    if ($this->config['driver'] === self::DRIVER_LOG && $this->config['log_path'] === '') {
      $errors[] = 'MAIL_LOG_PATH obrigatório com MAIL_DRIVER=log.';
    }
    return $errors;
  }

  public function appUrl(): string
  {
    return $this->config['app_url'];
  }

  public function send(string $to, string $subject, string $body): bool
  {
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
      return false;
    }
    // Sem quebra de linha no assunto: evita injeção de cabeçalhos.
    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');

    if ($this->config['driver'] === self::DRIVER_LOG) {
      $line = json_encode(['to' => $to, 'subject' => $subject, 'body' => $body], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return file_put_contents($this->config['log_path'], $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    $fromName = preg_replace('/[\r\n"]+/', ' ', $this->config['from_name']) ?? 'AlgoIA';
    $headers = [
      'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $this->config['from'] . '>',
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: 8bit',
      'Auto-Submitted: auto-generated',
    ];

    return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers), '-f' . $this->config['from']);
  }
}
