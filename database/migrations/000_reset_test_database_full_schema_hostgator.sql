-- AlgoIA - Reset completo do banco de TESTE HostGator
-- Data: 2026-05-23
--
-- ATENCAO: este script APAGA o banco informado e recria a estrutura do zero.
-- Use somente em ambiente de teste/homologacao.
-- Antes de executar, confirme se o nome abaixo NAO e o banco de producao.
--
-- Banco sugerido de teste:
--   edvarp17_algoia_teste
--
-- Observacao HostGator/cPanel: em alguns planos o usuario MySQL nao pode
-- executar DROP DATABASE/CREATE DATABASE. Nesse caso, apague/recrie o banco
-- pelo cPanel, comente as linhas DROP/CREATE/USE abaixo e execute o restante
-- dentro do banco de teste ja selecionado no phpMyAdmin.
--
-- Se o seu banco de teste tiver outro nome, altere as 3 linhas abaixo:
--   DROP DATABASE IF EXISTS `edvarp17_algoia_teste`;
--   CREATE DATABASE `edvarp17_algoia_teste` ...;
--   USE `edvarp17_algoia_teste`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `edvarp17_algoia_teste`;
CREATE DATABASE `edvarp17_algoia_teste`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `edvarp17_algoia_teste`;

SET FOREIGN_KEY_CHECKS = 1;

-- Usuarios
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    password_reset_at DATETIME NULL,
    password_reset_token_hash VARCHAR(255) NULL,
    password_reset_expires_at DATETIME NULL,
    role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
    registration_note TEXT NULL,
    registration_source ENUM('manual','student_public','teacher_public') NOT NULL DEFAULT 'manual',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email (email),
    KEY idx_users_password_reset_expires (password_reset_expires_at),
    KEY idx_users_role_status (role, status),
    KEY idx_users_registration_source (registration_source),
    KEY idx_users_approved_by (approved_by),
    CONSTRAINT fk_users_approved_by
      FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Turmas
CREATE TABLE turmas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    access_key CHAR(6) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_turmas_access_key (access_key),
    KEY idx_turmas_teacher (teacher_id),
    CONSTRAINT fk_turmas_teacher
      FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vinculo aluno/turma
CREATE TABLE student_turma (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NOT NULL,
    status ENUM('pending','active') NOT NULL DEFAULT 'pending',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_turma (student_id, turma_id),
    KEY idx_student_turma_turma (turma_id),
    KEY idx_student_turma_status (status),
    CONSTRAINT fk_st_student
      FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_st_turma
      FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exercicios
CREATE TABLE exercises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NULL COMMENT 'legado: publicacao atual usa exercise_turmas',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    opens_at DATETIME NULL COMMENT 'legado: janela atual usa exercise_turmas',
    closes_at DATETIME NULL COMMENT 'legado: janela atual usa exercise_turmas',
    max_attempts INT NULL COMMENT 'legado: tentativas atuais usam exercise_turmas',
    status ENUM('draft','ready','active') NOT NULL DEFAULT 'draft',
    admin_review_status ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note TEXT NULL,
    admin_reviewed_at DATETIME NULL,
    admin_reviewed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_exercises_teacher (teacher_id),
    KEY idx_exercises_turma (turma_id),
    KEY idx_exercises_status_review (status, admin_review_status),
    KEY idx_exercises_admin_reviewed_by (admin_reviewed_by),
    CONSTRAINT fk_ex_teacher
      FOREIGN KEY (teacher_id) REFERENCES users(id),
    CONSTRAINT fk_ex_turma
      FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL,
    CONSTRAINT fk_ex_admin_reviewed_by
      FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publicacao de exercicios por turma
CREATE TABLE exercise_turmas (
    exercise_id INT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NOT NULL,
    opens_at DATETIME NOT NULL,
    closes_at DATETIME NOT NULL,
    max_attempts INT NOT NULL DEFAULT 1 COMMENT '0 = ilimitado',
    PRIMARY KEY (exercise_id, turma_id),
    KEY idx_exercise_turmas_turma (turma_id),
    KEY idx_exercise_turmas_window (opens_at, closes_at),
    CONSTRAINT fk_ex_turmas_exercise
      FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_ex_turmas_turma
      FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questoes
CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    expected_answer_hint TEXT NOT NULL COMMENT 'Conceitos esperados; visivel ao aluno apenas apos fechamento',
    max_score DECIMAL(4,1) NOT NULL DEFAULT 10.0,
    order_index INT NOT NULL DEFAULT 0,
    admin_review_status ENUM('approved','flagged','blocked') NOT NULL DEFAULT 'approved',
    admin_review_note TEXT NULL,
    admin_reviewed_at DATETIME NULL,
    admin_reviewed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_questions_exercise_order (exercise_id, order_index),
    KEY idx_questions_review (admin_review_status),
    KEY idx_questions_admin_reviewed_by (admin_reviewed_by),
    CONSTRAINT fk_q_exercise
      FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_q_admin_reviewed_by
      FOREIGN KEY (admin_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas
CREATE TABLE attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exercise_id INT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    total_score DECIMAL(8,2) NULL,
    status ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
    KEY idx_attempts_student_exercise_turma (student_id, exercise_id, turma_id),
    KEY idx_attempts_exercise (exercise_id),
    KEY idx_attempts_turma (turma_id),
    KEY idx_attempts_status (status),
    KEY idx_attempts_submitted_at (submitted_at),
    CONSTRAINT fk_att_exercise
      FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_turma
      FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL,
    CONSTRAINT fk_att_student
      FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fila de correcao automatica
CREATE TABLE grading_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    status ENUM('queued','processing','completed','failed') NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_grading_jobs_attempt (attempt_id),
    KEY idx_grading_jobs_status_available (status, available_at),
    CONSTRAINT fk_grading_jobs_attempt
      FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Respostas
CREATE TABLE answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    student_answer TEXT NOT NULL,
    ai_score DECIMAL(4,1) NULL,
    ai_feedback TEXT NULL,
    deduction_reasons_json JSON NULL,
    evaluated_at DATETIME NULL,
    UNIQUE KEY uk_answer (attempt_id, question_id),
    KEY idx_answers_question (question_id),
    KEY idx_answers_evaluated_at (evaluated_at),
    CONSTRAINT fk_ans_attempt
      FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_ans_question
      FOREIGN KEY (question_id) REFERENCES questions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de prompt injection
CREATE TABLE injection_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    answer_id INT UNSIGNED NULL,
    student_id INT UNSIGNED NOT NULL,
    flagged_pattern VARCHAR(255) NOT NULL,
    student_answer TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_injection_logs_answer (answer_id),
    KEY idx_injection_logs_student (student_id),
    KEY idx_injection_logs_created_at (created_at),
    CONSTRAINT fk_inj_answer
      FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE SET NULL,
    CONSTRAINT fk_inj_student
      FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_role VARCHAR(20) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED NULL,
    metadata_json JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_actor (actor_user_id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_action (action),
    KEY idx_audit_created_at (created_at),
    CONSTRAINT fk_audit_actor_user
      FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativas de login
CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_identity (email, ip_address, succeeded, created_at),
    KEY idx_login_attempts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuracoes do sistema
CREATE TABLE system_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_setting_key (setting_key),
    KEY idx_settings_updated_by (updated_by),
    CONSTRAINT fk_settings_updated_by
      FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeds minimos para ambiente de teste
INSERT INTO system_settings (setting_key, setting_value)
VALUES ('teacher_registration_enabled', '0');

-- Admin inicial de teste
-- Email: admin@algoia.test
-- Senha temporaria: AlgoIA@2026!Trocar
-- O sistema exigira troca de senha no primeiro acesso.
INSERT INTO users (
    name,
    email,
    password_hash,
    must_change_password,
    role,
    status,
    registration_source,
    created_at,
    updated_at
) VALUES (
    'Administrador Teste',
    'admin@algoia.test',
    '$2y$12$zj85OG.MwFGQJZ3L28KBnuk9Ofq/PDaGwpejdFYyc6q2jsrrlsc62',
    1,
    'admin',
    'active',
    'manual',
    NOW(),
    NOW()
);
