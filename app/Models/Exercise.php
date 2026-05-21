<?php

declare(strict_types=1);

namespace App\Models;

class Exercise extends Model
{
  protected string $table = 'exercises';

  public function createDraft(
    int     $teacherId,
    string  $title,
    ?string $description,
    string  $opensAt,
    string  $closesAt,
    int     $maxAttempts
  ): int {
    return $this->db->insert(
      "INSERT INTO exercises (teacher_id, title, description, opens_at, closes_at, max_attempts, status)
             VALUES (?, ?, ?, ?, ?, ?, 'draft')",
      [$teacherId, $title, $description, $opensAt, $closesAt, $maxAttempts]
    );
  }

  public function findByTeacher(int $teacherId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    COALESCE(NULLIF(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', '), ''), 'Pendente de finalização') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count
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
                    COUNT(DISTINCT et.turma_id) AS turma_count
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
    return $exercise;
  }

  public function findAvailableForStudent(int $studentId): array
  {
    $now = date('Y-m-d H:i:s');
    return $this->db->fetchAll(
      "SELECT e.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN turmas t ON t.id = et.turma_id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.status = 'active'
               AND st.student_id = ? AND st.status = 'active'
               AND e.opens_at <= ? AND e.closes_at >= ?
             GROUP BY e.id
             ORDER BY e.closes_at ASC",
      [$studentId, $now, $now]
    );
  }

  public function findAllForStudent(int $studentId): array
  {
    return $this->db->fetchAll(
      "SELECT e.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS turma_label,
                    MIN(t.name) AS turma_name,
                    COUNT(DISTINCT et.turma_id) AS turma_count
             FROM exercises e
             JOIN exercise_turmas et ON et.exercise_id = e.id
             JOIN turmas t ON t.id = et.turma_id
             JOIN student_turma st ON st.turma_id = et.turma_id
             WHERE e.status = 'active'
               AND st.student_id = ? AND st.status = 'active'
             GROUP BY e.id
             ORDER BY e.closes_at DESC",
      [$studentId]
    );
  }

  public function updateDraft(
    int     $id,
    string  $title,
    ?string $description,
    string  $opensAt,
    string  $closesAt,
    int     $maxAttempts
  ): void {
    $this->db->execute(
      "UPDATE exercises
             SET title = ?, description = ?, opens_at = ?, closes_at = ?, max_attempts = ?
             WHERE id = ?",
      [$title, $description, $opensAt, $closesAt, $maxAttempts, $id]
    );
  }

  public function activate(int $id, array $turmaIds): void
  {
    $turmaIds = array_values(array_unique(array_map('intval', $turmaIds)));
    $primaryTurmaId = $turmaIds[0] ?? null;

    $this->db->beginTransaction();

    try {
      $this->db->execute("DELETE FROM exercise_turmas WHERE exercise_id = ?", [$id]);

      foreach ($turmaIds as $turmaId) {
        $this->db->insert(
          "INSERT INTO exercise_turmas (exercise_id, turma_id) VALUES (?, ?)",
          [$id, $turmaId]
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

    $now = time();
    return strtotime($exercise['opens_at']) <= $now
      && strtotime($exercise['closes_at']) >= $now;
  }

  public function isClosed(array $exercise): bool
  {
    return strtotime($exercise['closes_at']) < time();
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
