<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use Core\Auth;
use Core\View;

/** Avisos dentro do app: lista os mais recentes e os marca como lidos ao abrir. */
class NotificationController
{
  public function index(): void
  {
    $userId = (int) Auth::id();
    $notifications = new Notification();
    $items = $notifications->latest($userId);
    $notifications->markAllRead($userId);

    View::render('notifications/index', ['items' => $items]);
  }
}
