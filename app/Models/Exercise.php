<?php

declare(strict_types=1);

namespace App\Models;

class Exercise extends Model
{
  public const STATUS_DRAFT = 'draft';
  public const STATUS_READY = 'ready';
  public const STATUS_ACTIVE = 'active';
  public const REVIEW_APPROVED = 'approved';
  public const REVIEW_FLAGGED = 'flagged';
  public const REVIEW_BLOCKED = 'blocked';

  protected string $table = 'exercises';

  public function delete(int $id): int
  {
    $this->db->beginTransaction();

    try {
      $this->db->execute("DELETE FROM attempts WHERE exercise_id = ?", [$id]);
      $deleted = $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
      $this->db->commit();

      return $deleted;
    } catch (\Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  public function createDraft(
    int     $teacherId,
    string  $title,
    ?string $description
  ): int {
    return $this->db->insert(
      "INSERT INTO exercises (teacher_id, title, description, status)
             VALUES (?, ?, ?, ?)",
      [$teacherId, $title, $description, self::STATUS_DRAFT]
    );
  }

  public function isDraft(array $exercise): bool
  {
    return ($exercise['status'] ?? self::STATUS_DRAFT) === self::STATUS_DRAFT;
  }

  public function isReady(array $exercise): bool
  {
    return ($exercise['status'] ?? self::STATUS_DRAFT) === self::STATUS_READY;
  }

  public function isActive(array $exercise): bool
  {
    return ($exercise['status'] ?? self::STATUS_DRAFT) === self::STATUS_ACTIVE;
  }

  public function canEdit(array $exercise): bool
  {
    return $this->isDraft($exercise);
  }

  public function canComplete(array $exercise): bool
  {
    return $this->isDraft($exercise);
  }

  public function canPublish(array $exercise): bool
  {
    return $this->isReady($exercise) && !$this->isBlockedForReview($exercise);
  }

  public function isBlockedForReview(array $exercise): bool
  {
    return (string) ($exercise['admin_review_status'] ?? self::REVIEW_APPROVED) === self::REVIEW_BLOCKED;
  }

  public function findByTeacher(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts
             FROM exercises e
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             WHERE e.teacher_id = ?
             GROUP BY e.id
             ORDER BY e.created_at DESC",
      [$teacherId]
    );
  }

  public function getAllForAdmin(array $filters = [], ?int $limit = null, ?int $offset = null): array
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);
    $limitSql = $limit !== null ? ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, (int) $offset) : '';

    return $this->db->fetchAll(
      "SELECT e.*,
                    teacher.name AS teacher_name,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts,
                    COUNT(DISTINCT a.id) AS attempt_count
             FROM exercises e
             JOIN users teacher ON teacher.id = e.teacher_id
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             LEFT JOIN attempts a ON a.exercise_id = e.id
             {$where}
             GROUP BY e.id
             ORDER BY e.created_at DESC{$limitSql}",
      $params
    );
  }

  public function countForAdmin(array $filters = []): int
  {
    ['where' => $where, 'params' => $params] = $this->buildAdminFilters($filters);

    $row = $this->db->fetchOne(
      "SELECT COUNT(DISTINCT e.id) AS total
             FROM exercises e
             JOIN users teacher ON teacher.id = e.teacher_id
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             {$where}",
      $params
    );

    return (int) ($row['total'] ?? 0);
  }

  public function countClosingSoonForAdmin(int $hoursAhead = 72): int
  {
    $safeHoursAhead = max(1, $hoursAhead);

    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS total
             FROM (
               SELECT e.id
               FROM exercises e
               JOIN exercise_turmas et ON et.exercise_id = e.id
               WHERE e.status = ?
                AND COALESCE(e.admin_review_status, 'approved') <> ?
                 AND et.closes_at >= NOW()
                 AND et.closes_at <= DATE_ADD(NOW(), INTERVAL {$safeHoursAhead} HOUR)
               GROUP BY e.id
             ) AS closing_exercises",
      [self::STATUS_ACTIVE, self::REVIEW_BLOCKED]
    );

    return (int) ($row['total'] ?? 0);
  }

  public function getClosingSoonForAdmin(int $limit = 5, int $hoursAhead = 72): array
  {
    $safeLimit = max(1, $limit);
    $safeHoursAhead = max(1, $hoursAhead);

    return $this->db->fetchAll(
      "SELECT e.id, e.title, teacher.name AS teacher_name,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    MIN(et.closes_at) AS closes_at,
                    COUNT(DISTINCT a.id) AS attempt_count
             FROM exercises e
             JOIN users teacher ON teacher.id = e.teacher_id
             JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             LEFT JOIN attempts a ON a.exercise_id = e.id
             WHERE e.status = ?
               AND COALESCE(e.admin_review_status, 'approved') <> ?
               AND et.closes_at >= NOW()
               AND et.closes_at <= DATE_ADD(NOW(), INTERVAL {$safeHoursAhead} HOUR)
             GROUP BY e.id
             ORDER BY closes_at ASC, e.title ASC
             LIMIT {$safeLimit}",
      [self::STATUS_ACTIVE, self::REVIEW_BLOCKED]
    );
  }

  public function updateAdminReview(int $id, string $status, ?string $note, ?int $reviewedBy): void
  {
    $this->db->execute(
      "UPDATE exercises
             SET admin_review_status = ?, admin_review_note = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
             WHERE id = ?",
      [$status, $note, $reviewedBy, $id]
    );
  }

  public function findForAdmin(int $id): array|false
  {
    $exercise = $this->db->fetchOne(
      "SELECT e.*, teacher.name AS teacher_name, teacher.email AS teacher_email,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    GROUP_CONCAT(DISTINCT t.access_key ORDER BY t.name SEPARATOR ', ') AS turma_keys,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts,
                    COUNT(DISTINCT a.id) AS attempt_count
             FROM exercises e
             JOIN users teacher ON teacher.id = e.teacher_id
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             LEFT JOIN attempts a ON a.exercise_id = e.id
             WHERE e.id = ?
             GROUP BY e.id",
      [$id]
    );

    if ($exercise === false) {
      return false;
    }

    $exercise['assigned_turma_ids'] = $this->getAssignedTurmaIds($id);
    $exercise['publication_settings'] = $this->getPublicationSettings($id);
    return $exercise;
  }

  public function closePublications(int $exerciseId): void
  {
    $this->db->execute(
      "UPDATE exercise_turmas
             SET closes_at = CASE WHEN closes_at > NOW() THEN NOW() ELSE closes_at END
             WHERE exercise_id = ?",
      [$exerciseId]
    );
  }

  public function closePublication(int $exerciseId, int $turmaId): void
  {
    $this->db->execute(
      "UPDATE exercise_turmas
             SET closes_at = CASE WHEN closes_at > NOW() THEN NOW() ELSE closes_at END
             WHERE exercise_id = ? AND turma_id = ?",
      [$exerciseId, $turmaId]
    );
  }

  public function reopenPublications(int $exerciseId, string $newClosesAt): void
  {
    $this->db->execute(
      "UPDATE exercise_turmas
             SET closes_at = ?
             WHERE exercise_id = ?",
      [$newClosesAt, $exerciseId]
    );
  }

  public function reopenPublication(int $exerciseId, int $turmaId, string $newClosesAt): void
  {
    $this->db->execute(
      "UPDATE exercise_turmas
             SET closes_at = ?
             WHERE exercise_id = ? AND turma_id = ?",
      [$newClosesAt, $exerciseId, $turmaId]
    );
  }

  public function updatePublication(int $exerciseId, int $turmaId, string $opensAt, string $closesAt, int $maxAttempts): void
  {
    $this->db->execute(
      "UPDATE exercise_turmas
             SET opens_at = ?, closes_at = ?, max_attempts = ?
             WHERE exercise_id = ? AND turma_id = ?",
      [$opensAt, $closesAt, $maxAttempts, $exerciseId, $turmaId]
    );
  }

  private function buildAdminFilters(array $filters): array
  {
    $conditions = [];
    $params = [];

    $status = (string) ($filters['status'] ?? '');
    if (in_array($status, [self::STATUS_DRAFT, self::STATUS_READY, self::STATUS_ACTIVE], true)) {
      $conditions[] = 'e.status = ?';
      $params[] = $status;
    }

    $timing = (string) ($filters['timing'] ?? '');
    if ($timing === 'closing_soon') {
      $conditions[] = "e.status = 'active' AND et.closes_at >= NOW() AND et.closes_at <= DATE_ADD(NOW(), INTERVAL 72 HOUR)";
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
      $conditions[] = '(e.title LIKE ? OR teacher.name LIKE ? OR t.name LIKE ?)';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
      $params[] = '%' . $search . '%';
    }

    return [
      'where' => $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '',
      'params' => $params,
    ];
  }

  public function getWithTurma(int $id): array|false
  {
    $exercise = $this->db->fetchOne(
      "SELECT e.*,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    MIN(t.name) AS turma_name,
                    GROUP_CONCAT(DISTINCT t.access_key ORDER BY t.name SEPARATOR ', ') AS turma_keys,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts
             FROM exercises e
             LEFT JOIN exercise_turmas et ON et.exercise_id = e.id
             LEFT JOIN turmas t ON t.id = et.turma_id
             WHERE e.id = ?
             GROUP BY e.id",
      [$id]
    );

    if ($exercise === false) {
      return false;
    }

    $exercise['assigned_turma_ids'] = $this->getAssignedTurmaIds($id);
    $exercise['publication_settings'] = $this->getPublicationSettings($id);
    return $exercise;
  }

  public function findAvailableForStudent(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts,
                    MAX(CASE WHEN et.opens_at <= NOW() AND et.closes_at >= NOW() THEN 1 ELSE 0 END) AS has_open_publication,
                    MAX(CASE WHEN et.opens_at > NOW() THEN 1 ELSE 0 END) AS has_future_publication
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN turmas t ON t.id = et.turma_id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.status = 'active'
               AND COALESCE(e.admin_review_status, 'approved') <> 'blocked'
               AND st.student_id = ? AND st.status = 'active'
               AND et.opens_at <= NOW() AND et.closes_at >= NOW()
             GROUP BY e.id
             ORDER BY MIN(et.closes_at) ASC",
      [$studentId]
    );
  }

  public function findAllForStudent(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts,
                    MAX(CASE WHEN et.opens_at <= NOW() AND et.closes_at >= NOW() THEN 1 ELSE 0 END) AS has_open_publication,
                    MAX(CASE WHEN et.opens_at > NOW() THEN 1 ELSE 0 END) AS has_future_publication
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN turmas t ON t.id = et.turma_id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.status = 'active'
               AND COALESCE(e.admin_review_status, 'approved') <> 'blocked'
               AND st.student_id = ? AND st.status = 'active'
             GROUP BY e.id
             ORDER BY MAX(et.closes_at) DESC",
      [$studentId]
    );
  }

  public function findForStudent(int $exerciseId, int $studentId): array|false
  {
    return $this->db->fetchOne(
      "SELECT e.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count,
                    MIN(et.opens_at) AS opens_at,
                    MAX(et.closes_at) AS closes_at,
                    MAX(et.max_attempts) AS max_attempts,
                    MAX(CASE WHEN et.opens_at <= NOW() AND et.closes_at >= NOW() THEN 1 ELSE 0 END) AS has_open_publication,
                    MAX(CASE WHEN et.opens_at > NOW() THEN 1 ELSE 0 END) AS has_future_publication
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN turmas t ON t.id = et.turma_id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.id = ?
               AND e.status = 'active'
               AND COALESCE(e.admin_review_status, 'approved') <> 'blocked'
               AND st.student_id = ?
               AND st.status = 'active'
             GROUP BY e.id",
      [$exerciseId, $studentId]
    );
  }

  public function updateDraft(
    int     $id,
    string  $title,
    ?string $description
  ): void {
    $this->db->execute(
      "UPDATE exercises
             SET title = ?, description = ?
             WHERE id = ?",
      [$title, $description, $id]
    );
  }

  public function markReady(int $id): void
  {
    $this->db->execute(
      "UPDATE exercises SET status = ? WHERE id = ?",
      [self::STATUS_READY, $id]
    );
  }

  public function activate(int $id, array $publicationConfigs): void
  {
    $turmaIds = array_values(array_map('intval', array_keys($publicationConfigs)));
    $primaryTurmaId = $turmaIds[0] ?? null;

    $this->db->beginTransaction();

    try {
      $this->db->execute("DELETE FROM exercise_turmas WHERE exercise_id = ?", [$id]);

      foreach ($publicationConfigs as $turmaId => $config) {
        $this->db->insert(
          "INSERT INTO exercise_turmas (exercise_id, turma_id, opens_at, closes_at, max_attempts)
                 VALUES (?, ?, ?, ?, ?)",
          [$id, (int) $turmaId, $config['opens_at'], $config['closes_at'], (int) $config['max_attempts']]
        );
      }

      $this->db->execute(
        "UPDATE exercises SET turma_id = ?, status = ? WHERE id = ?",
        [$primaryTurmaId, self::STATUS_ACTIVE, $id]
      );

      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  public function getAssignedTurmaIds(int $exerciseId): array
  {
    $rows = $this->db->fetchAll(
      "SELECT turma_id FROM exercise_turmas WHERE exercise_id = ? ORDER BY turma_id",
      [$exerciseId]
    );

    return array_map(static fn(array $row): int => (int) $row['turma_id'], $rows);
  }

  public function getPublicationSettings(int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT et.turma_id, et.opens_at, et.closes_at, et.max_attempts, t.name AS turma_name, t.access_key
             FROM exercise_turmas et
             JOIN turmas t ON t.id = et.turma_id
             WHERE et.exercise_id = ?
             ORDER BY t.name",
      [$exerciseId]
    );
  }

  public function studentHasAccess(int $exerciseId, int $studentId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT e.id
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.id = ?
               AND e.status = 'active'
               AND COALESCE(e.admin_review_status, 'approved') <> 'blocked'
               AND st.student_id = ?
               AND st.status = 'active'
             LIMIT 1",
      [$exerciseId, $studentId]
    );

    return $row !== false;
  }

  public function belongsToTeacher(int $exerciseId, int $teacherId): bool
  {
    $row = $this->db->fetchOne(
      "SELECT id FROM exercises WHERE id = ? AND teacher_id = ?",
      [$exerciseId, $teacherId]
    );
    return $row !== false;
  }

  public function isOpen(array $exercise): bool
  {
    if (!$this->isActive($exercise)) {
      return false;
    }

    if (array_key_exists('has_open_publication', $exercise)) {
      return (int) $exercise['has_open_publication'] === 1;
    }

    $now = time();
    return !empty($exercise['opens_at'])
      && !empty($exercise['closes_at'])
      && strtotime($exercise['opens_at']) <= $now
      && strtotime($exercise['closes_at']) >= $now;
  }

  public function isClosed(array $exercise): bool
  {
    if (!$this->isActive($exercise)) {
      return false;
    }

    if (array_key_exists('has_open_publication', $exercise) && array_key_exists('has_future_publication', $exercise)) {
      return (int) $exercise['has_open_publication'] !== 1
        && (int) $exercise['has_future_publication'] !== 1;
    }

    return !empty($exercise['closes_at']) && strtotime($exercise['closes_at']) < time();
  }

  public function getResultsForTeacher(int $exerciseId): array
  {
    return $this->db->fetchAll(
      "SELECT u.name, u.email,
                    MAX(a.total_score) AS best_score,
                    COUNT(a.id)        AS attempt_count
             FROM users u
             JOIN attempts a ON a.student_id = u.id
             WHERE a.exercise_id = ? AND a.status = 'graded'
             GROUP BY u.id
             ORDER BY best_score DESC",
      [$exerciseId]
    );
  }
}
