<?php
$attempt = $attempt ?? [];
$bestScore = $bestScore ?? null;
$maxScore = $maxScore ?? 0;
$answers = $answers ?? [];
$showReferenceAnswer = $showReferenceAnswer ?? false;
$showDeductionReasons = $showDeductionReasons ?? false;
$resultBackUrl = $resultBackUrl ?? '/student/exercises/' . ($attempt['exercise_id'] ?? 0);
$resultAnswerLabel = $resultAnswerLabel ?? 'Sua resposta:';
$deductionLabels = [
  'logic_error' => 'Erro de lógica',
  'missing_concept' => 'Conceito ausente',
  'contradiction' => 'Contradição',
  'inefficiency' => 'Ineficiência',
  'approach_difference' => 'Diferença de abordagem',
  'incomplete_explanation' => 'Explicação incompleta',
];
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
  <div class="result-score">
    <?= number_format((float) $attempt['total_score'], 1) ?>
    <span class="result-max">/ <?= number_format((float) $maxScore, 1) ?> pts</span>
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
        $deductionReasons = [];
        if ($showDeductionReasons && !empty($ans['deduction_reasons_json'])) {
          $decodedReasons = json_decode((string) $ans['deduction_reasons_json'], true);
          $deductionReasons = is_array($decodedReasons) ? array_values(array_filter($decodedReasons, 'is_string')) : [];
        }
      ?>
      <?php if ($showDeductionReasons && !empty($deductionReasons)): ?>
        <div class="feedback-block feedback-block--err">
          <strong>Motivos de desconto:</strong>
          <p>
            <?php foreach ($deductionReasons as $reason): ?>
              <span class="badge badge--warning"><?= \Core\View::e($deductionLabels[$reason] ?? $reason) ?></span>
            <?php endforeach; ?>
          </p>
        </div>
      <?php endif; ?>

      <?php if ($showReferenceAnswer && $ans['expected_answer_hint']): ?>
        <div class="feedback-block feedback-block--ok">
          <strong>Resposta esperada:</strong>
          <p><?= nl2br(\Core\View::e($ans['expected_answer_hint'])) ?></p>
        </div>
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
