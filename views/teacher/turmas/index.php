<?php $pageTitle = 'Turmas'; ?>

<div class="page-header">
  <h1>Turmas</h1>
  <a href="<?= \Core\app_url('/teacher/turmas/create') ?>" class="btn btn--primary">+ Nova Turma</a>
</div>

<?php if (empty($turmas)): ?>
  <p class="empty-state">Nenhuma turma criada. <a href="<?= \Core\app_url('/teacher/turmas/create') ?>">Criar a primeira</a>.</p>
<?php else: ?>
  <div class="cards-grid">
    <?php foreach ($turmas as $t): ?>
      <div class="card">
        <div class="card-header">
          <h3><?= \Core\View::e($t['name']) ?></h3>
          <?php if ($t['pending_count'] > 0): ?>
            <span class="badge badge--warning"><?= $t['pending_count'] ?> pendente(s)</span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <p>
            <strong>Chave:</strong>
            <code class="access-key"><?= \Core\View::e($t['access_key']) ?></code>
          </p>
          <p><strong>Alunos ativos:</strong> <?= $t['active_count'] ?></p>
        </div>
        <div class="card-footer">
          <a href="<?= \Core\app_url('/teacher/turmas/' . $t['id']) ?>" class="btn btn--sm">Gerenciar</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
