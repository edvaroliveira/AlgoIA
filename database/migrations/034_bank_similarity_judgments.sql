-- Migration 034 — IA como juiz da similaridade no Banco de exercícios.
-- bank_similarity_judgments: veredito da IA para um par duvidoso (Questão de uma
--   Sugestão × Questão parecida já no banco ou em Sugestão anterior), feito pelo
--   cron (bin/process_grading_jobs.php) quando o Admin liga o juiz em Configurações.
--   Só informa o Admin; quem bloqueia é o Limite de similaridade (função própria).
-- ai_usage_logs.kind ganha 'similarity' para o custo aparecer no painel Uso da IA.
-- Idempotente: cada passo só roda se ainda faltar. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS bank_similarity_judgments (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_question_id       INT UNSIGNED  NOT NULL,
    match_bank_question_id INT UNSIGNED  NOT NULL,
    lexical_pct            DECIMAL(4,1)  NOT NULL,
    verdict                ENUM('same','different') NOT NULL,
    reason                 VARCHAR(300)  NOT NULL DEFAULT '',
    model                  VARCHAR(60)   NOT NULL,
    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bsj_pair (bank_question_id, match_bank_question_id),
    INDEX idx_bsj_match (match_bank_question_id),
    CONSTRAINT fk_bsj_question FOREIGN KEY (bank_question_id) REFERENCES bank_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_bsj_match FOREIGN KEY (match_bank_question_id) REFERENCES bank_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ai_usage_logs'
               AND COLUMN_NAME = 'kind' AND COLUMN_TYPE LIKE '%similarity%');
SET @sql := IF(@has = 0,
  'ALTER TABLE ai_usage_logs MODIFY kind ENUM(''grading'',''practice'',''similarity'') NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
