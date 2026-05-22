<?php
$pageTitle = 'Exercícios — Administração';
$exercises = $exercises ?? [];
$filters = $filters ?? ['search' => '', 'status' => '', 'timing' => ''];
$pagination = $pagination ?? ['totalPages' => 1, 'currentPage' => 1, 'totalItems' => count($exercises), 'path' => '/admin/exercises', 'query' => $filters];
$exportQuery = http_build_query(array_filter($filters, static fn($value): bool => (string) $value !== ''));
$now = time();
$activeExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'active'));
$draftExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'draft'));
$readyExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'ready'));
$attemptTotal = array_sum(array_map(static fn(array $exercise): int => (int) ($exercise['attempt_count'] ?? 0), $exercises));
$exerciseCount = count($exercises);
$exerciseCountBadgeVariant = $exerciseCount > 0 ? 'neutral' : 'warning';
$exerciseCountBadgeText = $exerciseCount > 0 ? 'base filtrada' : 'sem resultados';
$activeExercisesBadgeVariant = $activeExercises > 0 ? 'success' : 'neutral';
$activeExercisesBadgeText = $activeExercises > 0 ? 'em operação' : 'sem publicação';
$readyExercisesBadgeVariant = $readyExercises > 0 ? 'info' : 'neutral';
$readyExercisesBadgeText = $readyExercises > 0 ? 'fila pronta' : 'sem fila pronta';
$attemptTotalBadgeVariant = $attemptTotal > 0 ? 'success' : 'neutral';
$attemptTotalBadgeText = $attemptTotal > 0 ? 'atividade registrada' : 'sem submissões';
$filterPresets = $filterPresets ?? [];
global $session;
?>

<div class="page-header">
  <div>
    <h1>Exercícios do sistema</h1>
    <p class="subtitle">Visão global inicial da biblioteca de exercícios com autoria docente, publicação por turma e volume de tentativas.</p>
  </div>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <form method="GET" action="<?= \Core\app_url('/admin/exercises') ?>" class="form">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="exercise-search">Buscar</label>
          <input id="exercise-search" type="text" name="search" class="form-input" value="<?= \Core\View::e($filters['search'] ?? '') ?>" placeholder="Título, docente ou turma">
        </div>
        <div class="form-group">
          <label class="form-label" for="exercise-status">Status</label>
          <select id="exercise-status" name="status" class="form-input">
            <option value="">Todos</option>
            <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Rascunho</option>
            <option value="ready" <?= ($filters['status'] ?? '') === 'ready' ? 'selected' : '' ?>>Pronto</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Publicado</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="exercise-timing">Prazo</label>
          <select id="exercise-timing" name="timing" class="form-input">
            <option value="">Todos</option>
            <option value="closing_soon" <?= ($filters['timing'] ?? '') === 'closing_soon' ? 'selected' : '' ?>>Fechando em breve</option>
          </select>
        </div>
      </div>
      <div class="td-actions">
        <button type="submit" class="btn btn--primary">Filtrar</button>
        <a href="<?= \Core\app_url('/admin/exercises/export' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/exercises/export.json' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--ghost">Limpar</a>
      </div>
    </form>
  </div>
</section>

<section class="card card--narrow">
  <div class="card-body">
    <form method="POST" action="<?= \Core\app_url('/admin/presets/exercises/save') ?>" class="form">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
      <input type="hidden" name="return_query" value="<?= \Core\View::e($exportQuery) ?>">
      <input type="hidden" name="search" value="<?= \Core\View::e($filters['search'] ?? '') ?>">
      <input type="hidden" name="status" value="<?= \Core\View::e($filters['status'] ?? '') ?>">
      <input type="hidden" name="timing" value="<?= \Core\View::e($filters['timing'] ?? '') ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="exercises-preset-name">Salvar preset atual</label>
          <input id="exercises-preset-name" type="text" name="preset_name" class="form-input" placeholder="Ex.: fechando em breve">
        </div>
        <div class="form-group" style="justify-content: flex-end;">
          <label class="form-label">Preset</label>
          <div class="td-actions">
            <button type="submit" class="btn btn--ghost">Salvar preset</button>
          </div>
        </div>
      </div>
    </form>
    <?php if (!empty($filterPresets)): ?>
      <div class="content-note">
        <strong>Presets salvos</strong>
        <div class="td-actions">
          <?php foreach ($filterPresets as $preset): ?>
            <a href="<?= \Core\app_url('/admin/exercises' . (!empty($preset['query']) ? '?' . $preset['query'] : '')) ?>" class="btn btn--sm btn--ghost"><?= \Core\View::e($preset['name'] ?? 'Preset') ?></a>
            <form method="POST" action="<?= \Core\app_url('/admin/presets/exercises/delete') ?>">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <input type="hidden" name="return_query" value="<?= \Core\View::e($exportQuery) ?>">
              <input type="hidden" name="preset_id" value="<?= \Core\View::e($preset['id'] ?? '') ?>">
              <button type="submit" class="btn btn--sm btn--ghost">Remover <?= \Core\View::e($preset['name'] ?? 'preset') ?></button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Total cadastrados</span>
    <strong class="overview-card__value"><?= $exerciseCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($exerciseCountBadgeVariant) ?>"><?= \Core\View::e($exerciseCountBadgeText) ?></span></span>
    <p class="overview-card__copy">Inclui rascunhos, exercícios prontos e publicações ativas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Publicados</span>
    <strong class="overview-card__value"><?= $activeExercises ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($activeExercisesBadgeVariant) ?>"><?= \Core\View::e($activeExercisesBadgeText) ?></span></span>
    <p class="overview-card__copy">Exercícios já ativos em pelo menos uma turma.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Prontos</span>
    <strong class="overview-card__value"><?= $readyExercises ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($readyExercisesBadgeVariant) ?>"><?= \Core\View::e($readyExercisesBadgeText) ?></span></span>
    <p class="overview-card__copy">Itens finalizados, aguardando publicação operacional.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Tentativas</span>
    <strong class="overview-card__value"><?= $attemptTotal ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($attemptTotalBadgeVariant) ?>"><?= \Core\View::e($attemptTotalBadgeText) ?></span></span>
    <p class="overview-card__copy">Submissões registradas em toda a plataforma.</p>
  </article>
</div>

<?php if ($draftExercises > 0): ?>
  <div class="alert alert--info">Há <?= $draftExercises ?> exercício(s) em rascunho no sistema.</div>
<?php endif; ?>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício encontrado.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista global de exercícios</h2>
        <p class="surface-copy">Leitura administrativa para acompanhar autoria, publicação, calendário e atividade de submissão.</p>
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
                  <input type="checkbox" data-select-all="admin-exercises-list" aria-label="Selecionar todos os exercícios da listagem">
                  Todos
                </label>
              </th>
              <th>Título</th>
              <th>Docente</th>
              <th>Turmas</th>
              <th>Abre</th>
              <th>Fecha</th>
              <th>Tentativas</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($exercises as $exercise): ?>
              <?php
              $isDraft = ($exercise['status'] ?? '') === 'draft';
              $isReady = ($exercise['status'] ?? '') === 'ready';
              $reviewStatus = (string) ($exercise['admin_review_status'] ?? 'approved');
              $isOpen = ($exercise['status'] ?? '') === 'active'
                && !empty($exercise['opens_at'])
                && !empty($exercise['closes_at'])
                && strtotime($exercise['opens_at']) <= $now
                && strtotime($exercise['closes_at']) >= $now;
              $isClosed = ($exercise['status'] ?? '') === 'active'
                && !empty($exercise['closes_at'])
                && strtotime($exercise['closes_at']) < $now;
              $batchState = ($exercise['status'] ?? '') === 'active' ? ($isClosed ? 'closed' : 'active') : 'inactive';
              $batchStateLabel = $batchState === 'active' ? 'publicados' : ($batchState === 'closed' ? 'encerrados' : 'não publicados');
              ?>
              <tr>
                <td><input type="checkbox" name="exercise_ids[]" value="<?= (int) ($exercise['id'] ?? 0) ?>" data-select-item="admin-exercises-list" data-item-state="<?= $batchState ?>" data-item-state-label="<?= $batchStateLabel ?>"></td>
                <td><strong><?= \Core\View::e($exercise['title']) ?></strong></td>
                <td><?= \Core\View::e($exercise['teacher_name'] ?? '—') ?></td>
                <td><?= \Core\View::e($exercise['turma_label'] ?? 'Pendente de finalização') ?></td>
                <td><?= !empty($exercise['opens_at']) ? date('d/m/Y H:i', strtotime($exercise['opens_at'])) : '—' ?></td>
                <td><?= !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime($exercise['closes_at'])) : '—' ?></td>
                <td><?= (int) ($exercise['attempt_count'] ?? 0) ?></td>
                <td>
                  <?php if ($isDraft): ?>
                    <span class="badge badge--warning">Rascunho</span>
                  <?php elseif ($isReady): ?>
                    <span class="badge badge--info">Pronto</span>
                  <?php elseif ($isClosed): ?>
                    <span class="badge badge--neutral">Encerrado</span>
                  <?php elseif ($isOpen): ?>
                    <span class="badge badge--success">Aberto</span>
                  <?php else: ?>
                    <span class="badge badge--info">Agendado</span>
                  <?php endif; ?>
                  <?php if ($reviewStatus === 'blocked'): ?>
                    <span class="badge badge--error">Bloqueado</span>
                  <?php elseif ($reviewStatus === 'flagged'): ?>
                    <span class="badge badge--warning">Sinalizado</span>
                  <?php endif; ?>
                </td>
                <td class="td-actions">
                  <a href="<?= \Core\app_url('/admin/exercises/' . $exercise['id'] . ($exportQuery !== '' ? '?return_query=' . urlencode($exportQuery) : '')) ?>" class="btn btn--sm">Detalhes</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="exercises-list-reopen-until">Reabrir até</label>
            <input id="exercises-list-reopen-until" type="datetime-local" name="reopen_until" class="form-input" value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>" min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
          </div>
          <div class="form-group" style="justify-content: flex-end;">
            <label class="form-label">Ações em lote</label>
            <div class="td-actions">
              <span class="selection-summary" data-selection-count="admin-exercises-list">0 selecionados</span>
              <span class="selection-summary" data-selection-breakdown="admin-exercises-list"></span>
              <span class="selection-summary" data-selection-compatibility="admin-exercises-list"></span>
              <button type="submit" formaction="<?= \Core\app_url('/admin/exercises/batch-close') ?>" class="btn btn--danger" data-requires-selection="admin-exercises-list" data-allowed-states="active,closed" onclick="return confirm('Encerrar os exercícios selecionados?');" disabled>Encerrar selecionados</button>
              <button type="submit" formaction="<?= \Core\app_url('/admin/exercises/batch-reopen') ?>" class="btn btn--primary" data-requires-selection="admin-exercises-list" data-allowed-states="active,closed" disabled>Reabrir selecionados</button>
            </div>
          </div>
        </div>
      </form>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
