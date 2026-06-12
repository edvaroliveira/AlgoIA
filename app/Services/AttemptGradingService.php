<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exercise;
use App\Models\Question;

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

  public function gradeSubmittedAttempt(int $attemptId, ?callable $heartbeat = null): float
  {
    $startedAt = microtime(true);
    $attempt = $this->attempts->find($attemptId);

    if (!$attempt || (string) ($attempt['status'] ?? '') !== 'submitted') {
      throw new \RuntimeException('Tentativa não está pendente de correção.');
    }

    $exercises = new Exercise();
    $exercise = $exercises->find((int) $attempt['exercise_id']);
    if (
      !$exercise
      || $exercises->isBlockedForReview($exercise)
      || (new Question())->hasBlockedByExercise((int) $attempt['exercise_id'])
    ) {
      throw new \RuntimeException('Exercício bloqueado pela moderação. Correção suspensa.');
    }

    $answers = $this->answers->findByAttempt($attemptId);
    $totalScore = 0.0;

    foreach ($answers as $answer) {
      $this->assertLease($heartbeat);

      // Reuse a prior successful evaluation so a retried job does not re-call
      // (and re-charge) the AI for answers already graded in an earlier run.
      if (!empty($answer['evaluated_at'])) {
        $totalScore += (float) ($answer['ai_score'] ?? 0.0);
        continue;
      }

      if (trim((string) ($answer['student_answer'] ?? '')) === '') {
        if (!empty($answer['id'])) {
          $this->answers->updateAiResult(
            (int) $answer['id'],
            0.0,
            'Questão não respondida.',
            ['missing_concept', 'incomplete_explanation']
          );
        }
        continue;
      }

      $answerStartedAt = microtime(true);

      try {
        $result = $this->ai->evaluateAnswer(
          (string) $answer['question_text'],
          (string) $answer['expected_answer_hint'],
          (string) $answer['student_answer'],
          (float) $answer['max_score'],
          (int) $answer['id'],
          (int) $attempt['student_id']
        );
      } finally {
        $answerDurationMs = (int) round((microtime(true) - $answerStartedAt) * 1000);
        error_log("Attempt {$attemptId} answer " . (int) ($answer['id'] ?? 0) . " evaluation duration: {$answerDurationMs}ms");
      }

      // Do not persist an AI result after another worker has recovered the job.
      $this->assertLease($heartbeat);

      // Persist immediately so a later failure does not discard this evaluation.
      $this->answers->updateAiResult(
        (int) $answer['id'],
        $this->canonicalScore((float) $result['score']),
        (string) $result['feedback'],
        $result['deduction_reasons'] ?? []
      );
      $totalScore += $this->canonicalScore((float) $result['score']);
    }

    $this->assertLease($heartbeat);
    $totalScore = $this->canonicalScore($totalScore);
    $this->attempts->markGraded($attemptId, $totalScore);

    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
    error_log("Attempt {$attemptId} grading completed in {$durationMs}ms with score {$totalScore}.");

    return $totalScore;
  }

  private function assertLease(?callable $heartbeat): void
  {
    if ($heartbeat !== null && $heartbeat() !== true) {
      throw new \RuntimeException('Lease do job de correção foi perdido.');
    }
  }

  private function canonicalScore(float $score): float
  {
    return round($score, 1);
  }
}
