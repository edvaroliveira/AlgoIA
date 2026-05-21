-- IAProg – Migration 002: exercícios em rascunho + vínculo com múltiplas turmas

ALTER TABLE exercises
  MODIFY turma_id INT UNSIGNED NULL;

ALTER TABLE exercises
  ADD COLUMN status ENUM('draft','active') NOT NULL DEFAULT 'draft' AFTER max_attempts;

CREATE TABLE IF NOT EXISTS exercise_turmas (
  exercise_id INT UNSIGNED NOT NULL,
  turma_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (exercise_id, turma_id),
  CONSTRAINT fk_ex_turmas_exercise FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
  CONSTRAINT fk_ex_turmas_turma    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO exercise_turmas (exercise_id, turma_id)
SELECT id, turma_id
FROM exercises
WHERE turma_id IS NOT NULL;

UPDATE exercises
SET status = CASE WHEN turma_id IS NULL THEN 'draft' ELSE 'active' END;
