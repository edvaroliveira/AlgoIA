<?php
$pageTitle = 'Exercícios';
$exercises = $exercises ?? [];
$now = time();
$openExercises = count(array_filter($exercises, fn($exercise) => strtotime($exercise['opens_at']) <= $now && strtotime($exercise['closes_at']) >= $now));
$closedExercises = count(array_filter($exercises, fn($exercise) => strtotime($exercise['closes_at']) < $now));
$scheduledExercises = count($exercises) - $openExercises - $closedExercises;
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
    <span class="overview-card__label">Total publicados</span>
    <strong class="overview-card__value"><?= count($exercises) ?></strong>
    <p class="overview-card__copy">Todos os exercícios já configurados no ambiente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Abertos agora</span>
    <strong class="overview-card__value"><?= $openExercises ?></strong>
    <p class="overview-card__copy">Disponíveis neste instante para tentativas dos alunos.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Agendados ou encerrados</span>
    <strong class="overview-card__value"><?= $scheduledExercises + $closedExercises ?></strong>
    <p class="overview-card__copy">Itens fora da janela ativa e que exigem menos atenção imediata.</p>
  </article>
</div>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício criado. <a href="<?= \Core\app_url('/teacher/exercises/create') ?>">Criar o primeiro</a>.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Grade operacional</h2>
        <p class="surface-copy">Use esta lista para localizar rapidamente os itens que precisam de revisão ou edição.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Título</th>
            <th>Turma</th>
            <th>Abre</th>
            <th>Fecha</th>
            <th>Tentativas</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exercises as $ex):
            $open   = strtotime($ex['opens_at']) <= $now && strtotime($ex['closes_at']) >= $now;
            $closed = strtotime($ex['closes_at']) < $now;
          ?>
            <tr>
              <td><?= \Core\View::e($ex['title']) ?></td>
              <td><?= \Core\View::e($ex['turma_name']) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['opens_at'])) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['closes_at'])) ?></td>
              <td><?= $ex['max_attempts'] === '0' ? '∞' : $ex['max_attempts'] ?></td>
              <td>
                <?php if ($closed): ?>
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
