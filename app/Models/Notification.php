<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Avisos dentro do app. Com dedupe_key, o mesmo aviso não se repete para o
 * mesmo usuário (UNIQUE user_id + dedupe_key). Falhas ao avisar nunca podem
 * derrubar a ação principal: quem chama usa notifySafely().
 */
class Notification extends Model
{
  public const TYPE_EXERCISE_PUBLISHED   = 'exercise_published';
  public const TYPE_DEADLINE             = 'deadline';
  public const TYPE_ATTEMPT_GRADED       = 'attempt_graded';
  public const TYPE_ENROLLMENT_APPROVED  = 'enrollment_approved';
  public const TYPE_REVIEW_REQUESTED     = 'review_requested';
  public const TYPE_REVIEW_ANSWERED      = 'review_answered';
  public const TYPE_ENROLLMENT_REQUESTED = 'enrollment_requested';
  public const TYPE_TEACHER_REGISTRATION = 'teacher_registration';
  public const TYPE_TURMA_POST           = 'turma_post';

  protected string $table = 'notifications';

  public function notify(int $userId, string $type, string $title, ?string $link = null, ?string $body = null, ?string $dedupeKey = null): void
  {
    $this->db->execute(
      "INSERT IGNORE INTO notifications (user_id, type, title, body, link, dedupe_key) VALUES (?, ?, ?, ?, ?, ?)",
      [$userId, $type, mb_substr($title, 0, 200), $body !== null ? mb_substr($body, 0, 500) : null, $link, $dedupeKey]
    );
  }

  /** Avisa sem propagar erro (o aviso é secundário à ação que o gerou). */
  public function notifySafely(int $userId, string $type, string $title, ?string $link = null, ?string $body = null, ?string $dedupeKey = null): void
  {
    try {
      $this->notify($userId, $type, $title, $link, $body, $dedupeKey);
    } catch (\Throwable $e) {
      error_log("notification failed ({$type} → {$userId}): " . $e->getMessage());
    }
  }

  /** Avisa todos os Alunos com Matrícula ativa nas Turmas informadas. */
  public function notifyTurmaStudents(array $turmaIds, string $type, string $title, ?string $link = null, ?string $body = null, ?string $dedupePrefix = null): void
  {
    $turmaIds = array_values(array_filter(array_map('intval', $turmaIds), static fn(int $id): bool => $id > 0));
    if ($turmaIds === []) {
      return;
    }

    try {
      $placeholders = implode(',', array_fill(0, count($turmaIds), '?'));
      $students = $this->db->fetchAll(
        "SELECT DISTINCT st.student_id
               FROM student_turma st
               JOIN users u ON u.id = st.student_id AND u.status = 'active'
               WHERE st.turma_id IN ({$placeholders}) AND st.status = 'active'",
        $turmaIds
      );
      foreach ($students as $student) {
        $this->notify((int) $student['student_id'], $type, $title, $link, $body, $dedupePrefix);
      }
    } catch (\Throwable $e) {
      error_log("notification failed ({$type} → turmas): " . $e->getMessage());
    }
  }

  /**
   * Lembra o Aluno das Publicações que encerram nas próximas 24 h e que ele
   * ainda não enviou. Um lembrete por Publicação (dedupe). Sem Aluno (null),
   * gera para todos os Alunos ativos (worker de e-mail).
   */
  public function generateDeadlineReminders(?int $studentId): void
  {
    $studentFilter = $studentId !== null ? 'AND st.student_id = ?' : '';
    $due = $this->db->fetchAll(
      "SELECT st.student_id, et.exercise_id, et.turma_id, et.closes_at, e.title
             FROM exercise_turmas et
             JOIN exercises e ON e.id = et.exercise_id AND e.status = 'active' AND e.admin_review_status <> 'blocked'
             JOIN turmas t ON t.id = et.turma_id AND t.active = 1
             JOIN student_turma st ON st.turma_id = et.turma_id {$studentFilter} AND st.status = 'active'
             JOIN users u ON u.id = st.student_id AND u.status = 'active'
             WHERE et.opens_at <= NOW()
               AND et.closes_at > NOW()
               AND NOT EXISTS (
                 SELECT 1 FROM questions blocked_q
                 WHERE blocked_q.exercise_id = e.id AND blocked_q.admin_review_status = 'blocked'
               )
               AND et.closes_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
               AND NOT EXISTS (
                 SELECT 1 FROM attempts a
                 WHERE a.student_id = st.student_id AND a.exercise_id = et.exercise_id
                   AND a.turma_id = et.turma_id AND a.status IN ('submitted', 'graded')
               )",
      $studentId !== null ? [$studentId] : []
    );

    foreach ($due as $publication) {
      $this->notify(
        (int) $publication['student_id'],
        self::TYPE_DEADLINE,
        'Prazo encerrando: ' . $publication['title'],
        '/student/exercises/' . (int) $publication['exercise_id'],
        'Encerra em ' . date('d/m/Y \à\s H:i', strtotime((string) $publication['closes_at'])) . '.',
        'deadline:' . (int) $publication['exercise_id'] . ':' . (int) $publication['turma_id']
      );
    }
  }

  public function unreadCount(int $userId): int
  {
    $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL", [$userId]);
    return (int) ($row['c'] ?? 0);
  }

  public function latest(int $userId, int $limit = 50): array
  {
    $safeLimit = max(1, $limit);
    return $this->db->fetchAll(
      "SELECT id, type, title, body, link, read_at, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT {$safeLimit}",
      [$userId]
    );
  }

  public function markAllRead(int $userId): void
  {
    $this->db->execute("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", [$userId]);
  }
}
