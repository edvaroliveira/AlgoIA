<?php $pageTitle = 'Turma — ' . ($turma['name'] ?? '');
global $session; ?>

<div class="page-header">
  <h1><?= \Core\View::e($turma['name']) ?></h1>
  <a href="/teacher/turmas" class="btn btn--ghost">← Turmas</a>
</div>

<div class="card card--key">
  <p>Chave de acesso para os alunos:</p>
  <div class="big-key"><?= \Core\View::e($turma['access_key']) ?></div>
  <form method="POST" action="/teacher/turmas/<?= $turma['id'] ?>/key"
    onsubmit="return confirm('Gerar nova chave? A chave atual deixará de funcionar para novos cadastros.');">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <button type="submit" class="btn btn--ghost btn--sm">↻ Gerar nova chave</button>
  </form>
</div>

<!-- Pendentes -->
<?php if (!empty($pending)): ?>
  <div class="section">
    <h2>Aguardando aprovação <span class="badge badge--warning"><?= count($pending) ?></span></h2>
    <table class="table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Solicitado em</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $s): ?>
          <tr>
            <td><?= \Core\View::e($s['name']) ?></td>
            <td><?= \Core\View::e($s['email']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($s['joined_at'])) ?></td>
            <td class="td-actions">
              <form method="POST" action="/teacher/turmas/<?= $turma['id'] ?>/approve/<?= $s['id'] ?>" style="display:inline">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button class="btn btn--success btn--sm">Aprovar</button>
              </form>
              <form method="POST" action="/teacher/turmas/<?= $turma['id'] ?>/reject/<?= $s['id'] ?>"
                style="display:inline"
                onsubmit="return confirm('Rejeitar este aluno?');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button class="btn btn--danger btn--sm">Rejeitar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Alunos ativos -->
<div class="section">
  <h2>Alunos ativos <span class="badge badge--neutral"><?= count($students) ?></span></h2>
  <?php if (empty($students)): ?>
    <p class="empty-state">Nenhum aluno ativo ainda.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>E-mail</th>
          <th>Ingressou em</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><?= \Core\View::e($s['name']) ?></td>
            <td><?= \Core\View::e($s['email']) ?></td>
            <td><?= date('d/m/Y', strtotime($s['joined_at'])) ?></td>
            <td>
              <form method="POST" action="/teacher/turmas/<?= $turma['id'] ?>/reject/<?= $s['id'] ?>"
                onsubmit="return confirm('Remover este aluno da turma?');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button class="btn btn--danger btn--sm">Remover</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
