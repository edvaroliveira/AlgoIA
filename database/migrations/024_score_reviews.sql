-- Migration 024 — Revisões de nota (ajuste pelo Docente e pedido de revisão do Aluno).
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

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
