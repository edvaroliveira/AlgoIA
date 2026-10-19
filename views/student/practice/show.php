<?php
global $session;
$history = $history ?? [];
$draft = (string) ($draft ?? '');
$theme = (string) ($theme ?? '');
$usedToday = (int) ($usedToday ?? 0);
$limit = (int) ($limit ?? 0);
$fmt = static fn($n): string => number_format((float) $n, 1, ',', '');
?>

<div class="page-header">
  <div>
    <h1>Prática</h1>
    <p class="subtitle"><?= \Core\View::e($question['list_title']) ?><?= !empty($question['themes']) ? ' · ' . \Core\View::e($question['themes']) : '' ?></p>
  </div>
  <span class="badge <?= $usedToday >= $limit ? 'badge--error' : 'badge--info' ?>"><?= $usedToday ?> de <?= $limit ?> correções hoje</span>
</div>

<section class="surface-block">
  <div class="surface-block__body">
    <p><strong>Questão</strong> (vale até <?= $fmt($question['max_score']) ?>, só para treino)</p>
    <p><?= nl2br(\Core\View::e($question['text'])) ?></p>

    <form method="POST" action="<?= \Core\app_url('/student/pratica/questao/' . (int) $question['id']) ?>">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
      <input type="hidden" name="tema" value="<?= \Core\View::e($theme) ?>">
      <div class="form-group">
        <label for="practice-answer">Sua resposta</label>
        <textarea id="practice-answer" name="answer" rows="8" maxlength="10000" required><?= \Core\View::e($draft) ?></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn--primary">Enviar para correção</button>
        <?php if ($theme !== ''): ?>
          <a class="btn btn--ghost" href="<?= \Core\app_url('/student/pratica/sortear?tema=' . rawurlencode($theme)) ?>">Outra Questão</a>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= \Core\app_url('/student/pratica') ?>">Voltar aos Temas</a>
      </div>
    </form>
  </div>
</section>

<?php if ($history !== []): ?>
  <div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Suas correções</strong></p>
      <?php foreach ($history as $item):
        $reasons = \App\Models\DeductionReason::fromJson($item['deduction_reasons_json'] ?? null);
        $lost = (float) $item['score'] < (float) $item['max_score'];
      ?>
        <article class="notification-item">
          <strong>Pontuação: <?= $fmt($item['score']) ?> de <?= $fmt($item['max_score']) ?></strong>
          <small><?= date('d/m/Y H:i', strtotime((string) $item['created_at'])) ?></small>
          <div class="feedback-block">
            <strong>Feedback:</strong> <?= \Core\View::e($item['feedback']) ?>
          </div>
          <?php if ($reasons !== [] && $lost): ?>
            <div class="feedback-block feedback-block--err">
              <strong>O que faltou para a nota máxima:</strong>
              <ul class="deduction-list">
                <?php foreach ($reasons as $reason): ?>
                  <li>
                    <strong><?= \Core\View::e(\App\Models\DeductionReason::label($reason)) ?></strong>
                    <span><?= \Core\View::e(\App\Models\DeductionReason::tip($reason)) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <details>
            <summary>Ver sua resposta</summary>
            <p><?= nl2br(\Core\View::e($item['student_answer'])) ?></p>
          </details>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  </div>
<?php endif; ?>
