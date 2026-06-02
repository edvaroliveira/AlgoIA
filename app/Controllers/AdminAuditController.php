<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\View;

class AdminAuditController extends AdminBaseController
{
  public function audit(): void
  {
    Auth::requireAdmin();

    $filters    = $this->getAuditFiltersFromRequest();
    $pagination = $this->buildPagination('/admin/audit', $filters, $this->auditLogs->countForAdmin($filters));
    $logs       = $this->auditLogs->getAllForAdmin($filters, $pagination['perPage'], $pagination['offset']);

    View::render('admin/audit/index', [
      'logs'          => $logs,
      'filters'       => $filters,
      'filterPresets' => $this->getFilterPresets('audit'),
      'pagination'    => $pagination,
    ]);
  }

  public function exportAudit(): void
  {
    Auth::requireAdmin();

    $filters  = $this->getAuditFiltersFromRequest();
    $logs     = $this->auditLogs->getAllForAdmin($filters, null, null);
    $filename = 'audit-log-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      http_response_code(500);
      exit('Não foi possível gerar a exportação.');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['quando', 'ator_nome', 'ator_email', 'ator_role', 'acao', 'entidade', 'entidade_id', 'contexto', 'ip'], ';');

    foreach ($logs as $log) {
      fputcsv($output, [
        (string) ($log['created_at']  ?? ''),
        (string) ($log['actor_name']  ?? 'Sistema'),
        (string) ($log['actor_email'] ?? ''),
        (string) ($log['actor_role']  ?? ''),
        (string) ($log['action']      ?? ''),
        (string) ($log['entity_type'] ?? ''),
        (string) ($log['entity_id']   ?? ''),
        $this->buildAuditContextText($log),
        (string) ($log['ip_address']  ?? ''),
      ], ';');
    }

    fclose($output);
    exit;
  }

  public function exportAuditJson(): void
  {
    Auth::requireAdmin();

    $filters = $this->getAuditFiltersFromRequest();
    $logs    = $this->auditLogs->getAllForAdmin($filters, null, null);

    $this->streamJsonDownload(
      'audit-log-' . date('Ymd-His') . '.json',
      [
        'filters'     => $filters,
        'exported_at' => date('c'),
        'items'       => array_map(function (array $log): array {
          $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);

          return [
            'id'          => (int) ($log['id']          ?? 0),
            'created_at'  => (string) ($log['created_at']  ?? ''),
            'actor_name'  => (string) ($log['actor_name']  ?? 'Sistema'),
            'actor_email' => (string) ($log['actor_email'] ?? ''),
            'actor_role'  => (string) ($log['actor_role']  ?? ''),
            'action'      => (string) ($log['action']      ?? ''),
            'entity_type' => (string) ($log['entity_type'] ?? ''),
            'entity_id'   => $log['entity_id'] ?? null,
            'context'     => $this->buildAuditContextText($log),
            'metadata'    => is_array($metadata) ? $metadata : [],
            'ip_address'  => (string) ($log['ip_address']  ?? ''),
          ];
        }, $logs),
      ]
    );
  }
}
