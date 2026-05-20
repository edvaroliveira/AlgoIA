<?php $pageTitle = 'Exercícios'; ?>

<div class="page-header">
  <h1>Exercícios</h1>
  <a href="/teacher/exercises/create" class="btn btn--primary">+ Novo exercício</a>
</div>

<?php if (empty($exercises)): ?>
  <p class="empty-state">Nenhum exercício criado. <a href="/teacher/exercises/create">Criar o primeiro</a>.</p>
<?php else: ?>
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
        $now    = time();
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
            <a href="/teacher/exercises/<?= $ex['id'] ?>" class="btn btn--sm">Ver</a>
            <a href="/teacher/exercises/<?= $ex['id'] ?>/edit" class="btn btn--sm btn--ghost">Editar</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
