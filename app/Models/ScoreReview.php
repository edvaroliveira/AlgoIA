<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Revisão de nota: o Docente ajusta a Pontuação de uma Resposta corrigida
 * (com justificativa) e o Aluno pode pedir revisão, uma vez por Resposta.
 * Estados: requested (pedido do Aluno), adjusted (nota alterada), kept (nota mantida).
 */
class ScoreReview extends Model
{
  public const STATUS_REQUESTED = 'requested';
  public const STATUS_ADJUSTED  = 'adjusted';
  public const STATUS_KEPT      = 'kept';

  protected string $table = 'score_reviews';

  /** Resposta corrigida com o contexto de dono e nota máxima (null se não existir ou não estiver corrigida). */
  public function findGradedAnswer(int $answerId): array|false
  {
    return $this->db->fetchOne(
      "SELECT ans.id, ans.attempt_id, ans.ai_score, q.max_score, att.student_id, e.teacher_id
             FROM answers ans
             JOIN attempts att ON att.id = ans.attempt_id AND att.status = 'graded'
             JOIN questions q ON q.id = ans.question_id
             JOIN exercises e ON e.id = att.exercise_id
             WHERE ans.id = ?",
      [$answerId]
    );
  }

  /**
   * Docente altera a Pontuação. Se houver pedido do Aluno em aberto, ele é
   * resolvido como 'adjusted'; senão, registra um ajuste por iniciativa do Docente.
   *
   * @throws \RuntimeException com mensagem para o Docente.
   */
  public function adjust(int $answerId, int $teacherId, float $newScore, string $justification): void
  {
    $justification = trim($justification);
    if ($justification === '') {
      throw new \RuntimeException('Informe a justificativa da revisão: ela é mostrada ao Aluno.');
    }

    $this->db->beginTransaction();
    try {
      $answer = $this->db->fetchOne(
        "SELECT ans.id, ans.attempt_id, ans.ai_score, q.max_score
               FROM answers ans
               JOIN attempts att ON att.id = ans.attempt_id AND att.status = 'graded'
               JOIN questions q ON q.id = ans.question_id
               JOIN exercises e ON e.id = att.exercise_id AND e.teacher_id = ?
               WHERE ans.id = ?
               FOR UPDATE",
        [$teacherId, $answerId]
      );
      if (!$answer) {
        throw new \RuntimeException('Resposta corrigida não encontrada.');
      }
      if ($newScore < 0 || $newScore > (float) $answer['max_score']) {
        throw new \RuntimeException('A nova Pontuação deve ficar entre 0 e ' . number_format((float) $answer['max_score'], 1, ',', '') . '.');
      }

      $newScore = round($newScore, 1);
      $previous = $answer['ai_score'];

      $this->db->execute("UPDATE answers SET ai_score = ? WHERE id = ?", [$newScore, $answerId]);
      $this->db->execute(
        "UPDATE attempts
               SET total_score = (SELECT COALESCE(SUM(a.ai_score), 0) FROM answers a WHERE a.attempt_id = ?)
               WHERE id = ?",
        [(int) $answer['attempt_id'], (int) $answer['attempt_id']]
      );

      $resolved = $this->db->execute(
        "UPDATE score_reviews
               SET status = ?, previous_score = ?, new_score = ?, justification = ?, reviewed_by = ?, reviewed_at = NOW()
               WHERE answer_id = ? AND status = ?",
        [self::STATUS_ADJUSTED, $previous, $newScore, $justification, $teacherId, $answerId, self::STATUS_REQUESTED]
      );
      if ($resolved === 0) {
        $this->db->insert(
          "INSERT INTO score_reviews (answer_id, status, previous_score, new_score, justification, reviewed_by, reviewed_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
          [$answerId, self::STATUS_ADJUSTED, $previous, $newScore, $justification, $teacherId]
        );
      }

      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }
      throw $e;
    }
  }

  /**
   * Aluno pede revisão de uma Resposta própria que não tirou a Pontuação máxima.
   * Um pedido por Resposta.
   *
   * @throws \RuntimeException com mensagem para o Aluno.
   */
  public function request(int $answerId, int $studentId, string $message): void
  {
    $message = mb_substr(trim($message), 0, 1000);
    if ($message === '') {
      throw new \RuntimeException('Explique em poucas palavras por que a nota deve ser revista.');
    }

    $this->db->beginTransaction();
    try {
      $answer = $this->db->fetchOne(
        "SELECT ans.id, ans.ai_score, q.max_score
               FROM answers ans
               JOIN attempts att ON att.id = ans.attempt_id AND att.status = 'graded' AND att.student_id = ?
               JOIN questions q ON q.id = ans.question_id
               WHERE ans.id = ?
               FOR UPDATE",
        [$studentId, $answerId]
      );
      if (!$answer) {
        throw new \RuntimeException('Resposta corrigida não encontrada.');
      }
      if ((float) $answer['ai_score'] >= (float) $answer['max_score']) {
        throw new \RuntimeException('Esta questão já está com a Pontuação máxima.');
      }
      $existing = $this->db->fetchOne(
        "SELECT id FROM score_reviews WHERE answer_id = ? AND requested_by IS NOT NULL LIMIT 1",
        [$answerId]
      );
      if ($existing) {
        throw new \RuntimeException('Você já pediu revisão desta questão.');
      }

      $this->db->insert(
        "INSERT INTO score_reviews (answer_id, status, request_message, requested_by, requested_at)
               VALUES (?, ?, ?, ?, NOW())",
        [$answerId, self::STATUS_REQUESTED, $message, $studentId]
      );

      $this->db->commit();
    } catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollback();
      }
      throw $e;
    }
  }

  /** Docente responde a um pedido mantendo a nota. */
  public function keep(int $reviewId, int $teacherId, string $justification): bool
  {
    $justification = trim($justification);
    if ($justification === '') {
      throw new \RuntimeException('Informe a justificativa: ela é mostrada ao Aluno.');
    }

    return $this->db->execute(
      "UPDATE score_reviews sr
             JOIN answers ans ON ans.id = sr.answer_id
             JOIN attempts att ON att.id = ans.attempt_id
             JOIN exercises e ON e.id = att.exercise_id AND e.teacher_id = ?
             SET sr.status = ?, sr.justification = ?, sr.reviewed_by = ?, sr.reviewed_at = NOW()
             WHERE sr.id = ? AND sr.status = ?",
      [$teacherId, self::STATUS_KEPT, $justification, $teacherId, $reviewId, self::STATUS_REQUESTED]
    ) > 0;
  }

  /** Revisões da Tentativa agrupadas por Resposta, da mais antiga para a mais recente. */
  public function byAnswerForAttempt(int $attemptId): array
  {
    $rows = $this->db->fetchAll(
      "SELECT sr.*
             FROM score_reviews sr
             JOIN answers ans ON ans.id = sr.answer_id
             WHERE ans.attempt_id = ?
             ORDER BY sr.id",
      [$attemptId]
    );

    $map = [];
    foreach ($rows as $row) {
      $map[(int) $row['answer_id']][] = $row;
    }
    return $map;
  }

  /** Pedidos em aberto dos Alunos do Docente, para o painel. */
  public function openForTeacher(int $teacherId, int $limit = 10): array
  {
    $safeLimit = max(1, $limit);

    return $this->db->fetchAll(
      "SELECT sr.id, sr.request_message, sr.requested_at, att.id AS attempt_id, q.order_index,
              e.title AS exercise_title, student.name AS student_name
             FROM score_reviews sr
             JOIN answers ans ON ans.id = sr.answer_id
             JOIN attempts att ON att.id = ans.attempt_id
             JOIN questions q ON q.id = ans.question_id
             JOIN exercises e ON e.id = att.exercise_id
             JOIN users student ON student.id = att.student_id
             WHERE e.teacher_id = ? AND sr.status = ?
             ORDER BY sr.requested_at
             LIMIT {$safeLimit}",
      [$teacherId, self::STATUS_REQUESTED]
    );
  }

  public function countOpenForTeacher(int $teacherId): int
  {
    $row = $this->db->fetchOne(
      "SELECT COUNT(*) AS c
             FROM score_reviews sr
             JOIN answers ans ON ans.id = sr.answer_id
             JOIN attempts att ON att.id = ans.attempt_id
             JOIN exercises e ON e.id = att.exercise_id
             WHERE e.teacher_id = ? AND sr.status = ?",
      [$teacherId, self::STATUS_REQUESTED]
    );
    return (int) ($row['c'] ?? 0);
  }
}
