-- Migration 022 — índices de desempenho que faltavam no schema consolidado.
--
-- Estes 12 índices existiam só nos antigos scripts de reset de teste
-- (000_reset_test_*_hostgator.sql, removidos em 2026-10-19). Bancos criados por
-- eles já os têm; bancos criados pelo 001_create_tables.sql nasciam sem eles.
-- Cobrem filtros e ordenações do app: listas por status, Auditoria ordenada por
-- data, limpeza de injection_logs por retenção, janela das Publicações e
-- Questões em ordem por Exercício.
--
-- Idempotente: cada índice só é criado se ainda não existir.

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_role_status'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE users ADD INDEX idx_users_role_status (role, status)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_registration_source'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE users ADD INDEX idx_users_registration_source (registration_source)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_turma' AND INDEX_NAME = 'idx_student_turma_status'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE student_turma ADD INDEX idx_student_turma_status (status)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND INDEX_NAME = 'idx_exercises_status_review'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE exercises ADD INDEX idx_exercises_status_review (status, admin_review_status)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercise_turmas' AND INDEX_NAME = 'idx_exercise_turmas_window'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE exercise_turmas ADD INDEX idx_exercise_turmas_window (opens_at, closes_at)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND INDEX_NAME = 'idx_questions_exercise_order'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE questions ADD INDEX idx_questions_exercise_order (exercise_id, order_index)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND INDEX_NAME = 'idx_questions_review'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE questions ADD INDEX idx_questions_review (admin_review_status)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attempts' AND INDEX_NAME = 'idx_attempts_status'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE attempts ADD INDEX idx_attempts_status (status)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attempts' AND INDEX_NAME = 'idx_attempts_submitted_at'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE attempts ADD INDEX idx_attempts_submitted_at (submitted_at)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'answers' AND INDEX_NAME = 'idx_answers_evaluated_at'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE answers ADD INDEX idx_answers_evaluated_at (evaluated_at)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'injection_logs' AND INDEX_NAME = 'idx_injection_logs_created_at'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE injection_logs ADD INDEX idx_injection_logs_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND INDEX_NAME = 'idx_audit_created_at'
);
SET @ddl := IF(@idx_exists = 0, 'ALTER TABLE audit_logs ADD INDEX idx_audit_created_at (created_at)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
