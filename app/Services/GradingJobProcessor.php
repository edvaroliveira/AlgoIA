<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attempt;
use App\Models\GradingJob;
use App\Models\InjectionLog;

class GradingJobProcessor
{
  public function processNext(): bool
  {
    $workerId = bin2hex(random_bytes(8));
    $jobs     = new GradingJob();
    $jobs->recoverStaleProcessing();
    $job = $jobs->claimNext($workerId);

    if (!$job) {
      return false;
    }

    $jobId     = (int) $job['id'];
    $attemptId = (int) $job['attempt_id'];

    try {
      $attempt = (new Attempt())->find($attemptId);
      if ($attempt && (string) ($attempt['status'] ?? '') === 'graded') {
        $jobs->markCompleted($jobId, $workerId);
        error_log("Grading job {$jobId} skipped because attempt {$attemptId} is already graded.");
        return true;
      }

      $score = (new AttemptGradingService())->gradeSubmittedAttempt($attemptId);
      $jobs->markCompleted($jobId, $workerId);
      error_log("Grading job {$jobId} completed for attempt {$attemptId} with score {$score}.");
      return true;
    } catch (\Throwable $e) {
      $delay = $this->retryDelaySeconds((int) ($job['attempts'] ?? 1));
      $jobs->markFailed($jobId, $e->getMessage(), $delay, $workerId);
      error_log("Grading job {$jobId} failed for attempt {$attemptId} [" . $this->errorCategory($e) . "]: " . $e->getMessage());
      return true;
    }
  }

  public function processBatch(int $limit = 10): int
  {
    $processed = 0;
    $max = max(1, $limit);

    while ($processed < $max && $this->processNext()) {
      $processed++;
    }

    // Enforce injection-log retention as part of the periodic worker run.
    try {
      (new InjectionLog())->pruneOld();
    } catch (\Throwable $e) {
      error_log('injection_log prune failed: ' . $e->getMessage());
    }

    return $processed;
  }

  private function retryDelaySeconds(int $attempts): int
  {
    return min(3600, 120 * (2 ** max(0, $attempts - 1)));
  }

  private function errorCategory(\Throwable $e): string
  {
    $message = mb_strtolower($e->getMessage());

    if (str_contains($message, 'comunicação') || str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
      return 'timeout';
    }

    if (str_contains($message, 'temporariamente indisponível') || str_contains($message, 'rate') || str_contains($message, '429')) {
      return 'provider_unavailable';
    }

    if (str_contains($message, 'formato inesperado') || str_contains($message, 'json')) {
      return 'invalid_response';
    }

    return 'unknown';
  }
}
