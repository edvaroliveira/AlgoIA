-- IAProg – Migration 002: trilha de auditoria

CREATE TABLE IF NOT EXISTS audit_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NULL,
    actor_role    VARCHAR(20)  NOT NULL,
    action        VARCHAR(100) NOT NULL,
    entity_type   VARCHAR(60)  NOT NULL,
    entity_id     INT UNSIGNED NULL,
    metadata_json JSON         NULL,
    ip_address    VARCHAR(45)  NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor (actor_user_id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action (action),
    CONSTRAINT fk_audit_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
