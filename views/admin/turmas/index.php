<?php
$pageTitle = 'Turmas — Administração';
$turmas = $turmas ?? [];
$activeTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['active_count'] ?? 0), $turmas));
$pendingTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['pending_count'] ?? 0), $turmas));
$exerciseTotal = array_sum(array_map(static fn(array $turma): int => (int) ($turma['exercise_count'] ?? 0), $turmas));
?>

<div class="page-header">
  <div>
    <h1>Turmas do sistema</h1>
    <p class="subtitle">Visão global inicial das turmas com docente responsável, volume de alunos e carga de exercícios publicada.</p>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Turmas</span>
    <strong class="overview-card__value"><?= count($turmas) ?></strong>
    <p class="overview-card__copy">Estruturas acadêmicas atualmente cadastradas na base.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos ativos</span>
    <strong class="overview-card__value"><?= $activeTotal ?></strong>
    <p class="overview-card__copy">Matrículas aprovadas em todas as turmas monitoradas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendências</span>
    <strong class="overview-card__value"><?= $pendingTotal ?></strong>
    <p class="overview-card__copy">Solicitações de entrada aguardando decisão docente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Exercícios publicados</span>
    <strong class="overview-card__value"><?= $exerciseTotal ?></strong>
    <p class="overview-card__copy">Publicações vinculadas às turmas em toda a plataforma.</p>
  </article>
</div>

<?php if (empty($turmas)): ?>
  <p class="empty-state">Nenhuma turma encontrada.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista global de turmas</h2>
        <p class="surface-copy">Leitura administrativa para supervisão de volume, responsabilidade docente e sinais operacionais básicos.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Turma</th>
            <th>Docente</th>
            <th>Chave</th>
            <th>Ativos</th>
            <th>Pendentes</th>
            <th>Exercícios</th>
            <th>Situação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($turmas as $turma): ?>
            <?php
            $pendingCount = (int) ($turma['pending_count'] ?? 0);
            $activeCount = (int) ($turma['active_count'] ?? 0);
            $exerciseCount = (int) ($turma['exercise_count'] ?? 0);
            ?>
            <tr>
              <td><strong><?= \Core\View::e($turma['name']) ?></strong></td>
              <td><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></td>
              <td><span class="overview-card__value overview-card__value--mono"><?= \Core\View::e($turma['access_key']) ?></span></td>
              <td><?= $activeCount ?></td>
              <td><?= $pendingCount ?></td>
              <td><?= $exerciseCount ?></td>
              <td>
                <?php if (!(bool) ($turma['active'] ?? true)): ?>
                  <span class="badge badge--neutral">Inativa</span>
                <?php elseif ($pendingCount > 0): ?>
                  <span class="badge badge--warning">Com pendências</span>
                <?php else: ?>
                  <span class="badge badge--success">Operação normal</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
