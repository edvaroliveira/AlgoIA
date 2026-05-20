-- IAProg – Migration 001: Schema completo
-- Execute no MySQL/MariaDB do Hostgator via phpMyAdmin ou CLI

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── Usuários ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('teacher','student') NOT NULL DEFAULT 'student',
    status        ENUM('pending','active','inactive') NOT NULL DEFAULT 'pending',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Turmas ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turmas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    access_key CHAR(6)      NOT NULL UNIQUE,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_turmas_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Vínculo Aluno ↔ Turma ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS student_turma (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    turma_id   INT UNSIGNED NOT NULL,
    status     ENUM('pending','active') NOT NULL DEFAULT 'pending',
    joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_turma (student_id, turma_id),
    CONSTRAINT fk_st_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_st_turma   FOREIGN KEY (turma_id)   REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Exercícios ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exercises (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT UNSIGNED NOT NULL,
    turma_id     INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT,
    opens_at     DATETIME     NOT NULL,
    closes_at    DATETIME     NOT NULL,
    max_attempts INT          NOT NULL DEFAULT 1 COMMENT '0 = ilimitado',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ex_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_ex_turma   FOREIGN KEY (turma_id)   REFERENCES turmas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Questões ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS questions (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id          INT UNSIGNED NOT NULL,
    text                 TEXT         NOT NULL,
    expected_answer_hint TEXT         NOT NULL COMMENT 'Conceitos esperados — visível ao aluno apenas após fechamento',
    max_score            DECIMAL(4,1) NOT NULL DEFAULT 10.0,
    order_index          INT          NOT NULL DEFAULT 0,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_q_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tentativas ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id  INT UNSIGNED NOT NULL,
    student_id   INT UNSIGNED NOT NULL,
    started_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME     NULL,
    total_score  DECIMAL(8,2) NULL,
    status       ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
    CONSTRAINT fk_att_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    CONSTRAINT fk_att_student  FOREIGN KEY (student_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Respostas ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS answers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id     INT UNSIGNED NOT NULL,
    question_id    INT UNSIGNED NOT NULL,
    student_answer TEXT         NOT NULL,
    ai_score       DECIMAL(4,1) NULL,
    ai_feedback    TEXT         NULL,
    evaluated_at   DATETIME     NULL,
    UNIQUE KEY uk_answer (attempt_id, question_id),
    CONSTRAINT fk_ans_attempt  FOREIGN KEY (attempt_id)  REFERENCES attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_ans_question FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Log de Prompt Injection ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS injection_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    answer_id       INT UNSIGNED NULL,
    student_id      INT UNSIGNED NOT NULL,
    flagged_pattern VARCHAR(255) NOT NULL,
    student_answer  TEXT         NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inj_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
