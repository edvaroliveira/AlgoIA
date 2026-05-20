<?php $pageTitle = \Core\View::e($exercise['title']);
global $session; ?>

<div class="page-header">
  <h1><?= \Core\View::e($exercise['title']) ?></h1>
  <div class="header-actions">
    <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/edit') ?>" class="btn btn--ghost">Editar</a>
    <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="btn btn--ghost">← Exercícios</a>
  </div>
</div>

<!-- Meta -->
<div class="card card--meta">
  <div class="meta-grid">
    <div><strong>Turma:</strong> <?= \Core\View::e($exercise['turma_name']) ?></div>
    <div><strong>Abre:</strong> <?= date('d/m/Y H:i', strtotime($exercise['opens_at'])) ?></div>
    <div><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($exercise['closes_at'])) ?></div>
    <div><strong>Tentativas:</strong> <?= $exercise['max_attempts'] === '0' ? 'Ilimitadas' : $exercise['max_attempts'] ?></div>
    <div><strong>Pontuação total:</strong> <?= number_format((float) $maxScore, 1) ?> pts</div>
  </div>
  <?php if ($exercise['description']): ?>
    <p class="mt-1"><?= \Core\View::e($exercise['description']) ?></p>
  <?php endif; ?>
</div>

<!-- Questões -->
<div class="section">
  <div class="section-header">
    <h2>Questões (<?= count($questions) ?>)</h2>
    <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary btn--sm">+ Questão</a>
  </div>

  <?php if (empty($questions)): ?>
    <p class="empty-state">Nenhuma questão adicionada. <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>">Adicionar</a>.</p>
  <?php else: ?>
    <?php foreach ($questions as $i => $q): ?>
      <div class="question-card">
        <div class="question-header">
          <span class="question-num">Q<?= $i + 1 ?></span>
          <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
        </div>
        <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
        <details class="hint-details">
          <summary>Ver gabarito esperado</summary>
          <p class="hint-text"><?= nl2br(\Core\View::e($q['expected_answer_hint'])) ?></p>
        </details>
        <form method="POST" action="<?= \Core\app_url('/teacher/questions/' . $q['id'] . '/delete') ?>"
          class="inline-form"
          onsubmit="return confirm('Excluir esta questão?');">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <button class="btn btn--danger btn--sm">Excluir</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Resultados -->
<div class="section">
  <h2>Resultados dos alunos</h2>
  <?php if (empty($results)): ?>
    <p class="empty-state">Nenhuma submissão ainda.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Aluno</th>
          <th>E-mail</th>
          <th>Melhor nota</th>
          <th>Tentativas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= \Core\View::e($r['name']) ?></td>
            <td><?= \Core\View::e($r['email']) ?></td>
            <td><?= number_format((float) $r['best_score'], 1) ?> / <?= number_format((float) $maxScore, 1) ?></td>
            <td><?= $r['attempt_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Zona de perigo -->
<div class="section">
  <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/delete') ?>"
    onsubmit="return confirm('Excluir este exercício e todos os seus dados?');">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <button type="submit" class="btn btn--danger">Excluir exercício</button>
  </form>
</div>
