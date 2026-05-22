ALTER TABLE users
  ADD COLUMN registration_source ENUM('manual','student_public','teacher_public') NOT NULL DEFAULT 'manual' AFTER registration_note;

UPDATE users
SET registration_source = 'teacher_public'
WHERE role = 'teacher'
  AND registration_note IS NOT NULL
  AND registration_note <> '';

UPDATE users
SET registration_source = 'student_public'
WHERE role = 'student'
  AND status = 'pending'
  AND registration_source = 'manual';
