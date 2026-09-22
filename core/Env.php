<?php

declare(strict_types=1);

namespace Core;

/**
 * Loads a .env file into $_ENV / getenv().
 * Must be required directly — available before the autoloader.
 */
class Env
{
  public function __construct(private string $path) {}

  public function load(): void
  {
    if (!file_exists($this->path)) {
      return;
    }

    $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
      $line = trim($line);

      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }

      if (!str_contains($line, '=')) {
        continue;
      }

      [$key, $value] = explode('=', $line, 2);
      $key   = trim($key);
      $value = trim($value);

      // Strip surrounding quotes
      if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
        $value = $m[2];
      }

      if (!array_key_exists($key, $_ENV)) {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
      }
    }
  }
}

/**
 * Global helper — available everywhere after core/Env.php is required.
 */
function env(string $key, mixed $default = null): mixed
{
  $val = $_ENV[$key] ?? getenv($key);
  return ($val !== false && $val !== null) ? $val : $default;
}

function app_base_path(): string
{
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';

  if (is_string($scriptName) && $scriptName !== '') {
    $path = str_replace('\\', '/', dirname($scriptName));
    if ($path === '/' || $path === '.' || $path === '\\') {
      return '';
    }

    return '/' . trim($path, '/');
  }

  $appUrl = (string) env('APP_URL', '');
  $path   = $appUrl !== '' ? (string) parse_url($appUrl, PHP_URL_PATH) : '';
  $path   = '/' . trim($path, '/');

  return $path === '/' ? '' : rtrim($path, '/');
}

function app_request_path(): string
{
  $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
  $uri = is_string($uri) && $uri !== '' ? $uri : '/';

  $basePath = app_base_path();
  if ($basePath !== '' && ($uri === $basePath || str_starts_with($uri, $basePath . '/'))) {
    $uri = substr($uri, strlen($basePath)) ?: '/';
  }

  $uri = '/' . trim($uri, '/');
  return $uri === '//' ? '/' : $uri;
}

function app_url(string $path = ''): string
{
  if ($path !== '' && preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
    return $path;
  }

  $basePath = app_base_path();
  if ($path === '' || $path === '/') {
    return $basePath !== '' ? $basePath . '/' : '/';
  }

  $normalized = '/' . ltrim($path, '/');
  return ($basePath !== '' ? $basePath : '') . $normalized;
}

/**
 * Valida um caminho de retorno vindo da requisição.
 *
 * Aceita só caminho interno ("/algo"). Recusa "//host" e "/\host": o parser de
 * URL do navegador converte a barra invertida em barra, então os dois viram
 * URL protocol-relative e o redirect sai do domínio.
 */
function app_safe_path(string $candidate, string $fallback): string
{
    $candidate = trim($candidate);

    return preg_match('#^/(?![/\\\\])#', $candidate) === 1 ? $candidate : $fallback;
}

/**
 * TRUSTED_PROXIES: lista separada por vírgula de IPs ou blocos CIDR que têm
 * permissão de anunciar dados de conexão do cliente original (IP, protocolo)
 * via cabeçalho. Sem essa lista configurada, cabeçalhos de proxy nunca são
 * confiados — qualquer cliente pode forjá-los.
 */
function is_trusted_proxy(string $remoteAddr): bool
{
  if ($remoteAddr === '') {
    return false;
  }

  $configured = trim((string) env('TRUSTED_PROXIES', ''));
  if ($configured === '') {
    return false;
  }

  foreach (explode(',', $configured) as $entry) {
    $entry = trim($entry);
    if ($entry !== '' && ip_matches($remoteAddr, $entry)) {
      return true;
    }
  }

  return false;
}

function ip_matches(string $ip, string $rule): bool
{
  if (!str_contains($rule, '/')) {
    return $ip === $rule;
  }

  [$subnet, $bits] = explode('/', $rule, 2);
  $ipBin     = @inet_pton($ip);
  $subnetBin = @inet_pton($subnet);
  $bits      = (int) $bits;

  if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
    return false;
  }

  $maxBits = strlen($ipBin) * 8;
  if ($bits < 0 || $bits > $maxBits) {
    return false;
  }

  $wholeBytes = intdiv($bits, 8);
  $restBits   = $bits % 8;

  if ($wholeBytes > 0 && strncmp($ipBin, $subnetBin, $wholeBytes) !== 0) {
    return false;
  }

  if ($restBits === 0) {
    return true;
  }

  $mask = ~((1 << (8 - $restBits)) - 1) & 0xFF;

  return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
}

/**
 * true quando a requisição deve ser tratada como HTTPS: TLS direto, ou
 * X-Forwarded-Proto vindo de um proxy declarado em TRUSTED_PROXIES. Sem a
 * variável configurada, o cabeçalho do proxy é ignorado — mesma regra de
 * confiança usada em AuditService para IP.
 */
function request_is_https(): bool
{
  $direct = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
  if ($direct) {
    return true;
  }

  $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
  if (!is_trusted_proxy($remoteAddr)) {
    return false;
  }

  return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * URL de asset estático com cache-busting por mtime (?v=...).
 * Garante que CSS/JS atualizados sejam recarregados pelo navegador.
 */
function asset_url(string $path): string
{
  $url  = app_url($path);
  $file = ROOT_PATH . '/public/' . ltrim($path, '/');
  if (is_file($file)) {
    $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($file);
  }
  return $url;
}
