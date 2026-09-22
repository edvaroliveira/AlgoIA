-- Migration 020 — adiciona FK admin_reviewed_by -> users(id) em exercises e questions.
--
-- 001_create_tables.sql (schema consolidado) nao tinha essas FKs, mas os
-- schemas de teste (000_reset_test_*_hostgator.sql) sempre tiveram. Sem a FK,
-- um id invalido em admin_reviewed_by (ex.: usuario admin excluido por engano
-- em algum ponto futuro do sistema) fica silenciosamente orfao em vez de ser
-- bloqueado ou setado para NULL pelo banco.
--
-- Idempotente: so adiciona a constraint quando ela ainda nao existe.

SET @fk_ex_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'exercises'
      AND CONSTRAINT_NAME = 'fk_ex_admin_reviewed_by'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @ddl := IF(
    @fk_ex_exists = 0,
    'ALTER TABLE exercises ADD CONSTRAINT fk_ex_admin_reviewed_by FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_q_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'questions'
      AND CONSTRAINT_NAME = 'fk_q_admin_reviewed_by'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @ddl := IF(
    @fk_q_exists = 0,
    'ALTER TABLE questions ADD CONSTRAINT fk_q_admin_reviewed_by FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
