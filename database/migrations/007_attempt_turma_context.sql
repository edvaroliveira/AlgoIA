-- IAProg - Migration 007: contexto da publicacao usada em cada tentativa

ALTER TABLE attempts
  ADD COLUMN turma_id INT UNSIGNED NULL AFTER exercise_id,
  ADD INDEX idx_attempts_turma (turma_id),
  ADD CONSTRAINT fk_att_turma
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL;
