<?php $pageTitle = 'Questões — ' . \Core\View::e($exercise['title']);
global $session; ?>

<div class="page-header">
  <h1>Questões do exercício</h1>
  <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">← Voltar ao exercício</a>
</div>

<p class="subtitle">Exercício: <strong><?= \Core\View::e($exercise['title']) ?></strong></p>

<!-- Lista atual -->
<?php if (!empty($questions)): ?>
  <div class="section">
    <h2>Questões adicionadas (<?= count($questions) ?>)</h2>
    <?php foreach ($questions as $i => $q): ?>
      <div class="question-card">
        <div class="question-header">
          <span class="question-num">Q<?= $i + 1 ?></span>
          <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
        </div>
        <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
        <form method="POST" action="<?= \Core\app_url('/teacher/questions/' . $q['id'] . '/delete') ?>"
          class="inline-form"
          onsubmit="return confirm('Excluir esta questão?');">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <button class="btn btn--danger btn--sm">Excluir</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Adicionar nova -->
<div class="card card--narrow">
  <h2>Adicionar questão</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert--error">
      <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php
  global $session;
  $flash = $session->getFlash('success');
  if ($flash): ?>
    <div class="alert alert--success"><?= \Core\View::e($flash) ?></div>
  <?php endif; ?>

  <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions') ?>" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <input type="hidden" name="order_index" value="<?= $nextOrder ?>">

    <div class="form-group">
      <label class="form-label" for="text">Enunciado da questão</label>
      <textarea class="form-input form-textarea" id="text" name="text"
        rows="4" required
        placeholder="Descreva a questão de forma clara e objetiva."><?= \Core\View::e($old['text'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="expected_answer_hint">
        Conceitos esperados / Gabarito
        <span class="hint">
          (usado pela IA para corrigir — não será exibido ao aluno durante o exercício)
        </span>
      </label>
      <textarea class="form-input form-textarea" id="expected_answer_hint" name="expected_answer_hint"
        rows="4" required
        placeholder="Descreva os conceitos, passos ou resposta esperada."><?= \Core\View::e($old['hint'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="max_score">Pontuação máxima (0–10)</label>
      <input class="form-input form-input--short" type="number" id="max_score" name="max_score"
        value="<?= \Core\View::e($old['maxScore'] ?? 10) ?>"
        min="0" max="10" step="0.5" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Adicionar questão</button>
    </div>
  </form>
</div>
