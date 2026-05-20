<?php $pageTitle = \Core\View::e($exercise['title']);
global $session; ?>

<div class="page-header">
  <h1><?= \Core\View::e($exercise['title']) ?></h1>
  <a href="<?= \Core\app_url('/student/dashboard') ?>" class="btn btn--ghost">← Dashboard</a>
</div>

<div class="card card--meta">
  <div class="meta-grid">
    <div><strong>Turma:</strong> <?= \Core\View::e($exercise['turma_name']) ?></div>
    <div><strong>Abre:</strong> <?= date('d/m/Y H:i', strtotime($exercise['opens_at'])) ?></div>
    <div><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($exercise['closes_at'])) ?></div>
    <div><strong>Tentativas:</strong> <?= $exercise['max_attempts'] === '0' ? 'Ilimitadas' : $exercise['max_attempts'] ?></div>
    <div><strong>Suas tentativas:</strong> <?= $attCount ?></div>
    <?php if ($bestScore !== null): ?>
      <div><strong>Melhor nota:</strong> <?= number_format((float) $bestScore, 1) ?> pts</div>
    <?php endif; ?>
  </div>
  <?php if ($exercise['description']): ?>
    <p class="mt-1"><?= nl2br(\Core\View::e($exercise['description'])) ?></p>
  <?php endif; ?>
</div>

<?php
$flash = $session->getFlash('error');
if ($flash):
?>
  <div class="alert alert--error"><?= \Core\View::e($flash) ?></div>
<?php endif; ?>

<?php if (!$isOpen && !$exercise['closes_at']): ?>
  <div class="alert alert--info">Este exercício ainda não está aberto.</div>

<?php elseif ($exercise['closes_at'] && strtotime($exercise['closes_at']) < time()): ?>
  <div class="alert alert--neutral">Este exercício foi encerrado.</div>

<?php elseif ($canAttempt && $isOpen): ?>
  <form method="POST" action="<?= \Core\app_url('/student/exercises/' . $exercise['id'] . '/start') ?>">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <?php if ($inProgress): ?>
      <p class="alert alert--info">Você tem uma tentativa em andamento iniciada em <?= date('d/m H:i', strtotime($inProgress['started_at'])) ?>.</p>
      <a href="<?= \Core\app_url('/student/exercises/' . $exercise['id'] . '?attempt=' . $inProgress['id']) ?>" class="btn btn--primary">Continuar tentativa</a>
    <?php else: ?>
      <button type="submit" class="btn btn--primary btn--lg">Iniciar tentativa</button>
    <?php endif; ?>
  </form>

<?php elseif (!$canAttempt): ?>
  <div class="alert alert--neutral">Você atingiu o número máximo de tentativas.</div>
<?php endif; ?>

<!-- Tentativas anteriores -->
<?php if (!empty($attempts)): ?>
  <div class="section">
    <h2>Suas tentativas</h2>
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Iniciada em</th>
          <th>Submetida em</th>
          <th>Nota</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($attempts as $i => $att): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= date('d/m/Y H:i', strtotime($att['started_at'])) ?></td>
            <td><?= $att['submitted_at'] ? date('d/m/Y H:i', strtotime($att['submitted_at'])) : '—' ?></td>
            <td><?= $att['total_score'] !== null ? number_format((float) $att['total_score'], 1) . ' pts' : '—' ?></td>
            <td>
              <?php if ($att['status'] === 'graded'): ?>
                <a href="<?= \Core\app_url('/student/attempts/' . $att['id'] . '/result') ?>" class="btn btn--sm">Ver resultado</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Responder questões (quando há tentativa em andamento via ?attempt=) -->
<?php if ($attemptId && !empty($attemptQuestions)): ?>
  <div class="section">
    <h2>Responder questões</h2>

    <form id="attempt-form" method="POST" action="<?= \Core\app_url('/student/attempts/' . $attemptId . '/submit') ?>">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

      <?php foreach ($attemptQuestions as $i => $q):
        $saved = $savedAnswers[(int) $q['id']] ?? null;
      ?>
        <div class="question-card">
          <div class="question-header">
            <span class="question-num">Q<?= $i + 1 ?></span>
            <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
          </div>
          <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
          <textarea
            class="form-input form-textarea"
            name="answer_<?= $q['id'] ?>"
            data-question="<?= $q['id'] ?>"
            data-attempt="<?= $attemptId ?>"
            data-csrf="<?= \Core\View::e($session->csrfToken()) ?>"
            data-save-url="<?= \Core\app_url('/student/attempts/' . $attemptId . '/answer') ?>"
            rows="5"
            placeholder="Digite sua resposta aqui..."
            required><?= \Core\View::e($saved['student_answer'] ?? '') ?></textarea>
          <span class="autosave-status" id="status-<?= $q['id'] ?>"></span>
        </div>
      <?php endforeach; ?>

      <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--lg"
          onclick="return confirm('Submeter o exercício? Esta ação não pode ser desfeita.');">
          Submeter exercício
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>
