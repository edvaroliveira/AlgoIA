-- IAProg – Migration 014: fila de correção automática
-- Desacopla o submit do aluno da chamada síncrona para o provedor de IA.

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
