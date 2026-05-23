ALTER TABLE users
  ADD COLUMN registration_source ENUM('manual','student_public','teacher_public') NOT NULL DEFAULT 'manual' AFTER registration_note;

UPDATE users
SET registration_source = 'manual';

UPDATE users u
JOIN audit_logs al ON al.entity_type = 'user'
  AND al.entity_id = u.id
  AND al.action = 'auth.teacher_registration_request'
SET u.registration_source = 'teacher_public'
WHERE u.role = 'teacher';

UPDATE users
SET registration_source = 'student_public'
WHERE role = 'student'
  AND status = 'pending'
  AND registration_source = 'manual';
