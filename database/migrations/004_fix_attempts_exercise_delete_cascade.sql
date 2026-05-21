ALTER TABLE attempts
  DROP FOREIGN KEY fk_att_exercise;

ALTER TABLE attempts
  ADD CONSTRAINT fk_att_exercise
  FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE;
