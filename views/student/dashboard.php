<?php $pageTitle = 'Dashboard';
global $session; ?>

<div class="page-header">
  <h1>Olá, <?= \Core\View::e(\Core\Auth::user()['name']) ?>!</h1>
</div>

<!-- Turmas -->
<?php
$pendingTurmas = array_filter($turmas, fn($t) => $t['enrollment_status'] === 'pending');
?>
<?php if (!empty($pendingTurmas)): ?>
  <div class="alert alert--warning">
    Você está aguardando aprovação em <?= count($pendingTurmas) ?> turma(s).
    O docente será notificado.
  </div>
<?php endif; ?>

<!-- Exercícios disponíveis -->
<div class="section">
  <h2>Disponíveis agora</h2>
  <?php if (empty($available)): ?>
    <p class="empty-state">Nenhum exercício disponível no momento.</p>
  <?php else: ?>
    <div class="cards-grid">
      <?php foreach ($available as $ex):
        $closesTs  = strtotime($ex['closes_at']);
        $remaining = $closesTs - time();
        $hours     = floor($remaining / 3600);
        $mins      = floor(($remaining % 3600) / 60);
      ?>
        <div class="card card--exercise">
          <div class="card-header">
            <h3><?= \Core\View::e($ex['title']) ?></h3>
            <span class="badge badge--success">Aberto</span>
          </div>
          <div class="card-body">
            <p class="turma-tag">📚 <?= \Core\View::e($ex['turma_name']) ?></p>
            <p class="deadline">
              ⏱ Fecha em
              <?= $hours > 0 ? "{$hours}h {$mins}min" : "{$mins}min" ?>
            </p>
          </div>
          <div class="card-footer">
            <a href="/student/exercises/<?= $ex['id'] ?>" class="btn btn--primary btn--sm">Acessar</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Histórico -->
<div class="section">
  <div class="section-header">
    <h2>Todos os exercícios</h2>
    <a href="/student/exercises" class="btn btn--ghost btn--sm">Ver todos</a>
  </div>
  <?php
  $done = array_filter($all, fn($ex) => isset($ex['best_score']) && $ex['best_score'] !== null);
  ?>
  <?php if (empty($done)): ?>
    <p class="empty-state">Nenhum exercício concluído ainda.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Exercício</th>
          <th>Turma</th>
          <th>Melhor nota</th>
          <th>Tentativas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($done as $ex): ?>
          <tr>
            <td><a href="/student/exercises/<?= $ex['id'] ?>"><?= \Core\View::e($ex['title']) ?></a></td>
            <td><?= \Core\View::e($ex['turma_name']) ?></td>
            <td><?= number_format((float) $ex['best_score'], 1) ?></td>
            <td><?= $ex['attempt_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Entrar em nova turma -->
<div class="card card--narrow">
  <h3>Entrar em outra turma</h3>
  <form method="POST" action="/student/turma/join" class="form form--inline">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <input class="form-input form-input--key" type="text" name="turma_key"
      maxlength="6" placeholder="CHAVE" style="text-transform:uppercase" required>
    <button type="submit" class="btn btn--primary">Solicitar ingresso</button>
  </form>
</div>
