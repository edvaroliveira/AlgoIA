<?php
$pageTitle = 'Histórico de solicitações — Administração';
$history = $history ?? [];
$pagination = $pagination ?? ['totalItems' => count($history), 'totalPages' => 1, 'currentPage' => 1, 'path' => '/admin/teacher-requests/history', 'query' => []];
global $session;
?>

<div class="page-header">
  <div>
    <h1>Histórico de solicitações</h1>
    <p class="subtitle">Solicitações de docentes já aprovadas ou rejeitadas.</p>
  </div>
  <a href="<?= \Core\app_url('/admin/teacher-requests') ?>" class="btn btn--ghost">Pendentes</a>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Solicitações processadas</h2>
      <p class="surface-copy"><?= (int) ($pagination['totalItems'] ?? count($history)) ?> solicitação(ões) no histórico.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($history)): ?>
      <p class="empty-state">Nenhuma solicitação processada ainda.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Docente</th>
            <th>Instituição / Justificativa</th>
            <th>Solicitado em</th>
            <th>Decisão</th>
            <th>Decidido por</th>
            <th>Data decisão</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $req): ?>
            <?php
            $isApproved = ($req['status'] ?? '') === 'active';
            $decisionDate = $isApproved
              ? ($req['approved_at'] ?? null)
              : ($req['rejected_at'] ?? null);
            ?>
            <tr>
              <td>
                <strong><?= \Core\View::e($req['name'] ?? '—') ?></strong><br>
                <span class="text-muted"><?= \Core\View::e($req['email'] ?? '') ?></span>
              </td>
              <td><?= \Core\View::e(mb_strimwidth((string) ($req['registration_note'] ?? '—'), 0, 100, '…')) ?></td>
              <td><?= !empty($req['created_at']) ? date('d/m/Y H:i', strtotime((string) $req['created_at'])) : '—' ?></td>
              <td>
                <span class="badge badge--<?= $isApproved ? 'success' : 'neutral' ?>">
                  <?= $isApproved ? 'aprovado' : 'rejeitado' ?>
                </span>
              </td>
              <td><?= \Core\View::e($req['approver_name'] ?? '—') ?></td>
              <td><?= !empty($decisionDate) ? date('d/m/Y H:i', strtotime((string) $decisionDate)) : '—' ?></td>
              <td class="td-actions">
                <?php if (!$isApproved): ?>
                  <form method="POST" action="<?= \Core\app_url('/admin/teacher-requests/' . (int) ($req['id'] ?? 0) . '/approve') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                    <button type="submit" class="btn btn--sm btn--ghost">Aprovar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
  </div>
</section>
