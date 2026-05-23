-- Adiciona status 'rejected' ao enum de users e colunas de aprovacao
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

CREATE PROCEDURE add_fk_if_missing(IN table_name_value VARCHAR(64), IN constraint_name_value VARCHAR(64), IN fk_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_value
      AND CONSTRAINT_NAME = constraint_name_value
  ) THEN
    SET @add_fk_sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD CONSTRAINT `', constraint_name_value, '` ', fk_definition);
    PREPARE add_fk_stmt FROM @add_fk_sql;
    EXECUTE add_fk_stmt;
    DEALLOCATE PREPARE add_fk_stmt;
  END IF;
END//
DELIMITER ;

ALTER TABLE users
  MODIFY COLUMN status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending';

CALL add_column_if_missing('users', 'registration_note', 'TEXT NULL AFTER `status`');
CALL add_column_if_missing('users', 'approved_by', 'INT UNSIGNED NULL AFTER `registration_note`');
CALL add_column_if_missing('users', 'approved_at', 'DATETIME NULL AFTER `approved_by`');
CALL add_column_if_missing('users', 'rejected_at', 'DATETIME NULL AFTER `approved_at`');
CALL add_fk_if_missing('users', 'fk_users_approved_by', 'FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL');

-- Configuracoes do sistema
CREATE TABLE IF NOT EXISTS system_settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL DEFAULT '',
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_setting_key (setting_key),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('teacher_registration_enabled', '0');

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;
