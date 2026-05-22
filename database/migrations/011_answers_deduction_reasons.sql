ALTER TABLE answers
  ADD COLUMN deduction_reasons_json JSON NULL AFTER ai_feedback;

UPDATE injection_logs il
LEFT JOIN answers ans ON ans.id = il.answer_id
SET il.answer_id = NULL
WHERE il.answer_id IS NOT NULL
  AND ans.id IS NULL;

ALTER TABLE injection_logs
  ADD INDEX idx_injection_logs_answer (answer_id),
  ADD CONSTRAINT fk_inj_answer FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE SET NULL;
