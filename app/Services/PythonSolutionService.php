<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Core\Database;

/**
 * Resposta em Python de cada Questão: gerada uma vez pela IA (cron), revisada e
 * aprovada pelo Docente, e reaproveitada por todos os Alunos, Turmas, Banco e Prática.
 *
 * Ciclo (questions.python_solution_status):
 *   NULL → pending (Docente conclui o Exercício ou pede "Gerar") → draft (IA) → approved (Docente).
 *   Docente pode salvar o próprio código a qualquer momento (source = teacher).
 *   Resposta inválida da IA vira failed; só volta à fila quando o Docente pede de novo.
 */
final class PythonSolutionService
{
  public const SETTING_ENABLED = 'python_solution_ai_enabled';

  public const STATUS_PENDING  = 'pending';
  public const STATUS_DRAFT    = 'draft';
  public const STATUS_APPROVED = 'approved';
  public const STATUS_FAILED   = 'failed';

  /** Questões por execução do cron: limita custo e tempo. */
  public const PER_RUN = 20;

  private Database $db;
  private ?OpenAIService $ai;

  public function __construct(?Database $db = null, ?OpenAIService $ai = null)
  {
    $this->db = $db ?? Database::getInstance();
    $this->ai = $ai;
  }

  /** Geração pela IA ligada nas Configurações (padrão: ligada). */
  public function enabled(): bool
  {
    return (new SystemSetting($this->db))->getBool(self::SETTING_ENABLED, true);
  }

  /**
   * Põe na fila as Questões do Exercício que ainda não têm Resposta em Python
   * (ou cuja geração falhou). Questões bloqueadas pela Moderação ficam de fora.
   */
  public function queueExercise(int $exerciseId): int
  {
    return $this->db->execute(
      "UPDATE questions
             SET python_solution_status = 'pending', python_solution_updated_at = NOW()
             WHERE exercise_id = ?
               AND admin_review_status <> 'blocked'
               AND (python_solution_status IS NULL OR python_solution_status = 'failed')",
      [$exerciseId]
    );
  }

  /** "Gerar novamente": descarta o rascunho e volta para a fila. */
  public function requeue(int $questionId): void
  {
    $this->db->execute(
      "UPDATE questions
             SET python_solution_status = 'pending', python_solution_updated_at = NOW(), python_solution_reviewed_by = NULL
             WHERE id = ?",
      [$questionId]
    );
  }

  /** Código escrito ou editado pelo Docente: rascunho ou aprovado (visível ao Aluno). */
  public function save(int $questionId, string $code, bool $approve, int $teacherId): void
  {
    $this->db->execute(
      "UPDATE questions
             SET python_solution = ?, python_solution_status = ?, python_solution_source = 'teacher',
                 python_solution_updated_at = NOW(), python_solution_reviewed_by = ?
             WHERE id = ?",
      [OpenAIService::normalizePythonCode($code), $approve ? self::STATUS_APPROVED : self::STATUS_DRAFT, $approve ? $teacherId : null, $questionId]
    );
  }

  /** Aprova o código atual (da IA ou do Docente) sem alterá-lo. */
  public function approve(int $questionId, int $teacherId): bool
  {
    return $this->db->execute(
      "UPDATE questions
             SET python_solution_status = 'approved', python_solution_updated_at = NOW(), python_solution_reviewed_by = ?
             WHERE id = ? AND python_solution IS NOT NULL AND python_solution <> ''",
      [$teacherId, $questionId]
    ) > 0;
  }

  /**
   * Exercícios do Docente com Questões esperando revisão: rascunho da IA ainda
   * não aprovado, ou geração que falhou.
   *
   * @return list<array{id: int, title: string, total: int}>
   */
  public function awaitingReview(int $teacherId): array
  {
    $rows = $this->db->fetchAll(
      "SELECT e.id, e.title, COUNT(*) AS total
             FROM questions q
             JOIN exercises e ON e.id = q.exercise_id
             WHERE e.teacher_id = ?
               AND q.admin_review_status <> 'blocked'
               AND (q.python_solution_status = 'failed'
                    OR (q.python_solution_status = 'draft' AND q.python_solution_source = 'ai'))
             GROUP BY e.id, e.title
             ORDER BY e.title",
      [$teacherId]
    );

    return array_map(static fn(array $r): array => ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'total' => (int) $r['total']], $rows);
  }

  /**
   * Passo do cron: gera as Questões da fila. Copia do Banco quando a Questão veio
   * de uma Questão do banco com código aprovado (sem chamar a IA).
   *
   * @return array{enabled: bool, generated: int, copied: int, failed: int}
   */
  public function run(int $limit = self::PER_RUN): array
  {
    $result = ['enabled' => false, 'generated' => 0, 'copied' => 0, 'failed' => 0];
    $pending = $this->db->fetchAll(
      "SELECT q.id, q.text, q.expected_answer_hint, q.exercise_id, e.teacher_id,
              bq.python_solution AS bank_code, bq.python_solution_status AS bank_status, bq.python_solution_source AS bank_source
             FROM questions q
             JOIN exercises e ON e.id = q.exercise_id
             LEFT JOIN bank_questions bq ON bq.id = q.bank_question_id
             WHERE q.python_solution_status = 'pending'
             ORDER BY q.python_solution_updated_at, q.id
             LIMIT " . max(1, $limit)
    );

    $ai = null;
    $enabled = $this->enabled();
    $result['enabled'] = $enabled;
    foreach ($pending as $question) {
      if ($question['bank_status'] === self::STATUS_APPROVED && trim((string) $question['bank_code']) !== '') {
        $this->storeIfPending((int) $question['id'], (string) $question['bank_code'], self::STATUS_APPROVED, (string) ($question['bank_source'] ?: 'ai'));
        $result['copied']++;
        continue;
      }

      if (!$enabled) {
        continue; // IA desligada: espera na fila; cópia do Banco continua valendo
      }

      $ai ??= $this->ai ?? new OpenAIService($this->db);
      try {
        $generated = $ai->generatePythonSolution(
          (string) $question['text'],
          (string) $question['expected_answer_hint'],
          ['kind' => 'python', 'teacher_id' => (int) $question['teacher_id'], 'exercise_id' => (int) $question['exercise_id']]
        );
      } catch (\RuntimeException $e) {
        error_log('python solution failed: ' . $e->getMessage());
        $result['failed']++;
        if (str_contains($e->getMessage(), 'Resposta inválida')) {
          // A IA respondeu, mas sem código usável: não insiste sozinha (custaria a cada cron).
          $this->db->execute(
            "UPDATE questions SET python_solution_status = 'failed', python_solution_updated_at = NOW()
                   WHERE id = ? AND python_solution_status = 'pending'",
            [(int) $question['id']]
          );
          continue;
        }
        // Serviço fora do ar ou sem chave: fica na fila e para aqui até o próximo cron.
        break;
      }

      $this->storeIfPending((int) $question['id'], $generated['code'], self::STATUS_DRAFT, 'ai');
      $result['generated']++;
    }

    return $result;
  }

  /** Grava só se a Questão ainda estiver na fila: o Docente pode ter salvo o próprio código enquanto a IA respondia. */
  private function storeIfPending(int $questionId, string $code, string $status, string $source): void
  {
    $this->db->execute(
      "UPDATE questions
             SET python_solution = ?, python_solution_status = ?, python_solution_source = ?, python_solution_updated_at = NOW()
             WHERE id = ? AND python_solution_status = 'pending'",
      [$code, $status, $source, $questionId]
    );
  }
}
