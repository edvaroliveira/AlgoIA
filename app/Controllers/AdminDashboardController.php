<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GradingJob;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Core\Auth;
use Core\Request;
use Core\View;

class AdminDashboardController extends AdminBaseController
{
  public function dashboard(): void
  {
    Auth::requireAdmin();

    $userCounts             = $this->users->countByRole();
    $pendingTurmas          = $this->turmas->getPendingTurmasForAdmin();
    $closingExercises       = $this->exercises->getClosingSoonForAdmin();
    $pendingUsers           = $this->users->getRecentPendingForAdmin();
    $recentAdminEvents      = $this->auditLogs->getRecentAdminEvents();
    $pendingGradingAttempts = $this->attempts->getPendingGradingForAdmin(6);
    $pendingTeacherRequestCount = $this->users->countPendingTeacherRequests();
    $settings               = new SystemSetting();
    $gradingJobs            = new GradingJob();
    $pendingGradingAttempts = $this->attachGradingJobStatuses($pendingGradingAttempts, $gradingJobs);

    View::render('admin/dashboard', [
      'totalUsers'                 => $userCounts['total'],
      'adminCount'                 => $userCounts['admin'],
      'teacherCount'               => $userCounts['teacher'],
      'studentCount'               => $userCounts['student'],
      'turmaCount'                 => $this->turmas->countForAdmin(),
      'exerciseCount'              => $this->exercises->countForAdmin(),
      'auditCount'                 => $this->auditLogs->countForAdmin(),
      'pendingUserCount'           => $this->users->countPendingForAdmin(),
      'pendingEnrollmentCount'     => $this->turmas->countPendingEnrollmentsForAdmin(),
      'closingSoonCount'           => $this->exercises->countClosingSoonForAdmin(),
      'pendingGradingCount'        => $this->attempts->countPendingGradingForAdmin(),
      'pendingTeacherRequestCount' => $pendingTeacherRequestCount,
      'teacherRegistrationEnabled' => $settings->getBool('teacher_registration_enabled'),
      'pendingTurmas'              => $pendingTurmas,
      'closingExercises'           => $closingExercises,
      'pendingUsers'               => $pendingUsers,
      'pendingGradingAttempts'     => $pendingGradingAttempts,
      'gradingJobSummary'          => $gradingJobs->operationalSummary(),
      'gradingJobFailures'         => $gradingJobs->recentFailures(null, 5),
      'pendingActions'             => $this->buildDashboardPendingActions($pendingUsers, $pendingTurmas, $closingExercises, $pendingGradingAttempts, $this->users->getPendingTeacherRequests(3)),
      'recentAdminEvents'          => $recentAdminEvents,
    ]);
  }

  public function saveFilterPreset(string $scope): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $config = $this->getFilterPresetConfig($scope);
    global $session;

    if ($config === null) {
      $session->flash('error', 'Escopo de preset inválido.');
      View::redirect('/admin/dashboard');
    }

    $name         = trim(Request::str('preset_name'));
    $filters      = $this->getFilterPresetInput($scope);
    $redirectPath = $this->buildBatchReturnPath($config['path']);
    $filterQuery  = $this->buildFilterQuery($filters);

    if ($name === '') {
      $session->flash('error', 'Informe um nome para o preset.');
      View::redirect($redirectPath);
    }

    if ($filterQuery === '') {
      $session->flash('error', 'Aplique pelo menos um filtro antes de salvar um preset.');
      View::redirect($redirectPath);
    }

    $presetId    = $this->slugifyPresetName($name);
    $presets     = $session->get('admin_filter_presets', []);
    $scopePresets = is_array($presets[$scope] ?? null) ? $presets[$scope] : [];
    $scopePresets[$presetId] = [
      'id'         => $presetId,
      'name'       => $name,
      'query'      => $filterQuery,
      'filters'    => $filters,
      'updated_at' => date('c'),
    ];

    $presets[$scope] = $scopePresets;
    $session->set('admin_filter_presets', $presets);
    $session->flash('success', 'Preset salvo com sucesso.');
    View::redirect($redirectPath);
  }

  public function deleteFilterPreset(string $scope): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $config = $this->getFilterPresetConfig($scope);
    global $session;

    if ($config === null) {
      $session->flash('error', 'Escopo de preset inválido.');
      View::redirect('/admin/dashboard');
    }

    $presetId     = trim(Request::str('preset_id'));
    $redirectPath = $this->buildBatchReturnPath($config['path']);
    $presets      = $session->get('admin_filter_presets', []);
    $scopePresets = is_array($presets[$scope] ?? null) ? $presets[$scope] : [];

    if ($presetId === '' || !isset($scopePresets[$presetId])) {
      $session->flash('error', 'Preset não encontrado.');
      View::redirect($redirectPath);
    }

    unset($scopePresets[$presetId]);
    $presets[$scope] = $scopePresets;
    $session->set('admin_filter_presets', $presets);
    $session->flash('success', 'Preset removido com sucesso.');
    View::redirect($redirectPath);
  }

  public function retryGradingJob(string $id): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $jobId   = (int) $id;
    $jobs    = new GradingJob();
    $retried = $jobs->retryExhausted($jobId);

    global $session;
    if ($retried) {
      AuditService::record('admin.grading_job.retry', 'grading_job', $jobId);
      $session->flash('success', "Job #{$jobId} recolocado na fila.");
    } else {
      $session->flash('error', "Não foi possível retentar o job #{$jobId}. Verifique se está no estado 'failed'.");
    }

    View::redirect('/admin/attempts/pending');
  }

  public function toggleTeacherRegistration(): void
  {
    Auth::requireAdmin();
    Request::validateCsrf();

    $settings = new SystemSetting();
    $current  = $settings->getBool('teacher_registration_enabled');
    $newValue = $current ? '0' : '1';
    $settings->set('teacher_registration_enabled', $newValue, (int) Auth::id());

    AuditService::record('admin.settings.teacher_registration_toggle', 'setting', 0, [
      'previous' => $current ? 'enabled' : 'disabled',
      'new'      => $newValue === '1' ? 'enabled' : 'disabled',
    ]);

    global $session;
    $label = $newValue === '1' ? 'habilitado' : 'desabilitado';
    $session->flash('success', "Cadastro público de docentes {$label}.");
    View::redirect('/admin/teacher-requests');
  }

  private function attachGradingJobStatuses(array $attempts, GradingJob $gradingJobs): array
  {
    $statuses = $gradingJobs->statusesForAttempts(array_column($attempts, 'id'));

    foreach ($attempts as &$attempt) {
      $job = $statuses[(int) ($attempt['id'] ?? 0)] ?? null;
      $attempt['grading_job_status']   = $job['status']   ?? null;
      $attempt['grading_job_attempts'] = $job['attempts'] ?? null;
    }
    unset($attempt);

    return $attempts;
  }

  private function buildDashboardPendingActions(array $pendingUsers, array $pendingTurmas, array $closingExercises, array $pendingGradingAttempts = [], array $pendingTeacherRequests = []): array
  {
    $actions = [];

    foreach (array_slice($pendingGradingAttempts, 0, 3) as $attempt) {
      $actions[] = [
        'priority'     => 5,
        'variant'      => 'error',
        'label'        => 'correção pendente',
        'title'        => (string) ($attempt['exercise_title'] ?? 'Tentativa'),
        'description'  => 'Aluno: ' . (string) ($attempt['student_name'] ?? '—'),
        'path'         => '/admin/dashboard',
        'action_label' => 'Reprocessar',
      ];
    }

    foreach (array_slice($closingExercises, 0, 3) as $exercise) {
      $actions[] = [
        'priority'     => 10,
        'variant'      => 'error',
        'label'        => 'janela crítica',
        'title'        => (string) ($exercise['title'] ?? 'Exercício'),
        'description'  => 'Fecha em ' . (!empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime((string) $exercise['closes_at'])) : 'breve'),
        'path'         => '/admin/exercises/' . (int) ($exercise['id'] ?? 0),
        'action_label' => 'Revisar exercício',
      ];
    }

    foreach (array_slice($pendingTurmas, 0, 3) as $turma) {
      $actions[] = [
        'priority'     => 20,
        'variant'      => 'warning',
        'label'        => 'pendência de entrada',
        'title'        => (string) ($turma['name'] ?? 'Turma'),
        'description'  => (int) ($turma['pending_count'] ?? 0) . ' solicitação(ões) aguardando decisão',
        'path'         => '/admin/turmas/' . (int) ($turma['id'] ?? 0),
        'action_label' => 'Abrir turma',
      ];
    }

    foreach (array_slice($pendingUsers, 0, 3) as $user) {
      $actions[] = [
        'priority'     => 30,
        'variant'      => 'info',
        'label'        => 'cadastro pendente',
        'title'        => (string) ($user['name'] ?? 'Usuário'),
        'description'  => (string) ($user['email'] ?? 'Sem e-mail'),
        'path'         => '/admin/users/' . (int) ($user['id'] ?? 0),
        'action_label' => 'Abrir usuário',
      ];
    }

    foreach (array_slice($pendingTeacherRequests, 0, 3) as $request) {
      $actions[] = [
        'priority'     => 15,
        'variant'      => 'warning',
        'label'        => 'solicitação docente',
        'title'        => (string) ($request['name'] ?? 'Docente'),
        'description'  => (string) ($request['email'] ?? 'Sem e-mail'),
        'path'         => '/admin/teacher-requests',
        'action_label' => 'Revisar solicitações',
      ];
    }

    usort($actions, static fn(array $left, array $right): int => $left['priority'] <=> $right['priority']);

    return array_slice($actions, 0, 8);
  }
}
