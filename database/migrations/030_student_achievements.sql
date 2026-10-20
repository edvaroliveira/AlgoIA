-- Migration 030 — Conquistas do Aluno (visíveis só para ele).
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS student_achievements (
    student_id INT UNSIGNED NOT NULL,
    code       VARCHAR(100) NOT NULL,
    earned_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, code),
    CONSTRAINT fk_achievements_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
