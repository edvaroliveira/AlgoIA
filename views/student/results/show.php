<?php
$attempt = $attempt ?? [];
$bestScore = $bestScore ?? null;
$maxScore = $maxScore ?? 0;
$answers = $answers ?? [];
$showReferenceAnswer = $showReferenceAnswer ?? false;
$internalReview = $internalReview ?? false;
$canReviewScores = $canReviewScores ?? false;
$scoreReviews = $scoreReviews ?? [];
global $session;
$resultBackUrl = $resultBackUrl ?? '/student/exercises/' . ($attempt['exercise_id'] ?? 0);
$resultAnswerLabel = $resultAnswerLabel ?? 'Sua resposta:';
$pageTitle = 'Resultado — ' . ($attempt['exercise_title'] ?? 'Exercício');
$isBest    = $bestScore !== null
  && isset($attempt['total_score'])
  && abs((float) $attempt['total_score'] - (float) $bestScore) < 0.01;
?>

<div class="page-header">
  <div>
    <h1>Resultado da tentativa</h1>
    <p class="subtitle">Leitura detalhada da correção, com feedback da IA e referência esperada quando liberada.</p>
  </div>
  <a href="<?= \Core\app_url($resultBackUrl) ?>" class="btn btn--ghost">← Exercício</a>
</div>

<div class="result-summary <?= $isBest ? 'result-summary--best' : '' ?>">
  <div class="score-ring"
       data-score="<?= (float) $attempt['total_score'] ?>"
       data-max="<?= (float) $maxScore ?>"
       role="img"
       aria-label="Nota <?= number_format((float) $attempt['total_score'], 1) ?> de <?= number_format((float) $maxScore, 1) ?> pontos">
    <div class="score-ring__inner">
      <span class="score-ring__value"><?= number_format((float) $attempt['total_score'], 1) ?></span>
      <span class="score-ring__max">/ <?= number_format((float) $maxScore, 1) ?> pts</span>
    </div>
  </div>
  <p class="result-title"><?= \Core\View::e($attempt['exercise_title']) ?></p>
  <p class="result-meta">
    Submetido em <?= date('d/m/Y H:i', strtotime($attempt['submitted_at'])) ?>
    <?php if ($isBest): ?> · <strong>Sua melhor tentativa!</strong><?php endif; ?>
  </p>
</div>

<div class="overview-grid overview-grid--compact">
  <article class="overview-card">
    <span class="overview-card__label">Pontuação obtida</span>
    <strong class="overview-card__value"><?= number_format((float) $attempt['total_score'], 1) ?></strong>
    <p class="overview-card__copy">Resultado total consolidado desta submissão.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Valor máximo</span>
    <strong class="overview-card__value"><?= number_format((float) $maxScore, 1) ?></strong>
    <p class="overview-card__copy">Soma máxima possível considerando todas as questões.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Melhor marca</span>
    <strong class="overview-card__value"><?= $bestScore !== null ? number_format((float) $bestScore, 1) : '—' ?></strong>
    <p class="overview-card__copy">Sua referência atual neste exercício.</p>
  </article>
</div>

<!-- Respostas -->
<div class="section">
  <h2>Detalhes por questão</h2>

  <?php foreach ($answers as $i => $ans):
    $correct = isset($ans['ai_score']) && (float) $ans['ai_score'] >= (float) $ans['max_score'] * 0.6;
  ?>
    <div class="answer-card <?= $correct ? 'answer-card--correct' : 'answer-card--wrong' ?>">
      <div class="answer-header">
        <span class="question-num">Q<?= $i + 1 ?></span>
        <span class="answer-verdict">
          <?= $correct ? '✓ Correto' : '✗ Incorreto / Parcial' ?>
        </span>
        <span class="answer-score">
          <?= $ans['ai_score'] !== null
            ? number_format((float) $ans['ai_score'], 1) . ' / ' . number_format((float) $ans['max_score'], 1)
            : '—' ?> pts
        </span>
      </div>

      <p class="question-text"><strong>Questão:</strong> <?= nl2br(\Core\View::e($ans['question_text'])) ?></p>

      <div class="answer-block">
        <strong><?= \Core\View::e($resultAnswerLabel) ?></strong>
        <p><?= nl2br(\Core\View::e($ans['student_answer'])) ?></p>
      </div>

      <?php if ($ans['ai_feedback']): ?>
        <div class="feedback-block <?= $correct ? 'feedback-block--ok' : 'feedback-block--err' ?>">
          <strong>Feedback da IA:</strong>
          <p><?= nl2br(\Core\View::e($ans['ai_feedback'])) ?></p>
        </div>
      <?php endif; ?>

      <?php
        // Motivos de desconto: para o Aluno, só quando não tirou a nota máxima, com dica de estudo.
        $deductionReasons = \App\Models\DeductionReason::fromJson($ans['deduction_reasons_json'] ?? null);
        $lostPoints = isset($ans['ai_score']) && (float) $ans['ai_score'] < (float) $ans['max_score'];
      ?>
      <?php if ($deductionReasons !== [] && ($internalReview || $lostPoints)): ?>
        <div class="feedback-block feedback-block--err">
          <strong><?= $internalReview ? 'Motivos de desconto:' : 'O que faltou para a nota máxima:' ?></strong>
          <?php if ($internalReview): ?>
            <p>
              <?php foreach ($deductionReasons as $reason): ?>
                <span class="badge badge--warning"><?= \Core\View::e(\App\Models\DeductionReason::label($reason)) ?></span>
              <?php endforeach; ?>
            </p>
          <?php else: ?>
            <ul class="deduction-list">
              <?php foreach ($deductionReasons as $reason): ?>
                <li>
                  <strong><?= \Core\View::e(\App\Models\DeductionReason::label($reason)) ?></strong>
                  <span><?= \Core\View::e(\App\Models\DeductionReason::tip($reason)) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php
        // Revisão de nota: histórico, pedido do Aluno e ações do Docente.
        $answerId = (int) ($ans['id'] ?? 0);
        $answerReviews = $answerId > 0 ? ($scoreReviews[$answerId] ?? []) : [];
        $openRequest = null;
        $studentAlreadyRequested = false;
        foreach ($answerReviews as $review) {
          if ($review['status'] === 'requested') {
            $openRequest = $review;
          }
          if (!empty($review['requested_by'])) {
            $studentAlreadyRequested = true;
          }
        }
      ?>
      <?php foreach ($answerReviews as $review): ?>
        <div class="feedback-block">
          <?php if (!empty($review['request_message'])): ?>
            <p><strong><?= $internalReview ? 'Pedido de revisão do aluno:' : 'Seu pedido de revisão:' ?></strong> <?= nl2br(\Core\View::e($review['request_message'])) ?></p>
          <?php endif; ?>
          <?php if ($review['status'] === 'requested'): ?>
            <p><span class="badge badge--warning">Revisão pedida</span> Aguardando a resposta do Docente.</p>
          <?php elseif ($review['status'] === 'adjusted'): ?>
            <p><span class="badge badge--info">Nota revisada pelo Docente</span>
              <?= number_format((float) $review['previous_score'], 1) ?> → <?= number_format((float) $review['new_score'], 1) ?> pts</p>
            <p><?= nl2br(\Core\View::e((string) $review['justification'])) ?></p>
          <?php else: ?>
            <p><span class="badge badge--neutral">Nota mantida pelo Docente</span></p>
            <p><?= nl2br(\Core\View::e((string) $review['justification'])) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!$internalReview && $answerId > 0 && $lostPoints && !$studentAlreadyRequested): ?>
        <div class="feedback-block">
        <details class="hint-details">
          <summary>Pedir revisão da nota</summary>
          <form method="POST" action="<?= \Core\app_url('/student/answers/' . $answerId . '/review-request') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <div class="form-group">
              <label class="form-label" for="review-message-<?= $answerId ?>">Por que a nota deve ser revista? <span class="hint">(um pedido por questão)</span></label>
              <textarea id="review-message-<?= $answerId ?>" name="message" class="form-input form-textarea" rows="3" maxlength="1000" required></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn--ghost btn--sm">Enviar pedido ao Docente</button>
            </div>
          </form>
        </details>
        </div>
      <?php endif; ?>

      <?php if ($canReviewScores && $answerId > 0 && $ans['ai_score'] !== null): ?>
        <div class="feedback-block">
        <details class="hint-details" <?= $openRequest ? 'open' : '' ?>>
          <summary><?= $openRequest ? 'Responder ao pedido de revisão' : 'Revisar nota' ?></summary>
          <form method="POST" action="<?= \Core\app_url('/teacher/answers/' . $answerId . '/review') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="review-score-<?= $answerId ?>">Nova pontuação (0 a <?= number_format((float) $ans['max_score'], 1) ?>)</label>
                <input id="review-score-<?= $answerId ?>" name="score" class="form-input" type="number" min="0" max="<?= (float) $ans['max_score'] ?>" step="0.1"
                  value="<?= \Core\View::e((string) $ans['ai_score']) ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="review-justification-<?= $answerId ?>">Justificativa <span class="hint">(mostrada ao aluno)</span></label>
              <textarea id="review-justification-<?= $answerId ?>" name="justification" class="form-input form-textarea" rows="2" maxlength="1000" required></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn--primary btn--sm">Salvar nova nota</button>
            </div>
          </form>
          <?php if ($openRequest): ?>
            <form method="POST" action="<?= \Core\app_url('/teacher/score-reviews/' . (int) $openRequest['id'] . '/keep') ?>" class="form">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <input type="hidden" name="return_to" value="/teacher/attempts/<?= (int) ($attempt['id'] ?? 0) ?>/result">
              <div class="form-group">
                <label class="form-label" for="keep-justification-<?= $answerId ?>">Ou mantenha a nota, explicando ao aluno</label>
                <textarea id="keep-justification-<?= $answerId ?>" name="justification" class="form-input form-textarea" rows="2" maxlength="1000" required></textarea>
              </div>
              <div class="form-actions">
                <button type="submit" class="btn btn--ghost btn--sm">Manter nota</button>
              </div>
            </form>
          <?php endif; ?>
        </details>
        </div>
      <?php endif; ?>

      <?php if ($showReferenceAnswer && $ans['expected_answer_hint']): ?>
        <div class="feedback-block feedback-block--ok">
          <strong>Resposta esperada:</strong>
          <p><?= nl2br(\Core\View::e($ans['expected_answer_hint'])) ?></p>
        </div>
        <?php \Core\View::partial('partials/python_solution', ['code' => $ans['python_solution'] ?? null]); ?>
      <?php elseif (!$showReferenceAnswer && $ans['expected_answer_hint']): ?>
        <details class="hint-details">
          <summary>Resposta esperada indisponível por enquanto</summary>
          <p class="hint-text">Ela será exibida quando o exercício fechar ou quando você atingir o limite de tentativas.</p>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($bestScore !== null): ?>
  <p class="text-center text-muted">Sua melhor nota neste exercício: <strong><?= number_format((float) $bestScore, 1) ?> pts</strong></p>
<?php endif; ?>
