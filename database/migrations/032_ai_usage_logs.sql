-- Migration 032 — Uso da IA: uma linha por chamada à OpenAI (tokens, custo, contexto).
-- Sem chaves estrangeiras de propósito: o histórico de custo sobrevive a exclusões
-- de Turmas, Exercícios e Usuários.
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

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
