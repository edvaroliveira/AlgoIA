-- Migration 025 — Avisos dentro do app.
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(40)  NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body       VARCHAR(500) NULL,
    link       VARCHAR(255) NULL,
    dedupe_key VARCHAR(120) NULL,
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, read_at),
    UNIQUE KEY uk_notifications_dedupe (user_id, dedupe_key),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
