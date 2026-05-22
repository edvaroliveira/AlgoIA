<?php
$pageTitle = 'Correções pendentes — Administração';
$attempts = $attempts ?? [];
$filters = $filters ?? ['search' => '', 'from_date' => '', 'to_date' => '', 'min_age_hours' => 0];
$pagination = $pagination ?? ['totalItems' => count($attempts), 'totalPages' => 1, 'currentPage' => 1, 'path' => '/admin/attempts/pending', 'query' => $filters];
global $session;
?>

<div class="page-header">
  <div>
    <h1>Correções pendentes</h1>
    <p class="subtitle">Tentativas enviadas que ainda aguardam avaliação automática ou reprocessamento operacional.</p>
  </div>
  <a href="<?= \Core\app_url('/admin/dashboard') ?>" class="btn btn--ghost">Voltar ao painel</a>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <form method="GET" action="<?= \Core\app_url('/admin/attempts/pending') ?>" class="form">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="pending-search">Buscar</label>
          <input id="pending-search" type="text" name="search" class="form-input" value="<?= \Core\View::e($filters['search'] ?? '') ?>" placeholder="Aluno, e-mail, exercício, docente ou turma">
        </div>
        <div class="form-group">
          <label class="form-label" for="pending-min-age">Idade mínima</label>
          <input id="pending-min-age" type="number" min="0" name="min_age_hours" class="form-input" value="<?= (int) ($filters['min_age_hours'] ?? 0) ?>" placeholder="Horas">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="pending-from">De</label>
          <input id="pending-from" type="date" name="from_date" class="form-input" value="<?= \Core\View::e($filters['from_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="pending-to">Até</label>
          <input id="pending-to" type="date" name="to_date" class="form-input" value="<?= \Core\View::e($filters['to_date'] ?? '') ?>">
        </div>
        <div class="form-group" style="justify-content: flex-end;">
          <label class="form-label">Ações</label>
          <div class="td-actions">
            <button type="submit" class="btn btn--primary">Filtrar</button>
            <a href="<?= \Core\app_url('/admin/attempts/pending') ?>" class="btn btn--ghost">Limpar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Fila de reprocessamento</h2>
      <p class="surface-copy"><?= (int) ($pagination['totalItems'] ?? count($attempts)) ?> tentativa(s) pendente(s) no filtro atual.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($attempts)): ?>
      <p class="empty-state">Nenhuma tentativa pendente encontrada.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Aluno</th>
            <th>Exercício</th>
            <th>Docente</th>
            <th>Turma</th>
            <th>Enviada em</th>
            <th>Tempo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $attempt): ?>
            <tr>
              <td>
                <strong><?= \Core\View::e($attempt['student_name'] ?? '—') ?></strong><br>
                <span class="text-muted"><?= \Core\View::e($attempt['student_email'] ?? '') ?></span>
              </td>
              <td><?= \Core\View::e($attempt['exercise_title'] ?? '—') ?></td>
              <td><?= \Core\View::e($attempt['teacher_name'] ?? '—') ?></td>
              <td><?= \Core\View::e($attempt['turma_name'] ?? '—') ?></td>
              <td><?= !empty($attempt['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $attempt['submitted_at'])) : '—' ?></td>
              <td><span class="badge badge--warning"><?= (int) ($attempt['pending_hours'] ?? 0) ?>h</span></td>
              <td class="td-actions">
                <form method="POST" action="<?= \Core\app_url('/admin/attempts/' . (int) ($attempt['id'] ?? 0) . '/regrade') ?>">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <input type="hidden" name="return_to" value="<?= \Core\View::e(\Core\app_request_path() . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')) ?>">
                  <button type="submit" class="btn btn--sm btn--primary">Reprocessar</button>
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
