<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Answer;
use App\Models\Attempt;
use Core\Database;

class AttemptGradingService
{
  private Attempt $attempts;
  private Answer $answers;
  private OpenAIService $ai;

  public function __construct()
  {
    $this->attempts = new Attempt();
    $this->answers = new Answer();
    $this->ai = new OpenAIService();
  }

  public function gradeSubmittedAttempt(int $attemptId): float
  {
    $attempt = $this->attempts->find($attemptId);

    if (!$attempt || (string) ($attempt['status'] ?? '') !== 'submitted') {
      throw new \RuntimeException('Tentativa não está pendente de correção.');
    }

    $answers = $this->answers->findByAttempt($attemptId);
    $evaluations = [];
    $totalScore = 0.0;

    foreach ($answers as $answer) {
      if (trim((string) ($answer['student_answer'] ?? '')) === '') {
        if (!empty($answer['id'])) {
          $evaluations[] = [
            'answer_id' => (int) $answer['id'],
            'score' => 0.0,
            'feedback' => 'Questão não respondida.',
            'deduction_reasons' => ['missing_concept', 'incomplete_explanation'],
          ];
        }
        continue;
      }

      $result = $this->ai->evaluateAnswer(
        (string) $answer['question_text'],
        (string) $answer['expected_answer_hint'],
        (string) $answer['student_answer'],
        (float) $answer['max_score'],
        (int) $answer['id'],
        (int) $attempt['student_id']
      );

      $evaluations[] = [
        'answer_id' => (int) $answer['id'],
        'score' => (float) $result['score'],
        'feedback' => (string) $result['feedback'],
        'deduction_reasons' => $result['deduction_reasons'] ?? [],
      ];
      $totalScore += (float) $result['score'];
    }

    $db = Database::getInstance();

    try {
      $db->beginTransaction();

      foreach ($evaluations as $evaluation) {
        $this->answers->updateAiResult(
          $evaluation['answer_id'],
          $evaluation['score'],
          $evaluation['feedback'],
          $evaluation['deduction_reasons']
        );
      }

      $this->attempts->markGraded($attemptId, $totalScore);
      $db->commit();
    } catch (\Throwable $e) {
      if ($db->inTransaction()) {
        $db->rollback();
      }

      throw $e;
    }

    return $totalScore;
  }
}
