<?php
$posts = $posts ?? [];
?>

<div class="page-header">
  <div>
    <h1>Mural</h1>
    <p class="subtitle">Recados dos docentes das suas turmas. Os fixados aparecem primeiro.</p>
  </div>
</div>

<?php if ($posts === []): ?>
  <p class="empty-state">Nenhum recado nas suas turmas por enquanto.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <?php foreach ($posts as $post): ?>
        <?php \Core\View::partial('partials/mural_post', ['post' => $post, 'showTurma' => true]); ?>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
