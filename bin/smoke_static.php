<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
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
    'file' => 'bin/process_grading_jobs.php',
    'mustContain' => [
      'failedCount',
      'exit($failed > 0 ? 1 : 0);',
    ],
  ],
  [
    'file' => 'database/migrations/019_users_password_changed_at.sql',
    'mustContain' => [
      'password_changed_at',
      'information_schema.COLUMNS',
    ],
  ],
  [
    'file' => 'database/migrations/013_login_attempts.sql',
    'mustContain' => [
      'CREATE TABLE IF NOT EXISTS login_attempts',
    ],
  ],
  [
    'file' => 'database/migrations/014_grading_jobs.sql',
    'mustContain' => [
      'CREATE TABLE IF NOT EXISTS grading_jobs',
      'uk_grading_jobs_attempt',
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

if ($failures !== []) {
  fwrite(STDERR, "Smoke static falhou:\n- " . implode("\n- ", $failures) . "\n");
  exit(1);
}

echo "Smoke static OK." . PHP_EOL;
