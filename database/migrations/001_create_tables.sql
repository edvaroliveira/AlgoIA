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
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    password_reset_at DATETIME NULL,
    password_reset_token_hash VARCHAR(255) NULL,
    password_reset_expires_at DATETIME NULL,
    role          ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    status        ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
    registration_note TEXT NULL,
    registration_source ENUM('manual','student_public','teacher_public') NOT NULL DEFAULT 'manual',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_password_reset_expires (password_reset_expires_at),
    CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
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
    turma_id     INT UNSIGNED NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT,
    opens_at     DATETIME     NULL,
    closes_at    DATETIME     NULL,
    max_attempts INT          NULL COMMENT 'legado: configuração movida para a publicação por turma',
    status       ENUM('draft','ready','active') NOT NULL DEFAULT 'draft',
    admin_review_status ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note TEXT NULL,
    admin_reviewed_at DATETIME NULL,
    admin_reviewed_by INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ex_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_ex_turma   FOREIGN KEY (turma_id)   REFERENCES turmas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_turmas (
    exercise_id INT UNSIGNED NOT NULL,
    turma_id    INT UNSIGNED NOT NULL,
    opens_at    DATETIME     NOT NULL,
    closes_at   DATETIME     NOT NULL,
    max_attempts INT         NOT NULL DEFAULT 1 COMMENT '0 = ilimitado',
    PRIMARY KEY (exercise_id, turma_id),
    CONSTRAINT fk_ex_turmas_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_ex_turmas_turma    FOREIGN KEY (turma_id)    REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Questões ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS questions (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id          INT UNSIGNED NOT NULL,
    text                 TEXT         NOT NULL,
    expected_answer_hint TEXT         NOT NULL COMMENT 'Conceitos esperados — visível ao aluno apenas após fechamento',
    max_score            DECIMAL(4,1) NOT NULL DEFAULT 10.0,
    order_index          INT          NOT NULL DEFAULT 0,
    admin_review_status  ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note    TEXT NULL,
    admin_reviewed_at    DATETIME NULL,
    admin_reviewed_by    INT UNSIGNED NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_q_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tentativas ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS attempts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id  INT UNSIGNED NOT NULL,
    student_id   INT UNSIGNED NOT NULL,
    turma_id     INT UNSIGNED NULL,
    started_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME     NULL,
    total_score  DECIMAL(8,2) NULL,
    status       ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
    INDEX idx_attempts_turma (turma_id),
    CONSTRAINT fk_att_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_student  FOREIGN KEY (student_id)  REFERENCES users(id),
    CONSTRAINT fk_attempts_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Fila de Correção ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS grading_jobs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id   INT UNSIGNED NOT NULL,
    status       ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
    attempts     INT UNSIGNED NOT NULL DEFAULT 0,
    last_error   TEXT NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at    DATETIME NULL,
    completed_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grading_jobs_attempt (attempt_id),
    INDEX idx_grading_jobs_status_available (status, available_at),
    CONSTRAINT fk_grading_jobs_attempt FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Respostas ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS answers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id     INT UNSIGNED NOT NULL,
    question_id    INT UNSIGNED NOT NULL,
    student_answer TEXT         NOT NULL,
    ai_score       DECIMAL(4,1) NULL,
    ai_feedback    TEXT         NULL,
    deduction_reasons_json JSON NULL,
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
    INDEX idx_injection_logs_answer (answer_id),
    CONSTRAINT fk_inj_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE SET NULL,
    CONSTRAINT fk_inj_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Auditoria ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_role    VARCHAR(20)  NOT NULL,
    action        VARCHAR(100) NOT NULL,
    entity_type   VARCHAR(60)  NOT NULL,
    entity_id     INT UNSIGNED NULL,
    metadata_json JSON         NULL,
    ip_address    VARCHAR(45)  NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action (action),
    CONSTRAINT fk_audit_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tentativas de Login ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45)  NOT NULL,
    user_agent VARCHAR(255) NULL,
    succeeded  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_identity (email, ip_address, succeeded, created_at),
    INDEX idx_login_attempts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Configurações ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL DEFAULT '',
    updated_by    INT UNSIGNED NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_setting_key (setting_key),
    CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('teacher_registration_enabled', '0');

SET FOREIGN_KEY_CHECKS = 1;
