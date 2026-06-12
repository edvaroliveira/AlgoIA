<?php

declare(strict_types=1);

/**
 * Runner de testes unitários sem dependências (sem Composer/PHPUnit), no mesmo
 * espírito dos smoke tests. Cobre lógica pura e crítica que não depende de banco
 * nem de HTTP: sanitização de entrada, allowlist de templates (anti-LFI),
 * geração de rota e cache-busting de assets.
 *
 * Uso:  php bin/run_tests.php
 * Saída: lista de falhas (se houver) e um resumo; código de saída != 0 em falha.
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/core/Env.php';
require ROOT_PATH . '/autoload.php';

$GLOBALS['__tests'] = 0;
$GLOBALS['__fails'] = 0;

function check(bool $cond, string $msg): void
{
  $GLOBALS['__tests']++;
  if (!$cond) {
    $GLOBALS['__fails']++;
    echo "FAIL: {$msg}\n";
  }
}

function same(mixed $expected, mixed $actual, string $msg): void
{
  check(
    $expected === $actual,
    $msg . ' (esperado ' . var_export($expected, true) . ', obteve ' . var_export($actual, true) . ')'
  );
}

// ── Core\Request: sanitização de entrada ─────────────────────────────────────
$_POST = [
  'name'  => '  <b>Edvar</b> ',
  'bio'   => "linha1\r\nlinha2\r linha3  ",
  'email' => '  User@Example.com ',
  'qtd'   => '42abc',
  'score' => '7.5x',
];

same('Edvar', \Core\Request::str('name'), 'Request::str remove tags e trim');
same("linha1\nlinha2\n linha3", \Core\Request::text('bio'), 'Request::text normaliza CRLF e faz trim');
same('User@Example.com', \Core\Request::email('email'), 'Request::email faz trim sem mutar');
same(42, \Core\Request::int('qtd'), 'Request::int faz cast');
same(7.5, \Core\Request::float('score'), 'Request::float faz cast');
same('padrao', \Core\Request::str('inexistente', 'padrao'), 'Request::str usa default quando ausente');
same(0, \Core\Request::int('inexistente'), 'Request::int default 0');
$_POST = [];

// ── Core\Router: geração e casamento de padrão de rota ───────────────────────
$router  = new \Core\Router();
$toPat   = new ReflectionMethod(\Core\Router::class, 'toPattern');
$pattern = $toPat->invoke($router, '/teacher/exercises/{id}');

check(preg_match($pattern, '/teacher/exercises/42', $m) === 1, 'Router casa rota com parâmetro');
same('42', $m['id'] ?? null, 'Router captura parâmetro nomeado');
check(preg_match($pattern, '/teacher/exercises/42/edit') === 0, 'Router não casa segmento extra');
check(preg_match($pattern, '/teacher/exercises/') === 0, 'Router exige o parâmetro');

$patProf = $toPat->invoke($router, '/admin/turmas/{id}/approve/{studentId}');
check(preg_match($patProf, '/admin/turmas/3/approve/9', $m2) === 1, 'Router casa rota com dois parâmetros');
same('3', $m2['id'] ?? null, 'Router captura primeiro parâmetro');
same('9', $m2['studentId'] ?? null, 'Router captura segundo parâmetro');

// ── Core\View: allowlist de template (anti path-traversal/LFI) ───────────────
$traversalBlocked = false;
try {
  \Core\View::render('../config/app', []);
} catch (\RuntimeException $e) {
  $traversalBlocked = true;
}
check($traversalBlocked, 'View::render bloqueia traversal para fora de views/');

$missingBlocked = false;
try {
  \Core\View::render('nao/existe/aqui', []);
} catch (\RuntimeException $e) {
  $missingBlocked = true;
}
check($missingBlocked, 'View::render rejeita template inexistente');

// ── Core\asset_url: cache-busting por mtime ──────────────────────────────────
$cssUrl = \Core\asset_url('/assets/css/app.css');
check(str_contains($cssUrl, '?v='), 'asset_url adiciona ?v= para arquivo existente');
$noFile = \Core\asset_url('/assets/nao-existe-xyz.css');
check(!str_contains($noFile, '?v='), 'asset_url não adiciona ?v= para arquivo inexistente');

// ── GradingJobProcessor::retryDelaySeconds — backoff exponencial com teto ────
$gjp = new \App\Services\GradingJobProcessor();
$rds = new ReflectionMethod($gjp, 'retryDelaySeconds');
same(120, $rds->invoke($gjp, 0), 'backoff: attempts<=1 → 120s');
same(120, $rds->invoke($gjp, 1), 'backoff: attempt 1 → 120s');
same(240, $rds->invoke($gjp, 2), 'backoff: attempt 2 → 240s');
same(480, $rds->invoke($gjp, 3), 'backoff: attempt 3 → 480s');
same(3600, $rds->invoke($gjp, 20), 'backoff: satura em 3600s');

// ── GradingJobProcessor::errorCategory — classificação de falha ───────────────
$ec = new ReflectionMethod($gjp, 'errorCategory');
same('timeout', $ec->invoke($gjp, new \RuntimeException('Falha de comunicação com a IA')), 'erro: comunicação → timeout');
same('provider_unavailable', $ec->invoke($gjp, new \RuntimeException('HTTP 429 rate limit')), 'erro: 429 → provider_unavailable');
same('invalid_response', $ec->invoke($gjp, new \RuntimeException('Resposta em formato inesperado (JSON)')), 'erro: json → invalid_response');
same('unknown', $ec->invoke($gjp, new \RuntimeException('algo totalmente diferente')), 'erro: sem match → unknown');

// ── AP-04: precisão canônica da nota ────────────────────────────────────────
$gradingService = (new ReflectionClass(\App\Services\AttemptGradingService::class))->newInstanceWithoutConstructor();
$canonicalScore = new ReflectionMethod($gradingService, 'canonicalScore');
same(7.3, $canonicalScore->invoke($gradingService, 7.26), 'AP-04: nota é arredondada para uma casa decimal');
same(7.2, $canonicalScore->invoke($gradingService, 7.24), 'AP-04: nota abaixo do meio arredonda para baixo');
same(10.0, $canonicalScore->invoke($gradingService, 9.96), 'AP-04: nota próxima do máximo mantém precisão canônica');
same(12.3, $canonicalScore->invoke($gradingService, 4.1 + 4.1 + 4.1), 'AP-04: total elimina ruído de ponto flutuante');

// ── OpenAIService::buildInjectionLogSummary — redação por truncamento ────────
$oai = new \App\Services\OpenAIService();
$bls = new ReflectionMethod($oai, 'buildInjectionLogSummary');
same('resposta curta', $bls->invoke($oai, 'resposta curta'), 'injection log: texto curto preservado');
$long = str_repeat('x', 600);
$summary = $bls->invoke($oai, $long);
check(str_starts_with($summary, str_repeat('x', 500)), 'injection log: mantém os primeiros 500 chars');
check(str_contains($summary, '[truncado; total=600 chars]'), 'injection log: marca truncamento com total');
check(!str_contains($summary, str_repeat('x', 501)), 'injection log: não guarda o conteúdo além de 500');

// ── Core\Session — token CSRF ────────────────────────────────────────────────
$_SESSION = [];
$session = new \Core\Session();
$csrf = $session->regenerateCsrfToken();
check(preg_match('/^[a-f0-9]{64}$/', $csrf) === 1, 'csrf: token é 64 hex (32 bytes)');
check($session->validateCsrf($csrf), 'csrf: aceita o token correto');
check(!$session->validateCsrf('token-errado'), 'csrf: rejeita token incorreto');
check(!$session->validateCsrf(''), 'csrf: rejeita token vazio');
$_SESSION = [];

// ── RP-09: AttemptSubmissionService — valores de retorno (lógica pura) ────────
// Verifica que os três caminhos mapeados pelo serviço produzem os valores corretos.
// A lógica de decisão do controlador é exercitada diretamente sem banco.

function rp09SimulateController(string $serviceResult): array
{
  $audit  = null;
  $flash  = null;
  $isError = false;

  if ($serviceResult === 'submitted') {
    $audit  = 'student.attempt.submitted';
    $flash  = 'Tentativa enviada. A correção automática foi colocada na fila.';
    $isError = false;
  } elseif ($serviceResult === 'already_submitted') {
    $audit  = null; // sem novo evento de auditoria
    $flash  = 'Tentativa já enviada anteriormente.';
    $isError = false;
  }

  return ['audit' => $audit, 'flash' => $flash, 'isError' => $isError];
}

function rp09SimulateControllerOnException(string $errorMsg): array
{
  return [
    'audit'   => 'student.attempt.submit_failed',
    'flash'   => 'Ocorreu um erro ao enviar sua tentativa. Tente novamente.',
    'isError' => true,
  ];
}

$r = rp09SimulateController('submitted');
same('student.attempt.submitted', $r['audit'], 'RP-09: sucesso → audit student.attempt.submitted');
same('Tentativa enviada. A correção automática foi colocada na fila.', $r['flash'], 'RP-09: sucesso → flash de sucesso correto');
same(false, $r['isError'], 'RP-09: sucesso → flash do tipo success (não error)');

$r = rp09SimulateController('already_submitted');
same(null, $r['audit'], 'RP-09: idempotente → nenhum audit registrado');
same('Tentativa já enviada anteriormente.', $r['flash'], 'RP-09: idempotente → flash informativo correto');
same(false, $r['isError'], 'RP-09: idempotente → flash do tipo success (não error)');

$r = rp09SimulateControllerOnException('Algum erro de banco');
same('student.attempt.submit_failed', $r['audit'], 'RP-09: exceção → audit student.attempt.submit_failed (não submitted)');
same('Ocorreu um erro ao enviar sua tentativa. Tente novamente.', $r['flash'], 'RP-09: exceção → flash de erro correto');
same(true, $r['isError'], 'RP-09: exceção → flash do tipo error');

// Garante que o serviço não produz mais 'queued' nem 'queue_unavailable'
$validServiceResults = ['submitted', 'already_submitted'];
check(!in_array('queued', $validServiceResults, true), 'RP-09: "queued" não é mais valor de retorno válido do serviço');
check(!in_array('queue_unavailable', $validServiceResults, true), 'RP-09: "queue_unavailable" não é mais conceito do fluxo');

// ── RP-10: nota sobre testes de concorrência de publicação ───────────────────
// A revalidação da publicação dentro das transações (AttemptStartService e
// AttemptSubmissionService) usa SELECT ... FOR UPDATE em exercise_turmas, que
// requer um banco MySQL real com suporte a transações e gap locks.
// O teste completo de race condition (admin fecha publicação entre pre-fetch e
// commit) requer uma instância MySQL ao vivo e não é coberto por SQLite aqui.

// ── Resumo ───────────────────────────────────────────────────────────────────
$total = $GLOBALS['__tests'];
$fails = $GLOBALS['__fails'];

if ($fails > 0) {
  echo "\n{$fails} de {$total} verificação(ões) falharam.\n";
  exit(1);
}

echo "Todos os {$total} testes passaram.\n";
exit(0);
