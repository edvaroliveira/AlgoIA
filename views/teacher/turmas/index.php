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
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista operacional de turmas</h2>
        <p class="surface-copy">Visualização compacta para localizar turmas, acompanhar capacidade operacional e abrir a gestão detalhada sem expandir a página em excesso.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Turma</th>
            <th>Chave</th>
            <th>Ativos</th>
            <th>Pendentes</th>
            <th>Situação</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($turmas as $t): ?>
            <tr>
              <td>
                <strong><?= \Core\View::e($t['name']) ?></strong>
              </td>
              <td><span class="overview-card__value overview-card__value--mono"><?= \Core\View::e($t['access_key']) ?></span></td>
              <td><?= (int) $t['active_count'] ?></td>
              <td><?= (int) $t['pending_count'] ?></td>
              <td>
                <?php if ($t['pending_count'] > 0): ?>
                  <span class="badge badge--warning"><?= (int) $t['pending_count'] ?> pendente(s)</span>
                <?php else: ?>
                  <span class="badge badge--success">Fluxo normal</span>
                <?php endif; ?>
              </td>
              <td class="td-actions">
                <a href="<?= \Core\app_url('/teacher/turmas/' . $t['id']) ?>" class="btn btn--sm">Gerenciar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
