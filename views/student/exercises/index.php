<?php
$pageTitle = 'Exercícios';
$exercises = $exercises ?? [];
$openExercises = count(array_filter($exercises, fn($exercise) => !empty($exercise['is_open'])));
$closedExercises = count(array_filter($exercises, fn($exercise) => !empty($exercise['is_closed'])));
$gradedExercises = count(array_filter($exercises, fn($exercise) => $exercise['best_score'] !== null));
?>

<div class="page-header">
  <div>
    <h1>Seus exercícios</h1>
    <p class="subtitle">Filtre mentalmente o que está aberto, o que já foi concluído e o que ainda vem pela frente.</p>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Disponíveis agora</span>
    <strong class="overview-card__value"><?= $openExercises ?></strong>
    <p class="overview-card__copy">Prontos para acesso imediato dentro da janela definida pelo docente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Com nota registrada</span>
    <strong class="overview-card__value"><?= $gradedExercises ?></strong>
    <p class="overview-card__copy">Exercícios em que você já possui desempenho consolidado.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Encerrados</span>
    <strong class="overview-card__value"><?= $closedExercises ?></strong>
    <p class="overview-card__copy">Itens fora da janela de envio, úteis para consulta e revisão.</p>
  </article>
</div>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício disponível para você ainda.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Linha do tempo dos exercícios</h2>
        <p class="surface-copy">Consulte status, janela de entrega e sua melhor nota em uma grade única.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Exercício</th>
            <th>Turma</th>
            <th>Abre</th>
            <th>Fecha</th>
            <th>Status</th>
            <th>Melhor nota</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exercises as $ex): ?>
            <tr>
              <td><?= \Core\View::e($ex['title']) ?></td>
              <td><?= \Core\View::e($ex['turma_label'] ?? $ex['turma_name']) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['opens_at'])) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($ex['closes_at'])) ?></td>
              <td>
                <?php if ($ex['is_closed']): ?>
                  <span class="badge badge--neutral">Encerrado</span>
                <?php elseif ($ex['is_open']): ?>
                  <span class="badge badge--success">Aberto</span>
                <?php else: ?>
                  <span class="badge badge--info">Em breve</span>
                <?php endif; ?>
              </td>
              <td>
                <?= $ex['best_score'] !== null
                  ? number_format((float) $ex['best_score'], 1) . ' pts'
                  : '—' ?>
              </td>
              <td>
                <a href="<?= \Core\app_url('/student/exercises/' . $ex['id']) ?>" class="btn btn--sm">Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
