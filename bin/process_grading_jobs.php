<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

$mode = 'run';
$limitArg = $argv[1] ?? '10';

if (in_array($limitArg, ['--dry-run', '--status'], true)) {
  $mode = ltrim($limitArg, '-');
  $limitArg = $argv[2] ?? '10';
}

$limit = max(1, (int) $limitArg);

if ($mode !== 'run') {
  $jobs = new App\Models\GradingJob();
  $summary = $jobs->operationalSummary();
  $next = $jobs->nextRunnable($limit);

  echo 'Queue summary: ' . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
  echo 'Runnable jobs: ' . count($next) . PHP_EOL;

  foreach ($next as $job) {
    echo sprintf(
      "#%d attempt=%d status=%s tries=%d exercise=%s student=%s",
      (int) ($job['id'] ?? 0),
      (int) ($job['attempt_id'] ?? 0),
      (string) ($job['status'] ?? ''),
      (int) ($job['attempts'] ?? 0),
      (string) ($job['exercise_title'] ?? ''),
      (string) ($job['student_email'] ?? '')
    ) . PHP_EOL;
  }

  exit(0);
}

$processed = (new App\Services\GradingJobProcessor())->processBatch($limit);

echo "Processed {$processed} grading job(s)." . PHP_EOL;
