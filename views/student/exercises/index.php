<?php $pageTitle = 'Exercícios'; ?>

<div class="page-header">
  <h1>Todos os Exercícios</h1>
</div>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício disponível para você ainda.</p>
<?php else: ?>
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
          <td><?= \Core\View::e($ex['turma_name']) ?></td>
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
            <a href="/student/exercises/<?= $ex['id'] ?>" class="btn btn--sm">Ver</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
