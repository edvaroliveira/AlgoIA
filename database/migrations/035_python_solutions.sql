-- Migration 035 — Resposta em Python por Questão.
-- questions / bank_questions ganham o código Python da resposta correta, gerado
--   uma vez por Questão pela IA (cron, depois que o Docente conclui o Exercício),
--   revisado e aprovado pelo Docente. O Aluno só vê o código aprovado, na mesma
--   regra da Resposta de referência. O código viaja com a Questão para o Banco e
--   na Importação, sem nova chamada à IA.
--   python_solution_status: NULL = nunca pedido; pending = na fila do cron;
--   draft = rascunho (IA ou Docente); approved = visível ao Aluno; failed = IA falhou.
-- ai_usage_logs.kind ganha 'python' para o custo aparecer no painel Uso da IA.
-- Idempotente: cada passo só roda se ainda faltar. Também está no 001 (linha de base).

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'python_solution');
SET @sql := IF(@has = 0,
  'ALTER TABLE questions
     ADD COLUMN python_solution TEXT NULL COMMENT ''Resposta em Python; o Aluno vê só se approved'',
     ADD COLUMN python_solution_status ENUM(''pending'',''draft'',''approved'',''failed'') NULL,
     ADD COLUMN python_solution_source ENUM(''ai'',''teacher'') NULL,
     ADD COLUMN python_solution_updated_at DATETIME NULL,
     ADD COLUMN python_solution_reviewed_by INT UNSIGNED NULL,
     ADD INDEX idx_questions_python_status (python_solution_status),
     ADD CONSTRAINT fk_q_python_reviewed_by FOREIGN KEY (python_solution_reviewed_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_questions' AND COLUMN_NAME = 'python_solution');
SET @sql := IF(@has = 0,
  'ALTER TABLE bank_questions
     ADD COLUMN python_solution TEXT NULL COMMENT ''Resposta em Python aprovada, copiada da Questão de origem'',
     ADD COLUMN python_solution_status ENUM(''pending'',''draft'',''approved'',''failed'') NULL,
     ADD COLUMN python_solution_source ENUM(''ai'',''teacher'') NULL,
     ADD COLUMN python_solution_updated_at DATETIME NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_usage_logs'
               AND COLUMN_NAME = 'kind' AND COLUMN_TYPE LIKE '%python%');
SET @sql := IF(@has = 0,
  'ALTER TABLE ai_usage_logs MODIFY kind ENUM(''grading'',''practice'',''similarity'',''python'') NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
