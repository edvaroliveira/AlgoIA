<?php
$themes = $themes ?? [];
$usedToday = (int) ($usedToday ?? 0);
$limit = (int) ($limit ?? 0);
?>

<div class="page-header">
  <div>
    <h1>Prática</h1>
    <p class="subtitle">Questões do banco para treinar, sem valer nota. A correção sai na hora.</p>
  </div>
  <span class="badge <?= $usedToday >= $limit ? 'badge--error' : 'badge--info' ?>"><?= $usedToday ?> de <?= $limit ?> correções hoje</span>
</div>

<?php if ($themes === []): ?>
  <p class="empty-state">O banco de exercícios ainda não tem Questões para praticar.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <?php foreach ($themes as $theme): ?>
        <article class="agenda-item">
          <div class="agenda-item__main">
            <strong><?= \Core\View::e($theme['name']) ?></strong>
            <small><?= (int) $theme['practiced_count'] ?> de <?= (int) $theme['question_count'] ?> Questões praticadas</small>
          </div>
          <a class="btn btn--primary btn--sm" href="<?= \Core\app_url('/student/pratica/sortear?tema=' . rawurlencode((string) $theme['name'])) ?>">Praticar</a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
