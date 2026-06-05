-- IAProg – Migration 016: foto de perfil do usuario (avatar)
-- Adiciona users.avatar_path (caminho relativo do arquivo em public/assets/uploads).
-- Idempotente e seguro contra mismatch de collation no INFORMATION_SCHEMA
-- (as comparacoes sao forcadas para utf8_general_ci, evitando o erro #1267).

-- Limpa eventual procedure deixada por tentativa anterior desta migration.
DROP PROCEDURE IF EXISTS add_column_if_missing;

SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = CONVERT(DATABASE()       USING utf8) COLLATE utf8_general_ci
    AND TABLE_NAME   = CONVERT('users'          USING utf8) COLLATE utf8_general_ci
    AND COLUMN_NAME  = CONVERT('avatar_path'    USING utf8) COLLATE utf8_general_ci
);

SET @ddl := IF(
  @col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `email`',
  'DO 0'
);

PREPARE add_avatar_stmt FROM @ddl;
EXECUTE add_avatar_stmt;
DEALLOCATE PREPARE add_avatar_stmt;
