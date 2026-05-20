<?php

declare(strict_types=1);

namespace App\Models;

class AuditLog extends Model
{
  protected string $table = 'audit_logs';

  public function create(
    ?int   $actorUserId,
    string $actorRole,
    string $action,
    string $entityType,
    ?int   $entityId,
    array  $metadata,
    ?string $ipAddress,
    ?string $userAgent
  ): int {
    return $this->db->insert(
      'INSERT INTO audit_logs (actor_user_id, actor_role, action, entity_type, entity_id, metadata_json, ip_address, user_agent)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $actorUserId,
        $actorRole,
        $action,
        $entityType,
        $entityId,
        json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $ipAddress,
        $userAgent !== null ? substr($userAgent, 0, 255) : null,
      ]
    );
  }
}
