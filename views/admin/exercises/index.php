<?php
$pageTitle = 'Exercícios — Administração';
$exercises = $exercises ?? [];
$filters = $filters ?? ['search' => '', 'status' => ''];
$pagination = $pagination ?? ['totalPages' => 1, 'currentPage' => 1, 'totalItems' => count($exercises), 'path' => '/admin/exercises', 'query' => $filters];
$exportQuery = http_build_query(array_filter($filters, static fn($value): bool => (string) $value !== ''));
$now = time();
$activeExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'active'));
$draftExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'draft'));
$readyExercises = count(array_filter($exercises, static fn(array $exercise): bool => ($exercise['status'] ?? '') === 'ready'));
$attemptTotal = array_sum(array_map(static fn(array $exercise): int => (int) ($exercise['attempt_count'] ?? 0), $exercises));
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
      </div>
      <div class="td-actions">
        <button type="submit" class="btn btn--primary">Filtrar</button>
        <a href="<?= \Core\app_url('/admin/exercises/export' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--ghost">Limpar</a>
      </div>
    </form>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Total cadastrados</span>
    <strong class="overview-card__value"><?= count($exercises) ?></strong>
    <p class="overview-card__copy">Inclui rascunhos, exercícios prontos e publicações ativas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Publicados</span>
    <strong class="overview-card__value"><?= $activeExercises ?></strong>
    <p class="overview-card__copy">Exercícios já ativos em pelo menos uma turma.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Prontos</span>
    <strong class="overview-card__value"><?= $readyExercises ?></strong>
    <p class="overview-card__copy">Itens finalizados, aguardando publicação operacional.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Tentativas</span>
    <strong class="overview-card__value"><?= $attemptTotal ?></strong>
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
      <table class="table">
        <thead>
          <tr>
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
            $isOpen = ($exercise['status'] ?? '') === 'active'
              && !empty($exercise['opens_at'])
              && !empty($exercise['closes_at'])
              && strtotime($exercise['opens_at']) <= $now
              && strtotime($exercise['closes_at']) >= $now;
            $isClosed = ($exercise['status'] ?? '') === 'active'
              && !empty($exercise['closes_at'])
              && strtotime($exercise['closes_at']) < $now;
            ?>
            <tr>
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
              </td>
              <td class="td-actions">
                <a href="<?= \Core\app_url('/admin/exercises/' . $exercise['id']) ?>" class="btn btn--sm">Detalhes</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
