<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/core/Env.php';
(new Core\Env(ROOT_PATH . '/.env'))->load();

require ROOT_PATH . '/autoload.php';

$limit = max(1, (int) ($argv[1] ?? 10));
$processed = (new App\Services\GradingJobProcessor())->processBatch($limit);

echo "Processed {$processed} grading job(s)." . PHP_EOL;
