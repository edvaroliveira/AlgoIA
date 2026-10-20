<?php

declare(strict_types=1);

namespace App\Services;

use Core\Env;

/**
 * Leitura e gravação do .env pela tela de Configurações do Admin.
 * Preserva comentários e a ordem das linhas, troca só as chaves alteradas,
 * guarda backup do arquivo anterior e grava de forma atômica (tmp + rename).
 */
class EnvFile
{
  public const KEEP_BACKUPS = 5;

  public function __construct(private string $path)
  {
  }

  public function path(): string
  {
    return $this->path;
  }

  /** @return array<string, string> */
  public function read(): array
  {
    return is_file($this->path) ? Env::parse((string) file_get_contents($this->path)) : [];
  }

  public function isWritable(): bool
  {
    return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
  }

  /**
   * Grava as chaves informadas (as demais linhas ficam intactas).
   *
   * @param array<string, string> $changes
   * @throws \InvalidArgumentException valor com quebra de linha ou aspas dos dois tipos
   * @throws \RuntimeException falha de escrita
   */
  public function update(array $changes): void
  {
    $formatted = [];
    foreach ($changes as $key => $value) {
      if (preg_match('/^[A-Z][A-Z0-9_]*$/', (string) $key) !== 1) {
        throw new \InvalidArgumentException("Nome de variável inválido: {$key}");
      }
      $formatted[$key] = self::format((string) $value);
    }

    $original = is_file($this->path) ? (string) file_get_contents($this->path) : '';
    $lines = $original === '' ? [] : (preg_split('/\r\n|\r|\n/', rtrim($original, "\r\n")) ?: []);
    $pending = $formatted;

    foreach ($lines as $i => $line) {
      if (preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m) === 1 && array_key_exists($m[1], $pending)) {
        $lines[$i] = $m[1] . '=' . $pending[$m[1]];
        unset($pending[$m[1]]);
      }
    }
    foreach ($pending as $key => $value) {
      $lines[] = $key . '=' . $value;
    }

    $this->backup($original);
    $tmp = $this->path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX) === false) {
      throw new \RuntimeException('Não foi possível gravar o arquivo de configuração.');
    }
    @chmod($tmp, is_file($this->path) ? (fileperms($this->path) & 0777) : 0600);
    if (!rename($tmp, $this->path)) {
      @unlink($tmp);
      throw new \RuntimeException('Não foi possível substituir o arquivo de configuração.');
    }
  }

  /** Valor no formato que Core\Env::parse lê de volta igual. */
  public static function format(string $value): string
  {
    if (preg_match('/[\r\n\0]/', $value) === 1) {
      throw new \InvalidArgumentException('O valor não pode ter quebra de linha.');
    }
    $needsQuotes = $value !== trim($value)
      || preg_match('/\s#/', $value) === 1
      || preg_match('/^["\']/', $value) === 1
      || preg_match('/\s/', $value) === 1;
    if (!$needsQuotes) {
      return $value;
    }
    if (!str_contains($value, '"')) {
      return '"' . $value . '"';
    }
    if (!str_contains($value, "'")) {
      return "'" . $value . "'";
    }
    throw new \InvalidArgumentException('O valor não pode ter aspas simples e duplas ao mesmo tempo.');
  }

  private function backup(string $original): void
  {
    if ($original === '') {
      return;
    }
    $stamp = date('Ymd-His') . '-' . substr(sprintf('%06d', (int) (microtime(true) * 1_000_000) % 1_000_000), 0, 6);
    $backup = $this->path . '.backup-' . $stamp;
    if (file_put_contents($backup, $original, LOCK_EX) === false) {
      throw new \RuntimeException('Não foi possível criar o backup do arquivo de configuração.');
    }
    @chmod($backup, 0600);

    $backups = glob($this->path . '.backup-*') ?: [];
    sort($backups);
    foreach (array_slice($backups, 0, max(0, count($backups) - self::KEEP_BACKUPS)) as $old) {
      @unlink($old);
    }
  }
}
