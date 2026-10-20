-- AlgoIA – Migration 001: schema completo (linha de base)
-- Instalação limpa: aplique só este arquivo, via phpMyAdmin ou CLI.
--
-- Linha de base de 2026-10-19 (tag schema-baseline-2026-10-19): equivale às
-- antigas migrations 001–022. Toda mudança de schema daqui em diante precisa de
-- DUAS coisas: atualizar este arquivo E criar uma migration incremental
-- idempotente a partir da 023 (para o banco que já existe).

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── Usuários ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    avatar_path   VARCHAR(255)  NULL,
    email_notifications TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Avisos também por e-mail (Minha conta)',
    ranking_anonymous TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Aparece como Aluno anônimo no ranking',
    password_hash VARCHAR(255)  NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    password_reset_at DATETIME NULL,
    password_reset_token_hash VARCHAR(255) NULL,
    password_reset_expires_at DATETIME NULL,
    password_changed_at DATETIME NULL,
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
    INDEX idx_users_role_status (role, status),
    INDEX idx_users_registration_source (registration_source),
    CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Turmas ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS turmas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    name       VARCHAR(100) NOT NULL,
    access_key CHAR(6)      NOT NULL UNIQUE,
    active     TINYINT(1)   NOT NULL DEFAULT 1,
    ranking_mode ENUM('off','weekly','monthly','all') NOT NULL DEFAULT 'off',
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
    INDEX idx_student_turma_status (status),
    CONSTRAINT fk_st_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_st_turma   FOREIGN KEY (turma_id)   REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Exercícios ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exercises (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT UNSIGNED NOT NULL,
    title        VARCHAR(200) NOT NULL,
    description  TEXT,
    status       ENUM('draft','ready','active') NOT NULL DEFAULT 'draft',
    admin_review_status ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note TEXT NULL,
    admin_reviewed_at DATETIME NULL,
    admin_reviewed_by INT UNSIGNED NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exercises_status_review (status, admin_review_status),
    CONSTRAINT fk_ex_teacher FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_ex_admin_reviewed_by FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exercise_turmas (
    exercise_id INT UNSIGNED NOT NULL,
    turma_id    INT UNSIGNED NOT NULL,
    opens_at    DATETIME     NOT NULL,
    closes_at   DATETIME     NOT NULL,
    max_attempts INT         NOT NULL DEFAULT 1 COMMENT '0 = ilimitado',
    PRIMARY KEY (exercise_id, turma_id),
    INDEX idx_exercise_turmas_window (opens_at, closes_at),
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
    bank_question_id     INT UNSIGNED NULL COMMENT 'Questão do Banco de exercícios de origem (Importar); dá o Tema',
    admin_review_status  ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note    TEXT NULL,
    admin_reviewed_at    DATETIME NULL,
    admin_reviewed_by    INT UNSIGNED NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_questions_bank_question (bank_question_id),
    INDEX idx_questions_exercise_order (exercise_id, order_index),
    INDEX idx_questions_review (admin_review_status),
    CONSTRAINT fk_q_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_q_admin_reviewed_by FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
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
    INDEX idx_attempts_student_exercise_turma (student_id, exercise_id, turma_id),
    INDEX idx_attempts_status (status),
    INDEX idx_attempts_submitted_at (submitted_at),
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
    worker_id    VARCHAR(36) NULL DEFAULT NULL,
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
    INDEX idx_answers_evaluated_at (evaluated_at),
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
    INDEX idx_injection_logs_created_at (created_at),
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
    INDEX idx_audit_created_at (created_at),
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

-- ── Banco de exercícios (curado pelo Admin) ─────────────────────────────────
-- Snapshot independente: o item do banco não depende do Exercício de origem,
-- que pode ser apagado. Importar copia as Questões para um Rascunho do Docente.
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

-- ── Revisões de nota ────────────────────────────────────────────────────────
-- Ajuste da Pontuação de uma Resposta pelo Docente ('adjusted', com ou sem
-- pedido) e pedidos de revisão do Aluno ('requested' → 'adjusted' ou 'kept').
-- Guarda a nota anterior e a nova: a nota original da IA nunca se perde.
CREATE TABLE IF NOT EXISTS score_reviews (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    answer_id       INT UNSIGNED NOT NULL,
    status          ENUM('requested','adjusted','kept') NOT NULL,
    request_message TEXT NULL,
    requested_by    INT UNSIGNED NULL,
    requested_at    DATETIME NULL,
    previous_score  DECIMAL(4,1) NULL,
    new_score       DECIMAL(4,1) NULL,
    justification   TEXT NULL,
    reviewed_by     INT UNSIGNED NULL,
    reviewed_at     DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_score_reviews_answer (answer_id),
    INDEX idx_score_reviews_status (status),
    CONSTRAINT fk_score_reviews_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE,
    CONSTRAINT fk_score_reviews_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_score_reviews_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Avisos ──────────────────────────────────────────────────────────────────
-- Avisos dentro do app (sino no topo). dedupe_key evita repetir o mesmo aviso
-- para o mesmo usuário (ex.: prazo encerrando, correção concluída).
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       VARCHAR(500) NULL,
    link       VARCHAR(255) NULL,
    dedupe_key VARCHAR(120) NULL,
    read_at    DATETIME     NULL,
    emailed_at DATETIME NULL,
    email_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_email_queue (emailed_at, created_at),
    INDEX idx_notifications_user_read (user_id, read_at),
    UNIQUE KEY uk_notifications_dedupe (user_id, dedupe_key),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Modo prática (migration 026): só correções bem-sucedidas; base do limite diário.
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

-- Mural da Turma (migration 029).
CREATE TABLE IF NOT EXISTS turma_posts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id   INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    body       TEXT         NOT NULL,
    pinned     TINYINT(1)   NOT NULL DEFAULT 0,
    expires_at DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_turma_posts_turma (turma_id, pinned, created_at),
    CONSTRAINT fk_turma_posts_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    CONSTRAINT fk_turma_posts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conquistas do Aluno (migration 030).
CREATE TABLE IF NOT EXISTS student_achievements (
    student_id INT UNSIGNED NOT NULL,
    code       VARCHAR(100) NOT NULL,
    earned_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, code),
    CONSTRAINT fk_achievements_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uso da IA (migration 032): sem FKs para o histórico de custo sobreviver a exclusões.
CREATE TABLE IF NOT EXISTS ai_usage_logs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    kind              ENUM('grading','practice') NOT NULL,
    teacher_id        INT UNSIGNED NULL,
    turma_id          INT UNSIGNED NULL,
    exercise_id       INT UNSIGNED NULL,
    attempt_id        INT UNSIGNED NULL,
    answer_id         INT UNSIGNED NULL,
    student_id        INT UNSIGNED NULL,
    bank_question_id  INT UNSIGNED NULL,
    model             VARCHAR(60)  NOT NULL,
    prompt_tokens     INT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    latency_ms        INT UNSIGNED NOT NULL DEFAULT 0,
    status            ENUM('ok','error') NOT NULL,
    error_category    VARCHAR(40)  NULL,
    cost_usd          DECIMAL(12,6) NOT NULL DEFAULT 0,
    INDEX idx_ai_usage_created (created_at),
    INDEX idx_ai_usage_teacher (teacher_id, created_at),
    INDEX idx_ai_usage_turma (turma_id, created_at),
    INDEX idx_ai_usage_kind (kind, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Origem das Questões importadas (migration 027). A chave estrangeira vem por
-- último porque bank_questions é criada depois de questions. Num banco antigo
-- (questions sem a coluna), quem acrescenta coluna e chave é a 027.
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'bank_question_id');
SET @has_fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND CONSTRAINT_NAME = 'fk_questions_bank_question');
SET @sql := IF(@has_col = 1 AND @has_fk = 0,
  'ALTER TABLE questions ADD CONSTRAINT fk_questions_bank_question FOREIGN KEY (bank_question_id) REFERENCES bank_questions(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
