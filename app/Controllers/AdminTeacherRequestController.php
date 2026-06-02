<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemSetting;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminTeacherRequestController extends AdminBaseController
{
  public function teacherRequests(): void
  {
    Auth::requireAdmin();

    $perPage    = self::ADMIN_PER_PAGE;
    $page       = max(1, (int) Request::get('page', 1));
    $total      = $this->users->countPendingTeacherRequests();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $currentPage = min($page, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    $settings = new SystemSetting();

    View::render('admin/teacher_requests/index', [
      'requests'                   => $this->users->getPendingTeacherRequests($perPage, $offset),
      'teacherRegistrationEnabled' => $settings->getBool('teacher_registration_enabled'),
      'pagination'                 => [
        'path'        => '/admin/teacher-requests',
        'query'       => [],
        'perPage'     => $perPage,
        'totalItems'  => $total,
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'offset'      => $offset,
      ],
    ]);
  }

  public function teacherRequestHistory(): void
  {
    Auth::requireAdmin();

    $perPage    = self::ADMIN_PER_PAGE;
    $page       = max(1, (int) Request::get('page', 1));
    $total      = $this->users->countTeacherRequestHistory();
    $totalPages = max(1, (int) ceil($total / $perPage));
    $currentPage = min($page, $totalPages);
    $offset      = ($currentPage - 1) * $perPage;

    View::render('admin/teacher_requests/history', [
      'history'    => $this->users->getTeacherRequestHistory($perPage, $offset),
      'pagination' => [
        'path'        => '/admin/teacher-requests/history',
        'query'       => [],
        'perPage'     => $perPage,
        'totalItems'  => $total,
        'totalPages'  => $totalPages,
        'currentPage' => $currentPage,
        'offset'      => $offset,
      ],
    ]);
  }

  public function approveTeacherRequest(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $userId = (int) $id;
    $user   = $this->users->find($userId);
    global $session;

    if (!$user || ($user['role'] ?? '') !== 'teacher' || !in_array($user['status'] ?? '', ['pending', 'rejected'], true)) {
      $session->flash('error', 'Solicitação não encontrada ou já aprovada.');
      View::redirect('/admin/teacher-requests');
    }

    $this->users->approveTeacher($userId, (int) Auth::id());
    AuditService::record('admin.teacher_request.approve', 'user', $userId, [
      'teacher_name'  => $user['name']  ?? null,
      'teacher_email' => $user['email'] ?? null,
    ]);

    $session->flash('success', 'Solicitação aprovada. O docente já pode acessar o sistema.');
    View::redirect('/admin/teacher-requests');
  }

  public function rejectTeacherRequest(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $userId = (int) $id;
    $user   = $this->users->find($userId);
    global $session;

    if (!$user || ($user['role'] ?? '') !== 'teacher' || ($user['status'] ?? '') !== 'pending') {
      $session->flash('error', 'Solicitação não encontrada ou já processada.');
      View::redirect('/admin/teacher-requests');
    }

    $this->users->rejectTeacher($userId, (int) Auth::id());
    AuditService::record('admin.teacher_request.reject', 'user', $userId, [
      'teacher_name'  => $user['name']  ?? null,
      'teacher_email' => $user['email'] ?? null,
    ]);

    $session->flash('success', 'Solicitação rejeitada.');
    View::redirect('/admin/teacher-requests');
  }
}
