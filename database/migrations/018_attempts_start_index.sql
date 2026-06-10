-- Índice para serializar início concorrente de tentativas via FOR UPDATE + gap lock
ALTER TABLE attempts
  ADD INDEX idx_attempts_student_exercise_turma (student_id, exercise_id, turma_id);
