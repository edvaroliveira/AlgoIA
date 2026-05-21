<?php
$pageTitle = 'Exercícios';
$exercises = $exercises ?? [];
$now = time();
$activeExercises = count(array_filter($exercises, fn($exercise) => ($exercise['status'] ?? 'active') === 'active'));
$draftExercises = count(array_filter($exercises, fn($exercise) => ($exercise['status'] ?? 'active') === 'draft'));
$openExercises = count(array_filter($exercises, fn($exercise) => ($exercise['status'] ?? 'active') === 'active' && strtotime($exercise['opens_at']) <= $now && strtotime($exercise['closes_at']) >= $now));
?>

<div class="page-header">
  <div>
    <h1>Biblioteca de exercícios</h1>
    <p class="subtitle">Gerencie aberturas, fechamentos e acesso por turma em uma visão única.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/exercises/create') ?>" class="btn btn--primary">+ Novo exercício</a>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Total cadastrados</span>
    <strong class="overview-card__value"><?= count($exercises) ?></strong>
    <p class="overview-card__copy">Inclui rascunhos pendentes de finalização e atividades já ativas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Ativos agora</span>
    <strong class="overview-card__value"><?= $activeExercises ?></strong>
    <p class="overview-card__copy">Exercícios já finalizados, com pelo menos uma turma vinculada.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendentes de finalização</span>
    <strong class="overview-card__value"><?= $draftExercises ?></strong>
    <p class="overview-card__copy">Rascunhos que ainda precisam de questões completas e ativação para turmas.</p>
  </article>
</div>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício criado. <a href="<?= \Core\app_url('/teacher/exercises/create') ?>">Criar o primeiro</a>.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Grade operacional</h2>
        <p class="surface-copy">Use esta lista para localizar rapidamente rascunhos pendentes, exercícios ativos e ajustes de publicação.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Título</th>
            <th>Turmas</th>
            <th>Abre</th>
            <th>Fecha</th>
            <th>Tentativas</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exercises as $ex):
            $isDraft = ($ex['status'] ?? 'active') === 'draft';
            $open   = strtotime($ex['opens_at']) <= $now && strtotime($ex['closes_at']) >= $now;
            $closed = strtotime($ex['closes_at']) < $now;
          ?>
            <tr>
              <td><?= \Core\View::e($ex['title']) ?></td>
              <td><?= \Core\View::e($ex['turma_label'] ?? 'Pendente de finalização') ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['opens_at'])) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['closes_at'])) ?></td>
              <td><?= $ex['max_attempts'] === '0' ? '∞' : $ex['max_attempts'] ?></td>
              <td>
                <?php if ($isDraft): ?>
                  <span class="badge badge--warning">Pendente</span>
                <?php elseif ($closed): ?>
                  <span class="badge badge--neutral">Encerrado</span>
                <?php elseif ($open): ?>
                  <span class="badge badge--success">Aberto</span>
                <?php else: ?>
                  <span class="badge badge--info">Agendado</span>
                <?php endif; ?>
              </td>
              <td class="td-actions">
                <a href="<?= \Core\app_url('/teacher/exercises/' . $ex['id']) ?>" class="btn btn--sm">Ver</a>
                <a href="<?= \Core\app_url('/teacher/exercises/' . $ex['id'] . '/edit') ?>" class="btn btn--sm btn--ghost">Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
