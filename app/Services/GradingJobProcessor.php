<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attempt;
use App\Models\GradingJob;

class GradingJobProcessor
{
  public function processNext(): bool
  {
    $jobs = new GradingJob();
    $job = $jobs->claimNext();

    if (!$job) {
      return false;
    }

    $jobId = (int) $job['id'];
    $attemptId = (int) $job['attempt_id'];

    try {
      $attempt = (new Attempt())->find($attemptId);
      if ($attempt && (string) ($attempt['status'] ?? '') === 'graded') {
        $jobs->markCompleted($jobId);
        error_log("Grading job {$jobId} skipped because attempt {$attemptId} is already graded.");
        return true;
      }

      $score = (new AttemptGradingService())->gradeSubmittedAttempt($attemptId);
      $jobs->markCompleted($jobId);
      error_log("Grading job {$jobId} completed for attempt {$attemptId} with score {$score}.");
      return true;
    } catch (\Throwable $e) {
      $delay = $this->retryDelaySeconds((int) ($job['attempts'] ?? 1));
      $jobs->markFailed($jobId, $e->getMessage(), $delay);
      error_log("Grading job {$jobId} failed for attempt {$attemptId}: " . $e->getMessage());
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

    return $processed;
  }

  private function retryDelaySeconds(int $attempts): int
  {
    return min(3600, 120 * (2 ** max(0, $attempts - 1)));
  }
}
