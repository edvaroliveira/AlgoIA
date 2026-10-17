-- Migration 021 — remove de exercises as colunas legadas de turma, janela e limite.
--
-- Desde a migration 003, abertura, encerramento e limite de tentativas pertencem
-- à Publicação (exercise_turmas), não ao Exercício (ver docs/adr/0003). As
-- colunas exercises.turma_id/opens_at/closes_at/max_attempts não são mais
-- gravadas pelo app; mantidas, eram herdadas por engano quando o Aluno não tinha
-- Publicação (RD-02/RD-03, issue #35).
--
-- Antes de aplicar em uma base com dados, confirme que não há Tentativas sem
-- Turma: SELECT COUNT(*) FROM attempts WHERE turma_id IS NULL; (deve ser 0).
--
-- Idempotente: só remove a FK e cada coluna quando ainda existem.

SET @fk_name := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exercises'
      AND COLUMN_NAME = 'turma_id'
      AND REFERENCED_TABLE_NAME = 'turmas'
    LIMIT 1
);

SET @ddl := IF(
    @fk_name IS NOT NULL,
    CONCAT('ALTER TABLE exercises DROP FOREIGN KEY `', @fk_name, '`'),
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND COLUMN_NAME = 'turma_id'
);
SET @ddl := IF(@col_exists > 0, 'ALTER TABLE exercises DROP COLUMN turma_id', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND COLUMN_NAME = 'opens_at'
);
SET @ddl := IF(@col_exists > 0, 'ALTER TABLE exercises DROP COLUMN opens_at', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND COLUMN_NAME = 'closes_at'
);
SET @ddl := IF(@col_exists > 0, 'ALTER TABLE exercises DROP COLUMN closes_at', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exercises' AND COLUMN_NAME = 'max_attempts'
);
SET @ddl := IF(@col_exists > 0, 'ALTER TABLE exercises DROP COLUMN max_attempts', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
