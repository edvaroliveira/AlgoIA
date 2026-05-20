<?php $pageTitle = 'Dashboard — Docente'; ?>

<div class="page-header">
  <h1>Dashboard</h1>
  <p>Bem-vindo, <?= \Core\View::e(\Core\Auth::user()['name']) ?>.</p>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value"><?= count($turmas) ?></div>
    <div class="stat-label">Turmas</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $totalExs ?></div>
    <div class="stat-label">Exercícios</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $openCount ?></div>
    <div class="stat-label">Abertos agora</div>
  </div>
  <div class="stat-card stat-card--warning">
    <div class="stat-value"><?= $pendingTotal ?></div>
    <div class="stat-label">Aprovações pendentes</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $activeTotal ?></div>
    <div class="stat-label">Alunos ativos</div>
  </div>
</div>

<?php if ($pendingTotal > 0): ?>
  <div class="alert alert--warning">
    Você tem <strong><?= $pendingTotal ?></strong> aluno(s) aguardando aprovação.
    <a href="/teacher/turmas">Revisar agora →</a>
  </div>
<?php endif; ?>

<div class="section">
  <div class="section-header">
    <h2>Exercícios recentes</h2>
    <a href="/teacher/exercises/create" class="btn btn--primary btn--sm">+ Novo exercício</a>
  </div>

  <?php if (empty($exercises)): ?>
    <p class="empty-state">Nenhum exercício criado ainda. <a href="/teacher/exercises/create">Criar o primeiro</a>.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Título</th>
          <th>Turma</th>
          <th>Abre</th>
          <th>Fecha</th>
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
            <td>
              <?php if ($closed): ?>
                <span class="badge badge--neutral">Encerrado</span>
              <?php elseif ($open): ?>
                <span class="badge badge--success">Aberto</span>
              <?php else: ?>
                <span class="badge badge--info">Agendado</span>
              <?php endif; ?>
            </td>
            <td><a href="/teacher/exercises/<?= $ex['id'] ?>" class="btn btn--sm">Ver</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><a href="/teacher/exercises">Ver todos →</a></p>
  <?php endif; ?>
</div>
