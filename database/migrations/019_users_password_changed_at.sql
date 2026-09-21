-- Migration 019 — marca de versao da senha para invalidar sessoes antigas.
--
-- Toda troca de senha grava password_changed_at. A sessao guarda o valor visto
-- no login; quando o refresh periodico encontra um valor diferente, a sessao e
-- encerrada. Isso derruba sessoes abertas em outros dispositivos apos um reset.
--
-- Idempotente: so adiciona a coluna quando ela ainda nao existe.

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password_changed_at'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER password_reset_expires_at',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bases existentes: parte de created_at para que sessoes atuais nao caiam
-- todas de uma vez no primeiro deploy.
UPDATE users SET password_changed_at = created_at WHERE password_changed_at IS NULL;
