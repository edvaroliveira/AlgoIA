<?php $pageTitle = 'Alunos'; ?>

<div class="page-header">
  <h1>Todos os Alunos</h1>
</div>

<?php if (empty($students)): ?>
  <p class="empty-state">Nenhum aluno cadastrado ainda.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Nome</th>
        <th>E-mail</th>
        <th>Status</th>
        <th>Cadastrado em</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td><?= \Core\View::e($s['name']) ?></td>
          <td><?= \Core\View::e($s['email']) ?></td>
          <td>
            <?php match ($s['status']) {
              'active'   => print '<span class="badge badge--success">Ativo</span>',
              'pending'  => print '<span class="badge badge--warning">Pendente</span>',
              'inactive' => print '<span class="badge badge--neutral">Inativo</span>',
              default    => print '',
            }; ?>
          </td>
          <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
