-- Migration 031 — Ranking da Turma (opcional).
-- turmas.ranking_mode: off (padrão), weekly, monthly, all (desde o início da Turma).
-- users.ranking_anonymous: Aluno aparece como "Aluno anônimo" para os colegas.
-- Idempotente: cada coluna só é criada se faltar. Também está no 001 (linha de base).

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'turmas' AND COLUMN_NAME = 'ranking_mode');
SET @sql := IF(@has = 0,
  'ALTER TABLE turmas ADD COLUMN ranking_mode ENUM(''off'',''weekly'',''monthly'',''all'') NOT NULL DEFAULT ''off'' AFTER active',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'ranking_anonymous');
SET @sql := IF(@has = 0,
  'ALTER TABLE users ADD COLUMN ranking_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER email_notifications',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
