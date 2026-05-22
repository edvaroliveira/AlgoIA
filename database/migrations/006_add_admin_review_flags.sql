ALTER TABLE exercises
  ADD COLUMN admin_review_status ENUM('approved', 'flagged', 'blocked') NOT NULL DEFAULT 'approved' AFTER status,
  ADD COLUMN admin_review_note TEXT NULL AFTER admin_review_status,
  ADD COLUMN admin_reviewed_at DATETIME NULL AFTER admin_review_note,
  ADD COLUMN admin_reviewed_by INT NULL AFTER admin_reviewed_at;

ALTER TABLE questions
  ADD COLUMN admin_review_status ENUM('approved', 'flagged', 'blocked') NOT NULL DEFAULT 'approved' AFTER order_index,
  ADD COLUMN admin_review_note TEXT NULL AFTER admin_review_status,
  ADD COLUMN admin_reviewed_at DATETIME NULL AFTER admin_review_note,
  ADD COLUMN admin_reviewed_by INT NULL AFTER admin_reviewed_at;
