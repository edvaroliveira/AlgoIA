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
