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
  public const DRIVER_SMTP = 'smtp';

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
    if (!in_array($this->config['driver'], [self::DRIVER_MAIL, self::DRIVER_LOG, self::DRIVER_SMTP], true)) {
      $errors[] = 'MAIL_DRIVER deve ser "smtp", "mail" ou "log".';
    }
    if ($this->config['driver'] === self::DRIVER_SMTP) {
      $host = (string) ($this->config['smtp_host'] ?? '');
      $encryption = (string) ($this->config['smtp_encryption'] ?? '');
      if ($host === '') {
        $errors[] = 'MAIL_SMTP_HOST obrigatório com MAIL_DRIVER=smtp.';
      }
      if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) {
        $errors[] = 'MAIL_SMTP_ENCRYPTION deve ser "ssl", "tls" ou "none".';
      }
      // Sem criptografia a senha trafegaria aberta: só na própria máquina (testes).
      if ($encryption === 'none' && !in_array($host, ['127.0.0.1', 'localhost'], true)) {
        $errors[] = 'MAIL_SMTP_ENCRYPTION=none só é aceito para 127.0.0.1/localhost.';
      }
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

    if ($this->config['driver'] === self::DRIVER_SMTP) {
      try {
        (new SmtpTransport(
          (string) $this->config['smtp_host'],
          (int) $this->config['smtp_port'],
          (string) $this->config['smtp_encryption'],
          (string) $this->config['smtp_user'],
          (string) $this->config['smtp_password'],
          (int) ($this->config['smtp_timeout'] ?? 20)
        ))->send($this->config['from'], $to, $this->buildMessage($to, $subject, $body));
        return true;
      } catch (\RuntimeException $e) {
        error_log('smtp send failed: ' . $e->getMessage());
        return false;
      }
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

  /** Mensagem completa (RFC 5322) para envio por SMTP: texto puro UTF-8 em base64. */
  public function buildMessage(string $to, string $subject, string $body): string
  {
    $from = $this->config['from'];
    $fromName = preg_replace('/[\r\n"]+/', ' ', $this->config['from_name']) ?? 'AlgoIA';
    $domain = substr((string) strrchr($from, '@'), 1) ?: 'localhost';
    $headers = [
      'Date: ' . date(DATE_RFC2822),
      'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $from . '>',
      'To: <' . $to . '>',
      'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
      'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: base64',
      'Auto-Submitted: auto-generated',
    ];
    return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
  }
}
