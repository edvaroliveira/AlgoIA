DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;
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

CREATE PROCEDURE add_index_if_missing(IN table_name_value VARCHAR(64), IN index_name_value VARCHAR(64), IN index_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_value
      AND INDEX_NAME = index_name_value
  ) THEN
    SET @add_index_sql = CONCAT('ALTER TABLE `', table_name_value, '` ADD ', index_definition);
    PREPARE add_index_stmt FROM @add_index_sql;
    EXECUTE add_index_stmt;
    DEALLOCATE PREPARE add_index_stmt;
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

CALL add_column_if_missing('answers', 'deduction_reasons_json', 'JSON NULL AFTER `ai_feedback`');

UPDATE injection_logs il
LEFT JOIN answers ans ON ans.id = il.answer_id
SET il.answer_id = NULL
WHERE il.answer_id IS NOT NULL
  AND ans.id IS NULL;

CALL add_index_if_missing('injection_logs', 'idx_injection_logs_answer', 'INDEX `idx_injection_logs_answer` (`answer_id`)');
CALL add_fk_if_missing('injection_logs', 'fk_inj_answer', 'FOREIGN KEY (`answer_id`) REFERENCES `answers`(`id`) ON DELETE SET NULL');

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_fk_if_missing;
