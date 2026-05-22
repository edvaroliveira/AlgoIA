<?php
$pageTitle = 'Solicitações de docentes — Administração';
$requests = $requests ?? [];
$pagination = $pagination ?? ['totalItems' => count($requests), 'totalPages' => 1, 'currentPage' => 1, 'path' => '/admin/teacher-requests', 'query' => []];
$teacherRegistrationEnabled = $teacherRegistrationEnabled ?? false;
global $session;
?>

<div class="page-header">
  <div>
    <h1>Solicitações de docentes</h1>
    <p class="subtitle">Cadastros pendentes de aprovação administrativa.</p>
  </div>
  <div class="td-actions">
    <a href="<?= \Core\app_url('/admin/teacher-requests/history') ?>" class="btn btn--ghost">Histórico</a>
    <a href="<?= \Core\app_url('/admin/dashboard') ?>" class="btn btn--ghost">Painel</a>
  </div>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
      <div>
        <strong>Cadastro público:</strong>
        <span class="badge badge--<?= $teacherRegistrationEnabled ? 'success' : 'neutral' ?>">
          <?= $teacherRegistrationEnabled ? 'habilitado' : 'desabilitado' ?>
        </span>
      </div>
      <form method="POST" action="<?= \Core\app_url('/admin/settings/teacher-registration') ?>">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <button type="submit" class="btn btn--sm btn--<?= $teacherRegistrationEnabled ? 'ghost' : 'primary' ?>">
          <?= $teacherRegistrationEnabled ? 'Desabilitar cadastro' : 'Habilitar cadastro' ?>
        </button>
      </form>
    </div>
  </div>
</section>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Solicitações pendentes</h2>
      <p class="surface-copy"><?= (int) ($pagination['totalItems'] ?? count($requests)) ?> solicitação(ões) aguardando análise.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($requests)): ?>
      <p class="empty-state">Nenhuma solicitação pendente no momento.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Docente</th>
            <th>Instituição / Justificativa</th>
            <th>Solicitado em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $req): ?>
            <tr>
              <td>
                <strong><?= \Core\View::e($req['name'] ?? '—') ?></strong><br>
                <span class="text-muted"><?= \Core\View::e($req['email'] ?? '') ?></span>
              </td>
              <td><?= \Core\View::e(mb_strimwidth((string) ($req['registration_note'] ?? '—'), 0, 120, '…')) ?></td>
              <td><?= !empty($req['created_at']) ? date('d/m/Y H:i', strtotime((string) $req['created_at'])) : '—' ?></td>
              <td class="td-actions">
                <form method="POST" action="<?= \Core\app_url('/admin/teacher-requests/' . (int) ($req['id'] ?? 0) . '/approve') ?>" style="display:inline">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <button type="submit" class="btn btn--sm btn--primary">Aprovar</button>
                </form>
                <form method="POST" action="<?= \Core\app_url('/admin/teacher-requests/' . (int) ($req['id'] ?? 0) . '/reject') ?>" style="display:inline">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <button type="submit" class="btn btn--sm btn--ghost">Rejeitar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>
  </div>
</section>
