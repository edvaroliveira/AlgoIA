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

CALL add_column_if_missing('users', 'registration_source', 'ENUM(''manual'',''student_public'',''teacher_public'') NOT NULL DEFAULT ''manual'' AFTER `registration_note`');

UPDATE users
SET registration_source = 'manual';

UPDATE users u
JOIN audit_logs al ON al.entity_type = 'user'
  AND al.entity_id = u.id
  AND al.action = 'auth.teacher_registration_request'
SET u.registration_source = 'teacher_public'
WHERE u.role = 'teacher';

UPDATE users
SET registration_source = 'student_public'
WHERE role = 'student'
  AND status = 'pending'
  AND registration_source = 'manual';

DROP PROCEDURE IF EXISTS add_column_if_missing;
