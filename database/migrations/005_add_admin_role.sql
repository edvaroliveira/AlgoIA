ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','teacher','student') NOT NULL DEFAULT 'student';
