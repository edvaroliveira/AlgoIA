<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Auth;

class TeacherMiddleware
{
  public static function handle(): void
  {
    Auth::requireTeacher();
  }
}
