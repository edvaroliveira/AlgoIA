-- Migration 023 — Banco de exercícios (curado pelo Admin).
--
-- Tabelas próprias, separadas de exercises/questions, para que itens do banco
-- nunca apareçam nas consultas de Docente, Aluno ou Admin sobre Exercícios.
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS bank_exercises (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(200) NOT NULL,
    description        TEXT NULL,
    source_exercise_id INT UNSIGNED NULL,
    source_teacher_id  INT UNSIGNED NULL,
    status             ENUM('suggested','active','rejected','archived') NOT NULL DEFAULT 'suggested',
    review_note        TEXT NULL,
    reviewed_by        INT UNSIGNED NULL,
    reviewed_at        DATETIME NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bank_exercises_status (status),
    INDEX idx_bank_exercises_source (source_exercise_id),
    CONSTRAINT fk_bank_ex_source FOREIGN KEY (source_exercise_id) REFERENCES exercises(id) ON DELETE SET NULL,
    CONSTRAINT fk_bank_ex_teacher FOREIGN KEY (source_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_bank_ex_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_questions (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bank_exercise_id     INT UNSIGNED NOT NULL,
    text                 TEXT NOT NULL,
    expected_answer_hint TEXT NOT NULL,
    max_score            DECIMAL(4,1) NOT NULL DEFAULT 10.0,
    order_index          INT NOT NULL DEFAULT 0,
    INDEX idx_bank_questions_exercise_order (bank_exercise_id, order_index),
    CONSTRAINT fk_bank_q_exercise FOREIGN KEY (bank_exercise_id) REFERENCES bank_exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_themes (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    UNIQUE KEY uk_bank_themes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bank_exercise_themes (
    bank_exercise_id INT UNSIGNED NOT NULL,
    theme_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (bank_exercise_id, theme_id),
    INDEX idx_bank_exercise_themes_theme (theme_id),
    CONSTRAINT fk_bank_et_exercise FOREIGN KEY (bank_exercise_id) REFERENCES bank_exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_bank_et_theme FOREIGN KEY (theme_id) REFERENCES bank_themes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
