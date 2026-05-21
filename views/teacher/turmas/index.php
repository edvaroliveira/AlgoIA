<?php
$pageTitle = 'Turmas';
$turmas = $turmas ?? [];
$pendingTotal = array_sum(array_map(fn($turma) => (int) ($turma['pending_count'] ?? 0), $turmas));
$activeTotal = array_sum(array_map(fn($turma) => (int) ($turma['active_count'] ?? 0), $turmas));
?>

<div class="page-header">
  <div>
    <h1>Turmas vinculadas ao seu docente</h1>
    <p class="subtitle">Controle acesso, acompanhe aprovações e navegue para a gestão detalhada de cada grupo.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/turmas/create') ?>" class="btn btn--primary">+ Nova Turma</a>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Turmas criadas</span>
    <strong class="overview-card__value"><?= count($turmas) ?></strong>
    <p class="overview-card__copy">Conjuntos acadêmicos atualmente sob sua gestão direta.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos ativos</span>
    <strong class="overview-card__value"><?= $activeTotal ?></strong>
    <p class="overview-card__copy">Estudantes já aprovados e com participação regular nas turmas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Aprovações pendentes</span>
    <strong class="overview-card__value"><?= $pendingTotal ?></strong>
    <p class="overview-card__copy">Solicitações que exigem decisão sua nas páginas de gestão das turmas.</p>
  </article>
</div>

<?php if (empty($turmas)): ?>
  <p class="empty-state">Nenhuma turma criada. <a href="<?= \Core\app_url('/teacher/turmas/create') ?>">Criar a primeira</a>.</p>
<?php else: ?>
  <div class="cards-grid cards-grid--feature">
    <?php foreach ($turmas as $t): ?>
      <article class="card card--feature">
        <div class="card-header card-header--split">
          <div>
            <h3><?= \Core\View::e($t['name']) ?></h3>
            <p class="card-subtitle">Chave operacional e fluxo de matrícula em um só ponto.</p>
          </div>
          <?php if ($t['pending_count'] > 0): ?>
            <span class="badge badge--warning"><?= $t['pending_count'] ?> pendente(s)</span>
          <?php else: ?>
            <span class="badge badge--success">Fluxo normal</span>
          <?php endif; ?>
        </div>
        <div class="card-body card-body--stack">
          <div class="key-strip">
            <span class="key-strip__label">Chave da turma</span>
            <code class="access-key"><?= \Core\View::e($t['access_key']) ?></code>
          </div>
          <div class="mini-stats">
            <div class="mini-stats__item">
              <strong><?= (int) $t['active_count'] ?></strong>
              <span>ativos</span>
            </div>
            <div class="mini-stats__item">
              <strong><?= (int) $t['pending_count'] ?></strong>
              <span>pendentes</span>
            </div>
          </div>
        </div>
        <div class="card-footer card-footer--actions">
          <a href="<?= \Core\app_url('/teacher/turmas/' . $t['id']) ?>" class="btn btn--sm">Gerenciar turma</a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
