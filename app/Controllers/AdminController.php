<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\View;

class AdminController
{
  public function dashboard(): void
  {
    Auth::requireAdmin();

    View::render('admin/dashboard');
  }
}
