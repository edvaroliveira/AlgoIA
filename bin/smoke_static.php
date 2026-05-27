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
      'enqueueAttempt',
      'grading_enqueue_failed',
      'markCompletedForAttempt',
    ],
    'mustNotContain' => [
      'gradeSubmittedAttempt((int) $id)',
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
  'app/Services/GradingJobProcessor.php',
  'bin/process_grading_jobs.php',
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
