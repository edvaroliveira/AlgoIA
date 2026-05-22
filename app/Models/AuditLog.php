<?php

declare(strict_types=1);

namespace App\Models;

class AuditLog extends Model
{
  protected string $table = 'audit_logs';

  public function getAllForAdmin(array $filters = [], ?int $limit = null, ?int $offset = null): array
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);
    $limitSql = $limit !== null ? ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, (int) $offset) : ' LIMIT 200';

    return $this->db->fetchAll(
      "SELECT al.*, actor.name AS actor_name, actor.email AS actor_email
             FROM audit_logs al
             LEFT JOIN users actor ON actor.id = al.actor_user_id
             {$where}
             ORDER BY al.created_at DESC{$limitSql}",
      $params
    );
  }

  public function countForAdmin(array $filters = []): int
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM audit_logs al
             LEFT JOIN users actor ON actor.id = al.actor_user_id
             {$where}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }

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

  private function buildAdminFilters(array $filters): array
  {
    $conditions = [];
    $params = [];

    $action = trim((string) ($filters['action'] ?? ''));
    if ($action !== '') {
      $conditions[] = 'al.action LIKE ?';
      $params[] = '%' . $action . '%';
    }

    $entityType = trim((string) ($filters['entity_type'] ?? ''));
    if ($entityType !== '') {
      $conditions[] = 'al.entity_type = ?';
      $params[] = $entityType;
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
      $conditions[] = '(actor.name LIKE ? OR actor.email LIKE ? OR al.action LIKE ? OR al.entity_type LIKE ?)';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
    }

    return [
      'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
      'params' => $params,
    ];
  }
}
