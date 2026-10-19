<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
  // AU-11: com o repo inteiro no public_html, só public/ pode ser servido.
  [
    'file' => '.htaccess',
    'mustContain' => ['RewriteRule ^public(/|$) - [L]', 'RewriteRule ^ - [F,L]', 'Require all denied'],
  ],
  // AU-04: php_flag fora de IfModule derruba a pasta (500) com PHP via CGI/FPM.
  [
    'file' => 'public/assets/uploads/.htaccess',
    'mustContain' => ['<IfModule mod_php8.c>', 'Require all denied'],
  ],
  // AU-09/AU-10: nenhuma credencial conhecida nos seeds (repo público).
  [
    'file' => 'database/seeds/002_seed_admin.php',
    'mustContain' => ['seed_password(', 'must_change_password'],
    'mustNotContain' => ['AdminAlgoIA123', '"  Senha : '],
  ],
  [
    'file' => 'database/seeds/001_seed_teacher.php',
    'mustContain' => ['seed_password(', 'must_change_password'],
    'mustNotContain' => ['"  Senha : '],
  ],
  [
    'file' => 'app/Models/Turma.php',
    'mustContain' => [
      "AND u.role = 'student'",
      "AND st.status = 'pending'",
      'return false',
    ],
  ],
  [
    'file' => 'app/Controllers/AttemptController.php',
    'mustContain' => [
      'submit_failed',
      'adminRequeue',
      'AttemptSubmissionService',
    ],
    'mustNotContain' => [
      'gradeSubmittedAttempt',
      'AttemptGradingService',
      'markCompletedForAttempt',
    ],
  ],
  [
    'file' => 'app/Services/AttemptSubmissionService.php',
    'mustContain' => [
      'enqueueAttempt',
      'FOR UPDATE',
      'beginTransaction',
      "st.status = 'active'",
      "e.status = 'active'",
      "blocked_q.admin_review_status = 'blocked'",
      'findActiveByExercise',
    ],
  ],
  [
    'file' => 'app/Models/Question.php',
    'mustContain' => [
      'updateAdminReviewAndProtectExercise',
      'hasBlockedByExercise',
      'UPDATE exercise_turmas',
    ],
  ],
  [
    'file' => 'app/Models/Exercise.php',
    'mustContain' => [
      'hasBlockedByExercise',
      "blocked_q.admin_review_status = 'blocked'",
      'Exercício não está liberado pela moderação para publicação.',
    ],
  ],
  [
    'file' => 'app/Models/LoginAttempt.php',
    'mustContain' => [
      'login_attempts',
      'isLocked',
      'recordFailure',
      'recordSuccess',
    ],
  ],
  [
    'file' => 'app/Models/GradingJob.php',
    'mustContain' => [
      'grading_jobs',
      'recoverStaleProcessing',
      'markCompletedForAttempt',
      'statusesForAttempts',
      'adminRequeue',
      'locked_at = NULL',
    ],
    'mustNotContain' => [
      'worker_id IS NULL OR worker_id',
      "OR ? = ''",
    ],
  ],
  [
    'file' => 'app/Services/AttemptGradingService.php',
    'mustContain' => [
      'assertLease',
      'Lease do job de correção foi perdido.',
      'canonicalScore',
    ],
  ],
  [
    'file' => 'app/Services/GradingJobProcessor.php',
    'mustContain' => [
      'renewLease',
      'gradeSubmittedAttempt',
    ],
  ],
  [
    'file' => 'app/Models/Answer.php',
    'mustContain' => [
      "admin_review_status <> 'blocked'",
    ],
  ],
  [
    'file' => 'app/Models/Question.php',
    'mustContain' => [
      'findActiveByExercise',
      "admin_review_status <> 'blocked'",
    ],
  ],
  [
    // RS-01: a confirmação de ação destrutiva depende deste listener — sem ele
    // os data-confirm das views viram decoração e a ação passa direto.
    'file' => 'public/assets/js/app.js',
    'mustContain' => [
      'data-confirm',
      'form[data-confirm]',
    ],
  ],
  [
    // RS-02: cabeçalho de proxy só vale vindo de proxy declarado.
    'file' => 'app/Services/AuditService.php',
    'mustContain' => [
      'is_trusted_proxy',
      'REMOTE_ADDR',
    ],
  ],
  [
    // RS-02 / AP-10: helper compartilhado de confiança em proxy — sem ele,
    // AuditService, index.php e Session.php divergiriam na regra de quando
    // aceitar cabeçalho de proxy.
    'file' => 'core/Env.php',
    'mustContain' => [
      'function is_trusted_proxy',
      'function request_is_https',
      'TRUSTED_PROXIES',
    ],
  ],
  [
    // AP-10: X-Forwarded-Proto só deve valer atrás de proxy declarado —
    // senão um cliente direto pode forjar HTTPS e obter cookie/HSTS indevidos.
    'file' => 'public/index.php',
    'mustContain' => [
      'request_is_https',
    ],
  ],
  [
    'file' => 'core/Session.php',
    'mustContain' => [
      'request_is_https',
    ],
  ],
  [
    // RS-04: sessão versionada pela senha; sem isso o reset não derruba
    // sessões abertas em outros dispositivos.
    'file' => 'core/Auth.php',
    'mustContain' => [
      'password_changed_at',
      'refreshAfterPasswordChange',
      'sessionPayload',
    ],
  ],
  [
    'file' => 'app/Models/User.php',
    'mustContain' => [
      'password_changed_at = NOW()',
    ],
  ],
  [
    // RS-10: cron precisa enxergar degradação da fila pelo exit code.
    // AP-12: worker falha cedo (exit 2) se config crítica estiver ausente,
    // em vez de queimar retries job por job.
    'file' => 'bin/process_grading_jobs.php',
    'mustContain' => [
      'failedCount',
      'exit($failed > 0 ? 1 : 0);',
      'OPENAI_API_KEY não configurada',
      'exit(2)',
    ],
  ],
  [
    // AP-12: timeout/retries da OpenAI e limites do worker configuráveis por
    // env, com fallback para os padrões anteriores.
    'file' => 'config/openai.php',
    'mustContain' => [
      'OPENAI_TIMEOUT_SECONDS',
      'OPENAI_MAX_RETRIES',
    ],
  ],
  [
    'file' => 'app/Models/GradingJob.php',
    'mustContain' => [
      'GRADING_JOB_MAX_ATTEMPTS',
      'GRADING_JOB_STALE_MINUTES',
    ],
  ],
  [
    // Linha de base do schema (tag schema-baseline-2026-10-19): estruturas que o
    // código exige e que antes eram conferidas nas migrations 013, 014 e 019.
    'file' => 'database/migrations/001_create_tables.sql',
    'mustContain' => [
      'password_changed_at',
      'CREATE TABLE IF NOT EXISTS login_attempts',
      'CREATE TABLE IF NOT EXISTS grading_jobs',
      'uk_grading_jobs_attempt',
    ],
  ],
  [
    // AP-08: guarda de segurança do script de integração — sem ela, rodar
    // localmente contra um DB_DATABASE de produção por engano trunca tudo.
    'file' => 'bin/run_integration_tests.php',
    'mustContain' => [
      'INTEGRATION_TESTS_CONFIRM',
      "preg_match('/test|ci/i'",
      'TRUNCATE TABLE',
    ],
  ],
  [
    // AP-08 / CI: job de integração novo não pode substituir nem renomear o
    // job "Lint + testes (sem banco)" que a branch protection da main exige.
    'file' => '.github/workflows/ci.yml',
    'mustContain' => [
      'Lint + testes (sem banco)',
      'name: Testes de integração (MySQL + HTTP)',
      'run_integration_tests.php',
    ],
  ],
];
$lintFiles = [
  'app/Controllers/AuthController.php',
  'app/Controllers/AttemptController.php',
  'app/Controllers/TurmaController.php',
  'app/Models/GradingJob.php',
  'app/Models/LoginAttempt.php',
  'app/Models/Turma.php',
  'app/Services/AttemptGradingService.php',
  'app/Services/AttemptStartService.php',
  'app/Services/AttemptSubmissionService.php',
  'app/Services/GradingJobProcessor.php',
  'bin/process_grading_jobs.php',
  'bin/smoke_schema.php',
];

$failures = [];

foreach ($checks as $check) {
  $path = $root . '/' . $check['file'];
  if (!is_file($path)) {
    $failures[] = "Arquivo ausente: {$check['file']}";
    continue;
  }

  $content = (string) file_get_contents($path);

  foreach (($check['mustContain'] ?? []) as $needle) {
    if (!str_contains($content, $needle)) {
      $failures[] = "{$check['file']} nao contem: {$needle}";
    }
  }

  foreach (($check['mustNotContain'] ?? []) as $needle) {
    if (str_contains($content, $needle)) {
      $failures[] = "{$check['file']} contem trecho proibido: {$needle}";
    }
  }
}

foreach ($lintFiles as $file) {
  $path = $root . '/' . $file;
  if (!is_file($path)) {
    $failures[] = "Arquivo ausente para lint: {$file}";
    continue;
  }

  $command = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($path);
  exec($command, $output, $exitCode);
  if ($exitCode !== 0) {
    $failures[] = "Lint falhou em {$file}: " . implode(' ', $output);
  }
}

// PHP 8.5 deprecou chamadas que já eram no-op desde o 8.0 e as constantes
// PDO::MYSQL_ATTR_* (substituídas por Pdo\Mysql::ATTR_* no 8.4). Referência
// por string (fallback via constant()) é permitida.
$deprecatedPatterns = [
  '/\bimagedestroy\s*\(/'         => 'imagedestroy() (no-op desde PHP 8.0, deprecated no 8.5)',
  '/\bcurl_close\s*\(/'           => 'curl_close() (no-op desde PHP 8.0, deprecated no 8.5)',
  '/\bfinfo_close\s*\(/'          => 'finfo_close() (no-op desde PHP 8.1, deprecated no 8.5)',
  '/(?<![\'"])\bPDO::MYSQL_ATTR_/' => 'PDO::MYSQL_ATTR_* (use Pdo\Mysql::ATTR_*, deprecated no 8.5)',
];

$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($phpFiles as $file) {
  $relative = substr($file->getPathname(), strlen($root) + 1);
  if ($file->getExtension() !== 'php' || str_starts_with($relative, '.git/') || $relative === 'bin/smoke_static.php') {
    continue;
  }

  $content = (string) file_get_contents($file->getPathname());

  // AU-05: fputcsv só via AdminBaseController::writeCsvRow (que passa $escape).
  if ($relative !== 'app/Controllers/AdminBaseController.php' && preg_match('/\bfputcsv\s*\(/', $content)) {
    $failures[] = "{$relative} chama fputcsv() direto; use AdminBaseController::writeCsvRow()";
  }

  foreach ($deprecatedPatterns as $pattern => $label) {
    if (preg_match($pattern, $content)) {
      $failures[] = "{$relative} usa {$label}";
    }
  }
}

// Migrations nunca apagam o banco: scripts de DROP DATABASE ao lado das migrations
// podiam ser aplicados por engano em produção (removidos em 2026-10-19).
foreach (glob($root . '/database/migrations/*.sql') ?: [] as $migration) {
  if (preg_match('/\bDROP\s+DATABASE\b/i', (string) file_get_contents($migration))) {
    $failures[] = 'database/migrations/' . basename($migration) . ' contém DROP DATABASE';
  }
}

if ($failures !== []) {
  fwrite(STDERR, "Smoke static falhou:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "Smoke static OK." . PHP_EOL;
