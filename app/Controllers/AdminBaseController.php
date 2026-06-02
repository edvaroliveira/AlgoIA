<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Attempt;
use App\Models\Exercise;
use App\Models\Question;
use App\Models\Turma;
use App\Models\User;
use Core\Request;

abstract class AdminBaseController
{
  protected const ADMIN_PER_PAGE = 20;

  protected User $users;
  protected Turma $turmas;
  protected Exercise $exercises;
  protected AuditLog $auditLogs;
  protected Question $questions;
  protected Attempt $attempts;

  public function __construct()
  {
    $this->users     = new User();
    $this->turmas    = new Turma();
    $this->exercises = new Exercise();
    $this->auditLogs = new AuditLog();
    $this->questions = new Question();
    $this->attempts  = new Attempt();
  }

  // ── CSV / JSON streaming ─────────────────────────────────────────────────

  protected function streamCsvDownload(string $filename, array $headers, array $rows, callable $mapper): void
  {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit('Não foi possível gerar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, $headers, ';');

    foreach ($rows as $row) {
      fputcsv($output, $mapper($row), ';');
    }

    fclose($output);
    exit;
  }

  protected function streamJsonDownload(string $filename, array $payload): void
  {
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
  }

  // ── Pagination ───────────────────────────────────────────────────────────

  protected function buildPagination(string $path, array $filters, int $totalItems): array
  {
    $requestedPage = max(1, (int) Request::get('page', 1));
    $totalPages    = max(1, (int) ceil($totalItems / self::ADMIN_PER_PAGE));
    $currentPage   = min($requestedPage, $totalPages);

    return [
      'path'       => $path,
      'query'      => $filters,
      'perPage'    => self::ADMIN_PER_PAGE,
      'totalItems' => $totalItems,
      'totalPages' => $totalPages,
      'currentPage' => $currentPage,
      'offset'     => ($currentPage - 1) * self::ADMIN_PER_PAGE,
    ];
  }

  // ── Navigation helpers ───────────────────────────────────────────────────

  protected function buildBatchReturnPath(string $basePath): string
  {
    $returnQuery = trim((string) Request::post('return_query', ''));
    if ($returnQuery === '') {
      return $basePath;
    }

    return $basePath . '?' . ltrim($returnQuery, '?');
  }

  protected function buildReturnPathFromRequest(string $basePath): string
  {
    $returnTo = trim((string) Request::get('return_to', ''));
    if ($returnTo !== '' && str_starts_with($returnTo, '/') && strpos($returnTo, '://') === false) {
      return $returnTo;
    }

    $returnQuery = trim((string) Request::get('return_query', ''));
    if ($returnQuery === '') {
      return $basePath;
    }

    return $basePath . '?' . ltrim($returnQuery, '?');
  }

  // ── ID extraction ────────────────────────────────────────────────────────

  protected function extractSelectedIdsFromRequest(string $field): array
  {
    $rawIds = Request::post($field, []);
    if (!is_array($rawIds)) {
      return [];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn(int $value): bool => $value > 0)));
    sort($ids);

    return $ids;
  }

  protected function extractPublicationExerciseIdsFromRequest(): array
  {
    return $this->extractSelectedIdsFromRequest('exercise_ids');
  }

  protected function extractPublicationTurmaIdsFromRequest(): array
  {
    $rawTurmaIds = Request::post('turma_ids', []);
    if (!is_array($rawTurmaIds)) {
      return [];
    }

    $turmaIds = array_values(array_unique(array_filter(array_map('intval', $rawTurmaIds), static fn(int $value): bool => $value > 0)));
    sort($turmaIds);

    return $turmaIds;
  }

  // ── Filter presets ───────────────────────────────────────────────────────

  protected function getFilterPresets(string $scope): array
  {
    global $session;

    $presets      = $session->get('admin_filter_presets', []);
    $scopePresets = is_array($presets[$scope] ?? null) ? array_values($presets[$scope]) : [];

    usort($scopePresets, static function (array $left, array $right): int {
      return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
    });

    return $scopePresets;
  }

  protected function getFilterPresetConfig(string $scope): ?array
  {
    return match ($scope) {
      'users'     => ['path' => '/admin/users'],
      'turmas'    => ['path' => '/admin/turmas'],
      'exercises' => ['path' => '/admin/exercises'],
      'audit'     => ['path' => '/admin/audit'],
      default     => null,
    };
  }

  protected function getFilterPresetInput(string $scope): array
  {
    return match ($scope) {
      'users'     => $this->getUserFiltersFromPost(),
      'turmas'    => $this->getTurmaFiltersFromPost(),
      'exercises' => $this->getExerciseFiltersFromPost(),
      'audit'     => $this->getAuditFiltersFromPost(),
      default     => [],
    };
  }

  protected function buildFilterQuery(array $filters): string
  {
    return http_build_query(array_filter($filters, static fn($value): bool => (string) $value !== ''));
  }

  protected function slugifyPresetName(string $name): string
  {
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)) ?? 'preset';
    $normalized = trim($normalized, '-');

    if ($normalized === '') {
      $normalized = 'preset';
    }

    return substr($normalized, 0, 40);
  }

  // ── Filter getters ───────────────────────────────────────────────────────

  protected function getUserFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'role'   => trim((string) Request::get('role', '')),
      'status' => trim((string) Request::get('status', '')),
    ];
  }

  protected function getUserFiltersFromPost(): array
  {
    return [
      'search' => trim((string) Request::post('search', '')),
      'role'   => trim((string) Request::post('role', '')),
      'status' => trim((string) Request::post('status', '')),
    ];
  }

  protected function getTurmaFiltersFromRequest(): array
  {
    return [
      'search'    => trim((string) Request::get('search', '')),
      'status'    => trim((string) Request::get('status', '')),
      'attention' => trim((string) Request::get('attention', '')),
    ];
  }

  protected function getTurmaFiltersFromPost(): array
  {
    return [
      'search'    => trim((string) Request::post('search', '')),
      'status'    => trim((string) Request::post('status', '')),
      'attention' => trim((string) Request::post('attention', '')),
    ];
  }

  protected function getExerciseFiltersFromRequest(): array
  {
    return [
      'search' => trim((string) Request::get('search', '')),
      'status' => trim((string) Request::get('status', '')),
      'timing' => trim((string) Request::get('timing', '')),
    ];
  }

  protected function getExerciseFiltersFromPost(): array
  {
    return [
      'search' => trim((string) Request::post('search', '')),
      'status' => trim((string) Request::post('status', '')),
      'timing' => trim((string) Request::post('timing', '')),
    ];
  }

  protected function getAuditFiltersFromRequest(): array
  {
    return [
      'search'      => trim((string) Request::get('search', '')),
      'action'      => trim((string) Request::get('action', '')),
      'entity_type' => trim((string) Request::get('entity_type', '')),
      'from_date'   => trim((string) Request::get('from_date', '')),
      'to_date'     => trim((string) Request::get('to_date', '')),
    ];
  }

  protected function getAuditFiltersFromPost(): array
  {
    return [
      'search'      => trim((string) Request::post('search', '')),
      'action'      => trim((string) Request::post('action', '')),
      'entity_type' => trim((string) Request::post('entity_type', '')),
      'from_date'   => trim((string) Request::post('from_date', '')),
      'to_date'     => trim((string) Request::post('to_date', '')),
    ];
  }

  // ── Context text builders ────────────────────────────────────────────────

  protected function buildAdminUserContextText(array $user): string
  {
    $role = (string) ($user['role'] ?? 'student');

    if ($role === 'teacher') {
      return (int) ($user['owned_turma_count'] ?? 0) . ' turma(s) · ' . (int) ($user['exercise_count'] ?? 0) . ' exercício(s)';
    }

    if ($role === 'student') {
      return !empty($user['turma_names']) ? (string) $user['turma_names'] : 'Sem turma';
    }

    if ($role === 'admin') {
      return 'Acesso global';
    }

    return '—';
  }

  protected function buildAdminTurmaSituationText(array $turma): string
  {
    if (!(bool) ($turma['active'] ?? true)) {
      return 'Inativa';
    }

    if ((int) ($turma['pending_count'] ?? 0) > 0) {
      return 'Com pendências';
    }

    return 'Operação normal';
  }

  protected function buildAdminExerciseStatusText(array $exercise): string
  {
    $status = (string) ($exercise['status'] ?? '');
    if ($status === Exercise::STATUS_DRAFT) {
      return 'Rascunho';
    }

    if ($status === Exercise::STATUS_READY) {
      return 'Pronto';
    }

    if ($status !== Exercise::STATUS_ACTIVE) {
      return $status;
    }

    $now      = time();
    $opensAt  = !empty($exercise['opens_at'])  ? strtotime((string) $exercise['opens_at'])  : false;
    $closesAt = !empty($exercise['closes_at']) ? strtotime((string) $exercise['closes_at']) : false;

    if ($closesAt !== false && $closesAt < $now) {
      return 'Encerrado';
    }

    if ($opensAt !== false && $closesAt !== false && $opensAt <= $now && $closesAt >= $now) {
      return 'Aberto';
    }

    return 'Agendado';
  }

  protected function buildAuditContextText(array $log): string
  {
    $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);
    if (!is_array($metadata)) {
      return 'Sem metadados adicionais';
    }

    $contextParts = [];
    foreach ($metadata as $key => $value) {
      if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }

      if ($value === null || $value === '') {
        continue;
      }

      $contextParts[] = $key . ': ' . (string) $value;
    }

    return $contextParts ? implode(' | ', $contextParts) : 'Sem metadados adicionais';
  }

  // ── Exercise publication helpers ─────────────────────────────────────────

  protected function sanitizeReviewStatus(string $status): string
  {
    return in_array($status, [Exercise::REVIEW_APPROVED, Exercise::REVIEW_FLAGGED, Exercise::REVIEW_BLOCKED], true)
      ? $status
      : Exercise::REVIEW_APPROVED;
  }

  protected function sanitizeReviewNote(?string $note): ?string
  {
    $value = trim((string) $note);
    return $value === '' ? null : mb_substr($value, 0, 2000);
  }

  protected function findExercisePublicationByTurmaId(array $exercise, int $turmaId): ?array
  {
    $publications = $exercise['publication_settings'] ?? [];
    if (!is_array($publications)) {
      return null;
    }

    foreach ($publications as $publication) {
      if ((int) ($publication['turma_id'] ?? 0) === $turmaId) {
        return $publication;
      }
    }

    return null;
  }

  protected function getSelectedExercisePublications(array $exercise, array $selectedTurmaIds): array
  {
    $publications = $exercise['publication_settings'] ?? [];
    if (!is_array($publications)) {
      return [];
    }

    return array_values(array_filter($publications, static function (array $publication) use ($selectedTurmaIds): bool {
      return in_array((int) ($publication['turma_id'] ?? 0), $selectedTurmaIds, true);
    }));
  }

  protected function getSelectedTurmaPublications(array $publications, array $selectedExerciseIds): array
  {
    return array_values(array_filter($publications, static function (array $publication) use ($selectedExerciseIds): bool {
      return in_array((int) ($publication['id'] ?? 0), $selectedExerciseIds, true);
    }));
  }
}
