-- IAProg – Migration 017: worker_id identifica qual processo detém o lock de um job
-- Impede que dois workers concluam o mesmo job em processamento.

ALTER TABLE grading_jobs
  ADD COLUMN worker_id VARCHAR(36) NULL DEFAULT NULL AFTER locked_at;
