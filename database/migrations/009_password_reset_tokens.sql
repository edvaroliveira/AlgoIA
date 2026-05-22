-- IAProg - Migration 009: reset de senha por token com expiracao

ALTER TABLE users
  ADD COLUMN password_reset_token_hash VARCHAR(255) NULL AFTER password_reset_at,
  ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_token_hash,
  ADD INDEX idx_users_password_reset_expires (password_reset_expires_at);
