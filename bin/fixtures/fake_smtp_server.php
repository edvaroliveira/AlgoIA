<?php

/**
 * Servidor SMTP falso para os testes de integração (sem TLS). Nunca é usado
 * em produção. Atende conexões em sequência e grava cada mensagem recebida
 * como uma linha JSON em FAKE_SMTP_LOG: {auth_user, from, to, data}.
 * Senha "senha-errada" recebe 535 (falha de autenticação).
 *
 * Uso: php bin/fixtures/fake_smtp_server.php <porta>
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 0);
$log = (string) getenv('FAKE_SMTP_LOG');
$server = stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if ($server === false) {
  fwrite(STDERR, "fake smtp: {$errstr}\n");
  exit(1);
}

$send = static function ($conn, string $line): void {
  fwrite($conn, $line . "\r\n");
};

while ($conn = @stream_socket_accept($server, -1)) {
  $send($conn, '220 fake.smtp ESMTP');
  $message = ['auth_user' => null, 'from' => null, 'to' => [], 'data' => ''];
  $state = 'cmd';
  while (($line = fgets($conn)) !== false) {
    if ($state === 'data') {
      if (rtrim($line, "\r\n") === '.') {
        if ($log !== '') {
          file_put_contents($log, json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
        }
        $send($conn, '250 OK queued');
        $state = 'cmd';
        continue;
      }
      $message['data'] .= str_starts_with($line, '..') ? substr($line, 1) : $line;
      continue;
    }
    if ($state === 'auth_user') {
      $message['auth_user'] = base64_decode(trim($line));
      $send($conn, '334 UGFzc3dvcmQ6');
      $state = 'auth_pass';
      continue;
    }
    if ($state === 'auth_pass') {
      if (base64_decode(trim($line)) === 'senha-errada') {
        $send($conn, '535 5.7.8 Authentication failed');
      } else {
        $send($conn, '235 2.7.0 Authentication successful');
      }
      $state = 'cmd';
      continue;
    }

    $cmd = strtoupper(trim($line));
    if (str_starts_with($cmd, 'EHLO') || str_starts_with($cmd, 'HELO')) {
      $send($conn, '250-fake.smtp');
      $send($conn, '250 AUTH LOGIN');
    } elseif ($cmd === 'AUTH LOGIN') {
      $send($conn, '334 VXNlcm5hbWU6');
      $state = 'auth_user';
    } elseif (str_starts_with($cmd, 'MAIL FROM:')) {
      $message['from'] = trim(substr(trim($line), 10), '<> ');
      $send($conn, '250 OK');
    } elseif (str_starts_with($cmd, 'RCPT TO:')) {
      $message['to'][] = trim(substr(trim($line), 8), '<> ');
      $send($conn, '250 OK');
    } elseif ($cmd === 'DATA') {
      $send($conn, '354 End data with <CR><LF>.<CR><LF>');
      $state = 'data';
    } elseif ($cmd === 'RSET' || $cmd === 'NOOP') {
      $send($conn, '250 OK');
    } elseif ($cmd === 'QUIT') {
      $send($conn, '221 Bye');
      break;
    } else {
      $send($conn, '502 Command not implemented');
    }
  }
  fclose($conn);
}
