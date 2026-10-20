<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cliente SMTP mínimo, sem dependências (ADR 0001): SSL direto (465),
 * STARTTLS (587) ou sem criptografia (só máquina local, para testes);
 * autenticação AUTH LOGIN. Uma conexão por mensagem.
 */
class SmtpTransport
{
  /** @var resource|null */
  private $socket = null;
  private string $lastReply = '';

  public function __construct(
    private string $host,
    private int $port,
    private string $encryption,
    private string $user,
    private string $password,
    private int $timeout = 20
  ) {
  }

  /** Envia a mensagem pronta (cabeçalhos + corpo). Lança RuntimeException com a resposta do servidor. */
  public function send(string $from, string $to, string $message): void
  {
    try {
      $this->connect();
      $this->expect([220]);
      $helo = $this->heloName($from);
      $this->command("EHLO {$helo}", [250]);

      if ($this->encryption === 'tls') {
        $this->command('STARTTLS', [220]);
        if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
          throw new \RuntimeException('Falha ao iniciar TLS (STARTTLS).');
        }
        $this->command("EHLO {$helo}", [250]);
      }

      if ($this->user !== '') {
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->user), [334]);
        $this->command(base64_encode($this->password), [235], true);
      }

      $this->command("MAIL FROM:<{$from}>", [250]);
      $this->command("RCPT TO:<{$to}>", [250, 251]);
      $this->command('DATA', [354]);
      // Transparência (RFC 5321 4.5.2): linha que começa com ponto ganha outro ponto.
      $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
      $this->write(str_replace("\n", "\r\n", rtrim($data, "\n")) . "\r\n.\r\n");
      $this->expect([250]);
      $this->command('QUIT', [221]);
    } finally {
      if (is_resource($this->socket)) {
        fclose($this->socket);
      }
      $this->socket = null;
    }
  }

  /** Só conecta e autentica (sem enviar), para validar a configuração antes de salvar. */
  public function verifyLogin(string $from): void
  {
    try {
      $this->connect();
      $this->expect([220]);
      $helo = $this->heloName($from);
      $this->command("EHLO {$helo}", [250]);
      if ($this->encryption === 'tls') {
        $this->command('STARTTLS', [220]);
        if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
          throw new \RuntimeException('Falha ao iniciar TLS (STARTTLS).');
        }
        $this->command("EHLO {$helo}", [250]);
      }
      if ($this->user !== '') {
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->user), [334]);
        $this->command(base64_encode($this->password), [235], true);
      }
      $this->command('QUIT', [221]);
    } finally {
      if (is_resource($this->socket)) {
        fclose($this->socket);
      }
      $this->socket = null;
    }
  }

  private function connect(): void
  {
    $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $socket = @stream_socket_client(
      "{$scheme}://{$this->host}:{$this->port}",
      $errno,
      $errstr,
      $this->timeout,
      STREAM_CLIENT_CONNECT,
      $context
    );
    if ($socket === false) {
      throw new \RuntimeException("Não foi possível conectar ao SMTP {$this->host}:{$this->port} ({$errstr}).");
    }
    stream_set_timeout($socket, $this->timeout);
    $this->socket = $socket;
  }

  private function heloName(string $from): string
  {
    $domain = substr((string) strrchr($from, '@'), 1);
    return $domain !== '' ? $domain : 'localhost';
  }

  /** @param int[] $codes */
  private function command(string $line, array $codes, bool $secret = false): void
  {
    $this->write($line . "\r\n");
    $this->expect($codes, $secret ? 'AUTH (senha)' : $line);
  }

  private function write(string $data): void
  {
    if (fwrite($this->socket, $data) === false) {
      throw new \RuntimeException('Falha ao escrever na conexão SMTP.');
    }
  }

  /** @param int[] $codes */
  private function expect(array $codes, string $context = 'conexão'): void
  {
    $reply = '';
    while (($line = fgets($this->socket, 1024)) !== false) {
      $reply .= $line;
      // Resposta multilinha: "250-..." continua; "250 ..." termina.
      if (strlen($line) < 4 || $line[3] !== '-') {
        break;
      }
    }
    $this->lastReply = trim($reply);
    $code = (int) substr($this->lastReply, 0, 3);
    if (!in_array($code, $codes, true)) {
      $context = str_starts_with($context, 'AUTH') ? $context : explode(':', $context)[0];
      throw new \RuntimeException("SMTP recusou {$context}: " . ($this->lastReply !== '' ? $this->lastReply : 'sem resposta'));
    }
  }
}
