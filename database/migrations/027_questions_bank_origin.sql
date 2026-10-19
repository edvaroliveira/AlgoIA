-- Migration 027 — Origem das Questões importadas do Banco de exercícios.
-- Permite o progresso por Tema também nas Tentativas.
-- Idempotente: só acrescenta coluna e chave estrangeira se faltarem; o UPDATE
-- só preenche Questões ainda sem origem. Também está no 001 (linha de base).

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'bank_question_id');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE questions ADD COLUMN bank_question_id INT UNSIGNED NULL AFTER order_index, ADD INDEX idx_questions_bank_question (bank_question_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND CONSTRAINT_NAME = 'fk_questions_bank_question');
SET @sql := IF(@has_fk = 0,
  'ALTER TABLE questions ADD CONSTRAINT fk_questions_bank_question FOREIGN KEY (bank_question_id) REFERENCES bank_questions(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Importações anteriores: a cópia é literal, então enunciado e Resposta de
-- referência idênticos identificam a Questão de origem no banco.
UPDATE questions q
  JOIN bank_questions bq ON bq.text = q.text AND bq.expected_answer_hint = q.expected_answer_hint
   SET q.bank_question_id = bq.id
 WHERE q.bank_question_id IS NULL;
