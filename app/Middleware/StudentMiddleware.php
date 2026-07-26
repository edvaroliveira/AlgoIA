<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;

class StudentMiddleware
{
  public static function handle(): void
  {
    Auth::requireStudent();
  }
}
