-- IAProg – Migration 016: foto de perfil do usuario (avatar)
-- Adiciona users.avatar_path (caminho relativo do arquivo em public/assets/uploads).
-- Idempotente: nao falha se a coluna ja existir.

DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER //
CREATE PROCEDURE add_column_if_missing(IN table_name_value VARCHAR(64), IN column_name_value VARCHAR(64), IN column_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_value
      AND COLUMN_NAME = column_name_value
  ) THEN
    SET @add_column_sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD COLUMN `', column_name_value, '` ', column_definition);
    PREPARE add_column_stmt FROM @add_column_sql;
    EXECUTE add_column_stmt;
    DEALLOCATE PREPARE add_column_stmt;
  END IF;
END//
DELIMITER ;

CALL add_column_if_missing('users', 'avatar_path', 'VARCHAR(255) NULL AFTER `email`');

DROP PROCEDURE IF EXISTS add_column_if_missing;
