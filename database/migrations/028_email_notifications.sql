-- Migration 028 — Avisos por e-mail.
-- users.email_notifications: preferência do Usuário (ligado por padrão).
-- notifications.emailed_at / email_attempts: fila de envio do worker.
-- Idempotente: cada coluna só é criada se faltar. Também está no 001 (linha de base).

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_notifications');
SET @sql := IF(@has = 0,
  'ALTER TABLE users ADD COLUMN email_notifications TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'emailed_at');
SET @sql := IF(@has = 0,
  'ALTER TABLE notifications ADD COLUMN emailed_at DATETIME NULL AFTER read_at, ADD COLUMN email_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER emailed_at, ADD INDEX idx_notifications_email_queue (emailed_at, created_at)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
