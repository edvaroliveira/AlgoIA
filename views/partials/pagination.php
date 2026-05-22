<?php
$pagination = $pagination ?? [];
$totalPages = (int) ($pagination['totalPages'] ?? 1);

if ($totalPages <= 1) {
  return;
}

$currentPage = (int) ($pagination['currentPage'] ?? 1);
$path = (string) ($pagination['path'] ?? '');
$query = $pagination['query'] ?? [];

$buildUrl = static function (int $page) use ($path, $query): string {
  $params = array_filter(array_merge($query, ['page' => $page]), static fn($value): bool => $value !== '' && $value !== null);
  $queryString = http_build_query($params);
  return \Core\app_url($path . ($queryString !== '' ? '?' . $queryString : ''));
};
?>

<div class="pagination-bar">
  <div class="pagination-bar__summary">
    Página <?= $currentPage ?> de <?= $totalPages ?> · <?= (int) ($pagination['totalItems'] ?? 0) ?> registro(s)
  </div>
  <div class="td-actions">
    <?php if ($currentPage > 1): ?>
      <a href="<?= \Core\View::e($buildUrl($currentPage - 1)) ?>" class="btn btn--sm btn--ghost">Anterior</a>
    <?php endif; ?>
    <?php if ($currentPage < $totalPages): ?>
      <a href="<?= \Core\View::e($buildUrl($currentPage + 1)) ?>" class="btn btn--sm btn--ghost">Próxima</a>
    <?php endif; ?>
  </div>
</div>
