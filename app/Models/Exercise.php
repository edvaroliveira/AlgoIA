<?php

declare(strict_types=1);

namespace App\Models;

class Exercise extends Model
{
  protected string $table = 'exercises';

  public function createDraft(
    int     $teacherId,
    string  $title,
    ?string $description
  ): int {
    return $this->db->insert(
      "INSERT INTO exercises (teacher_id, title, description, status)
             VALUES (?, ?, ?, 'draft')",
      [$teacherId, $title, $description]
    );
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
      "UPDATE exercises SET status = 'ready' WHERE id = ?",
      [$id]
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
        "UPDATE exercises SET turma_id = ?, status = 'active' WHERE id = ?",
        [$primaryTurmaId, $id]
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
    if (($exercise['status'] ?? 'active') !== 'active') {
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
    if (($exercise['status'] ?? 'active') !== 'active') {
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
