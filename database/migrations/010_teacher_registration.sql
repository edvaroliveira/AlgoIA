-- Adiciona status 'rejected' ao enum de users e colunas de aprovacao
ALTER TABLE users
  MODIFY COLUMN status ENUM('pending','active','inactive','rejected') NOT NULL DEFAULT 'pending',
  ADD COLUMN registration_note TEXT NULL AFTER status,
  ADD COLUMN approved_by INT UNSIGNED NULL AFTER registration_note,
  ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
  ADD COLUMN rejected_at DATETIME NULL AFTER approved_at,
  ADD CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Configuracoes do sistema
CREATE TABLE system_settings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key   VARCHAR(100) NOT NULL,
  setting_value TEXT NOT NULL DEFAULT '',
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_setting_key (setting_key),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value) VALUES ('teacher_registration_enabled', '0');
