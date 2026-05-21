-- IAProg – Migration 003: publicação do exercício com agenda e tentativas por turma

ALTER TABLE exercises
  MODIFY opens_at DATETIME NULL,
  MODIFY closes_at DATETIME NULL,
  MODIFY max_attempts INT NULL,
  MODIFY status ENUM('draft','ready','active') NOT NULL DEFAULT 'draft';

ALTER TABLE exercise_turmas
  ADD COLUMN opens_at DATETIME NULL AFTER turma_id,
  ADD COLUMN closes_at DATETIME NULL AFTER opens_at,
  ADD COLUMN max_attempts INT NULL AFTER closes_at;

UPDATE exercise_turmas et
JOIN exercises e ON e.id = et.exercise_id
SET et.opens_at = COALESCE(et.opens_at, e.opens_at),
    et.closes_at = COALESCE(et.closes_at, e.closes_at),
    et.max_attempts = COALESCE(e.max_attempts, 1);

ALTER TABLE exercise_turmas
  MODIFY opens_at DATETIME NOT NULL,
  MODIFY closes_at DATETIME NOT NULL,
  MODIFY max_attempts INT NOT NULL DEFAULT 1;
