ALTER TABLE answers
  ADD COLUMN deduction_reasons_json JSON NULL AFTER ai_feedback;

ALTER TABLE injection_logs
  ADD INDEX idx_injection_logs_answer (answer_id),
  ADD CONSTRAINT fk_inj_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE SET NULL;
