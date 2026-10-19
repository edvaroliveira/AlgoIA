-- Migration 026 — Modo prática: respostas corrigidas na hora, sem nota.
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS practice_answers (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id             INT UNSIGNED NOT NULL,
    bank_question_id       INT UNSIGNED NOT NULL,
    student_answer         TEXT         NOT NULL,
    score                  DECIMAL(4,1) NOT NULL,
    max_score              DECIMAL(4,1) NOT NULL,
    feedback               TEXT         NOT NULL,
    deduction_reasons_json JSON         NULL,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_practice_student_created (student_id, created_at),
    INDEX idx_practice_student_question (student_id, bank_question_id),
    CONSTRAINT fk_practice_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_practice_question FOREIGN KEY (bank_question_id) REFERENCES bank_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
