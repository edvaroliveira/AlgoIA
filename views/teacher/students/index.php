<?php $pageTitle = 'Alunos';
global $session; ?>

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
        <th>Turmas</th>
        <th>Status</th>
        <th>Cadastrado em</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
        <tr>
          <td><?= \Core\View::e($s['name']) ?></td>
          <td><?= \Core\View::e($s['email']) ?></td>
          <td><?= \Core\View::e($s['turma_names'] ?? '—') ?></td>
          <td>
            <?php match ($s['status']) {
              'active'   => print '<span class="badge badge--success">Ativo</span>',
              'pending'  => print '<span class="badge badge--warning">Pendente</span>',
              'inactive' => print '<span class="badge badge--neutral">Inativo</span>',
              default    => print '',
            }; ?>
          </td>
          <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
          <td class="td-actions">
            <form method="POST" action="<?= \Core\app_url('/teacher/students/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Excluir este aluno e todos os registros dele?');">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <button type="submit" class="btn btn--danger btn--sm">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
