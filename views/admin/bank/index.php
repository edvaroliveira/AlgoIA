<?php
$status     = $status ?? 'suggested';
$tabs       = $tabs ?? [];
$counts     = $counts ?? [];
$items      = $items ?? [];
$pagination = $pagination ?? [];
$pageTitle  = 'Banco de exercícios';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Banco de exercícios</h1>
    <p class="subtitle">Curadoria do acervo institucional: aprove Sugestões dos Docentes com os Temas certos, recuse com motivo e arquive o que não deve mais ser reaproveitado.</p>
  </div>
</div>

<div class="td-actions" role="tablist" aria-label="Situação no banco">
  <?php foreach ($tabs as $tabStatus => $label): ?>
    <a href="<?= \Core\app_url('/admin/bank?status=' . $tabStatus) ?>"
      class="btn <?= $status === $tabStatus ? 'btn--primary' : 'btn--ghost' ?>"
      role="tab" aria-selected="<?= $status === $tabStatus ? 'true' : 'false' ?>">
      <?= \Core\View::e($label) ?> (<?= (int) ($counts[$tabStatus] ?? 0) ?>)
    </a>
  <?php endforeach; ?>
</div>

<?php if ($items === []): ?>
  <p class="empty-state section">Nenhum item em "<?= \Core\View::e($tabs[$status] ?? $status) ?>".</p>
<?php else: ?>
  <?php foreach ($items as $item): ?>
    <section class="surface-block section">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title"><?= \Core\View::e($item['title']) ?></h2>
          <p class="surface-copy">
            Origem: <?= \Core\View::e($item['teacher_name'] ?? 'Docente removido') ?>
            · <?= (int) $item['question_count'] ?> questão(ões)
            · <?= $status === 'suggested' ? 'sugerido' : 'revisado' ?> em
            <?= date('d/m/Y H:i', strtotime((string) ($status === 'suggested' ? $item['created_at'] : ($item['reviewed_at'] ?? $item['created_at'])))) ?>
          </p>
          <?php if (!empty($item['themes'])): ?>
            <div class="badge-strip">
              <?php foreach ($item['themes'] as $theme): ?>
                <span class="badge badge--info"><?= \Core\View::e($theme) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if (!empty($item['source_exercise_id'])): ?>
          <a href="<?= \Core\app_url('/admin/exercises/' . (int) $item['source_exercise_id']) ?>" class="btn btn--sm btn--ghost">Exercício de origem</a>
        <?php endif; ?>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <details class="hint-details">
          <summary>Ver questões</summary>
          <?php foreach ($item['questions'] as $i => $q): ?>
            <div class="question-card">
              <div class="question-header">
                <span class="question-num">Q<?= $i + 1 ?></span>
                <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
              </div>
              <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
              <p class="hint-text"><strong>Resposta de referência:</strong> <?= nl2br(\Core\View::e($q['expected_answer_hint'])) ?></p>
            </div>
          <?php endforeach; ?>
        </details>

        <?php if ($status === 'suggested'): ?>
          <form method="POST" action="<?= \Core\app_url('/admin/bank/' . (int) $item['id'] . '/approve') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <div class="form-group">
              <label class="form-label" for="approve-themes-<?= (int) $item['id'] ?>">Temas <span class="hint">(separados por vírgula)</span></label>
              <input id="approve-themes-<?= (int) $item['id'] ?>" name="themes" class="form-input" type="text" maxlength="500" placeholder="Ex.: Recursão, Vetores">
            </div>
            <div class="td-actions">
              <button type="submit" class="btn btn--primary">Aprovar e incluir no banco</button>
            </div>
          </form>
          <form method="POST" action="<?= \Core\app_url('/admin/bank/' . (int) $item['id'] . '/reject') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <div class="form-group">
              <label class="form-label" for="reject-note-<?= (int) $item['id'] ?>">Motivo da recusa <span class="hint">(mostrado ao Docente)</span></label>
              <textarea id="reject-note-<?= (int) $item['id'] ?>" name="review_note" class="form-input form-textarea" rows="2" maxlength="1000" required></textarea>
            </div>
            <div class="td-actions">
              <button type="submit" class="btn btn--danger">Recusar</button>
            </div>
          </form>
        <?php elseif ($status === 'active'): ?>
          <form method="POST" action="<?= \Core\app_url('/admin/bank/' . (int) $item['id'] . '/themes') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <div class="form-group">
              <label class="form-label" for="themes-<?= (int) $item['id'] ?>">Temas</label>
              <input id="themes-<?= (int) $item['id'] ?>" name="themes" class="form-input" type="text" maxlength="500"
                value="<?= \Core\View::e(implode(', ', $item['themes'])) ?>">
            </div>
            <div class="td-actions">
              <button type="submit" class="btn btn--ghost">Salvar temas</button>
            </div>
          </form>
          <form method="POST" action="<?= \Core\app_url('/admin/bank/' . (int) $item['id'] . '/archive') ?>"
            data-confirm="Arquivar este item? Ele deixa de aparecer para os Docentes.">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <button type="submit" class="btn btn--danger btn--sm">Arquivar</button>
          </form>
        <?php elseif ($status === 'rejected' && !empty($item['review_note'])): ?>
          <div class="content-note"><strong>Motivo da recusa</strong><p><?= nl2br(\Core\View::e($item['review_note'])) ?></p></div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
<?php endif; ?>
