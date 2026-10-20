-- Migration 029 — Mural da Turma: recados do Docente para os Alunos.
-- Idempotente: CREATE TABLE IF NOT EXISTS. Também está no 001 (linha de base).

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
