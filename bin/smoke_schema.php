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
  'bank_exercises',
  'bank_questions',
  'bank_themes',
  'bank_exercise_themes',
  'score_reviews',
  'notifications',
  'practice_answers',
  'turma_posts',
  'student_achievements',
];

$requiredColumns = [
  'users' => ['id', 'email', 'role', 'status', 'must_change_password', 'registration_source', 'avatar_path', 'email_notifications'],
  'student_turma' => ['student_id', 'turma_id', 'status'],
  'attempts' => ['id', 'exercise_id', 'student_id', 'turma_id', 'status', 'submitted_at', 'total_score'],
  'answers' => ['attempt_id', 'question_id', 'student_answer', 'ai_score', 'ai_feedback', 'deduction_reasons_json'],
  'login_attempts' => ['email', 'ip_address', 'user_agent', 'succeeded', 'created_at'],
  'grading_jobs' => ['attempt_id', 'status', 'attempts', 'last_error', 'available_at', 'locked_at', 'worker_id', 'completed_at'],
  'bank_exercises' => ['id', 'title', 'description', 'source_exercise_id', 'source_teacher_id', 'status', 'review_note', 'reviewed_by', 'reviewed_at'],
  'bank_questions' => ['id', 'bank_exercise_id', 'text', 'expected_answer_hint', 'max_score', 'order_index'],
  'bank_themes' => ['id', 'name'],
  'bank_exercise_themes' => ['bank_exercise_id', 'theme_id'],
  'notifications' => ['user_id', 'type', 'title', 'link', 'dedupe_key', 'read_at', 'emailed_at', 'email_attempts'],
  'questions' => ['bank_question_id'],
  'student_achievements' => ['student_id', 'code', 'earned_at'],
  'turma_posts' => ['turma_id', 'teacher_id', 'title', 'body', 'pinned', 'expires_at'],
  'practice_answers' => ['student_id', 'bank_question_id', 'score', 'max_score', 'feedback', 'deduction_reasons_json'],
  'score_reviews' => ['answer_id', 'status', 'request_message', 'requested_by', 'previous_score', 'new_score', 'justification', 'reviewed_by'],
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

  // Janela, limite e turma pertencem à Publicação (ADR 0003), não ao Exercício.
  $forbiddenColumns = [
    'exercises' => ['turma_id', 'opens_at', 'closes_at', 'max_attempts'],
  ];

  foreach ($forbiddenColumns as $table => $columns) {
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

      if ($row) {
        $missing[] = "coluna legada presente: {$table}.{$column}";
      }
    }
  }

  // Os índices de desempenho existiam só nos antigos scripts de reset de teste;
  // até 2026-10-19 uma instalação feita pelo 001 nascia sem eles.
  $requiredIndexes = [
    'attempts'        => ['idx_attempts_student_exercise_turma', 'idx_attempts_status', 'idx_attempts_submitted_at'],
    'grading_jobs'    => ['idx_grading_jobs_status_available'],
    'users'           => ['idx_users_role_status', 'idx_users_registration_source'],
    'student_turma'   => ['idx_student_turma_status'],
    'exercises'       => ['idx_exercises_status_review'],
    'exercise_turmas' => ['idx_exercise_turmas_window'],
    'questions'       => ['idx_questions_exercise_order', 'idx_questions_review', 'idx_questions_bank_question'],
    'answers'         => ['idx_answers_evaluated_at'],
    'injection_logs'  => ['idx_injection_logs_created_at'],
    'audit_logs'      => ['idx_audit_created_at'],
  ];

  foreach ($requiredIndexes as $table => $indexes) {
    foreach ($indexes as $indexName) {
      $row = $db->fetchOne(
        "SELECT INDEX_NAME
               FROM INFORMATION_SCHEMA.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 AND INDEX_NAME = ?
               LIMIT 1",
        [$table, $indexName]
      );

      if (!$row) {
        $missing[] = "índice ausente: {$table}.{$indexName}";
      }
    }
  }

  $requiredForeignKeys = [
    ['exercises', 'admin_reviewed_by', 'users'],
    ['questions', 'admin_reviewed_by', 'users'],
    ['questions', 'bank_question_id', 'bank_questions'],
  ];

  foreach ($requiredForeignKeys as [$table, $column, $referencedTable]) {
    $row = $db->fetchOne(
      "SELECT CONSTRAINT_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME = ?
             LIMIT 1",
      [$table, $column, $referencedTable]
    );

    if (!$row) {
      $missing[] = "FK ausente: {$table}.{$column} -> {$referencedTable}";
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
