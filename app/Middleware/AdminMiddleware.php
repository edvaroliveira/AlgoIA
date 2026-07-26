<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;

class AdminMiddleware
{
  public static function handle(): void
  {
    Auth::requireAdmin();
  }
}
