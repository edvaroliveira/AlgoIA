-- IAProg – Migration 015: corrigir FK exercises.turma_id para ON DELETE SET NULL
-- Alinha 001_create_tables.sql com o comportamento correto já presente em 000_reset_test_*

ALTER TABLE exercises
  DROP FOREIGN KEY fk_ex_turma;

ALTER TABLE exercises
  ADD CONSTRAINT fk_ex_turma
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL;
