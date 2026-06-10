<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

$requiredTables = [
  'users',
  'turmas',
  'student_turma',
  'exercises',
  'exercise_turmas',
  'questions',
  'attempts',
  'answers',
  'audit_logs',
  'login_attempts',
  'grading_jobs',
];

$requiredColumns = [
  'users' => ['id', 'email', 'role', 'status', 'must_change_password', 'registration_source', 'avatar_path'],
  'student_turma' => ['student_id', 'turma_id', 'status'],
  'attempts' => ['id', 'exercise_id', 'student_id', 'turma_id', 'status', 'submitted_at', 'total_score'],
  'answers' => ['attempt_id', 'question_id', 'student_answer', 'ai_score', 'ai_feedback', 'deduction_reasons_json'],
  'login_attempts' => ['email', 'ip_address', 'user_agent', 'succeeded', 'created_at'],
  'grading_jobs' => ['attempt_id', 'status', 'attempts', 'last_error', 'available_at', 'locked_at', 'completed_at'],
];

try {
  $db = Core\Database::getInstance();
  $missing = [];

  foreach ($requiredTables as $table) {
    $row = $db->fetchOne(
      "SELECT TABLE_NAME
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
             LIMIT 1",
      [$table]
    );

    if (!$row) {
      $missing[] = "tabela ausente: {$table}";
    }
  }

  foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
      $row = $db->fetchOne(
        "SELECT COLUMN_NAME
               FROM INFORMATION_SCHEMA.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 AND COLUMN_NAME = ?
               LIMIT 1",
        [$table, $column]
      );

      if (!$row) {
        $missing[] = "coluna ausente: {$table}.{$column}";
      }
    }
  }

  if ($missing !== []) {
    fwrite(STDERR, "Smoke schema falhou:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
  }

  echo "Smoke schema OK." . PHP_EOL;
} catch (Throwable $e) {
  fwrite(STDERR, 'Smoke schema falhou: ' . $e->getMessage() . PHP_EOL);
  exit(1);
}
