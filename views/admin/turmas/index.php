<?php
$pageTitle = 'Turmas — Administração';
$turmas = $turmas ?? [];
$filters = $filters ?? ['search' => '', 'status' => '', 'attention' => ''];
$pagination = $pagination ?? ['totalPages' => 1, 'currentPage' => 1, 'totalItems' => count($turmas), 'path' => '/admin/turmas', 'query' => $filters];
$exportQuery = http_build_query(array_filter($filters, static fn($value): bool => (string) $value !== ''));
$activeTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['active_count'] ?? 0), $turmas));
$pendingTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['pending_count'] ?? 0), $turmas));
$exerciseTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['exercise_count'] ?? 0), $turmas));
global $session;
?>

<div class="page-header">
  <div>
    <h1>Turmas do sistema</h1>
    <p class="subtitle">Visão global inicial das turmas com docente responsável, volume de alunos e carga de exercícios publicada.</p>
  </div>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <form method="GET" action="<?= \Core\app_url('/admin/turmas') ?>" class="form">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="turma-search">Buscar</label>
          <input id="turma-search" type="text" name="search" class="form-input" value="<?= \Core\View::e($filters['search'] ?? '') ?>" placeholder="Turma, docente ou chave">
        </div>
        <div class="form-group">
          <label class="form-label" for="turma-status">Situação</label>
          <select id="turma-status" name="status" class="form-input">
            <option value="">Todas</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativas</option>
            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativas</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="turma-attention">Atenção</label>
          <select id="turma-attention" name="attention" class="form-input">
            <option value="">Todas</option>
            <option value="pending" <?= ($filters['attention'] ?? '') === 'pending' ? 'selected' : '' ?>>Com pendências</option>
          </select>
        </div>
      </div>
      <div class="td-actions">
        <button type="submit" class="btn btn--primary">Filtrar</button>
        <a href="<?= \Core\app_url('/admin/turmas/export' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/turmas/export.json' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/turmas') ?>" class="btn btn--ghost">Limpar</a>
      </div>
    </form>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Turmas</span>
    <strong class="overview-card__value"><?= count($turmas) ?></strong>
    <p class="overview-card__copy">Estruturas acadêmicas atualmente cadastradas na base.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos ativos</span>
    <strong class="overview-card__value"><?= $activeTotal ?></strong>
    <p class="overview-card__copy">Matrículas aprovadas em todas as turmas monitoradas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendências</span>
    <strong class="overview-card__value"><?= $pendingTotal ?></strong>
    <p class="overview-card__copy">Solicitações de entrada aguardando decisão docente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Exercícios publicados</span>
    <strong class="overview-card__value"><?= $exerciseTotal ?></strong>
    <p class="overview-card__copy">Publicações vinculadas às turmas em toda a plataforma.</p>
  </article>
</div>

<?php if (empty($turmas)): ?>
  <p class="empty-state">Nenhuma turma encontrada.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista global de turmas</h2>
        <p class="surface-copy">Leitura administrativa para supervisão de volume, responsabilidade docente e sinais operacionais básicos.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <input type="hidden" name="return_query" value="<?= \Core\View::e($exportQuery) ?>">
        <table class="table">
          <thead>
            <tr>
              <th>
                <label>
                  <input type="checkbox" data-select-all="admin-turmas-list" aria-label="Selecionar todas as turmas da listagem">
                  Todas
                </label>
              </th>
              <th>Turma</th>
              <th>Docente</th>
              <th>Chave</th>
              <th>Ativos</th>
              <th>Pendentes</th>
              <th>Exercícios</th>
              <th>Situação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($turmas as $turma): ?>
              <?php
              $pendingCount = (int) ($turma['pending_count'] ?? 0);
              $activeCount = (int) ($turma['active_count'] ?? 0);
              $exerciseCount = (int) ($turma['exercise_count'] ?? 0);
              $turmaState = !(bool) ($turma['active'] ?? true) ? 'inactive' : 'active';
              $turmaStateLabel = $turmaState === 'active' ? 'ativas' : 'inativas';
              ?>
              <tr>
                <td><input type="checkbox" name="turma_ids[]" value="<?= (int) ($turma['id'] ?? 0) ?>" data-select-item="admin-turmas-list" data-item-state="<?= $turmaState ?>" data-item-state-label="<?= $turmaStateLabel ?>"></td>
                <td><strong><?= \Core\View::e($turma['name']) ?></strong></td>
                <td><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></td>
                <td><span class="overview-card__value overview-card__value--mono"><?= \Core\View::e($turma['access_key']) ?></span></td>
                <td><?= $activeCount ?></td>
                <td><?= $pendingCount ?></td>
                <td><?= $exerciseCount ?></td>
                <td>
                  <?php if (!(bool) ($turma['active'] ?? true)): ?>
                    <span class="badge badge--neutral">Inativa</span>
                  <?php elseif ($pendingCount > 0): ?>
                    <span class="badge badge--warning">Com pendências</span>
                  <?php else: ?>
                    <span class="badge badge--success">Operação normal</span>
                  <?php endif; ?>
                </td>
                <td class="td-actions">
                  <a href="<?= \Core\app_url('/admin/turmas/' . $turma['id'] . ($exportQuery !== '' ? '?return_query=' . urlencode($exportQuery) : '')) ?>" class="btn btn--sm">Detalhes</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="form-row">
          <div class="form-group" style="justify-content: flex-end;">
            <label class="form-label">Ações em lote</label>
            <div class="td-actions">
              <span class="hint" data-selection-count="admin-turmas-list">0 selecionadas</span>
              <span class="hint" data-selection-breakdown="admin-turmas-list"></span>
              <button type="submit" formaction="<?= \Core\app_url('/admin/turmas/batch-deactivate') ?>" class="btn btn--danger" data-requires-selection="admin-turmas-list" data-allowed-states="active" onclick="return confirm('Inativar as turmas selecionadas?');" disabled>Inativar selecionadas</button>
              <button type="submit" formaction="<?= \Core\app_url('/admin/turmas/batch-reactivate') ?>" class="btn btn--primary" data-requires-selection="admin-turmas-list" data-allowed-states="inactive" disabled>Reativar selecionadas</button>
            </div>
          </div>
        </div>
      </form>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
