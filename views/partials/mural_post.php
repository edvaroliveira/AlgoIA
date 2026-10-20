<?php
/** Um recado do Mural da Turma. Espera $post e, opcionalmente, $showTurma. */
$showTurma = $showTurma ?? false;
?>
<article class="mural-post<?= (int) $post['pinned'] === 1 ? ' mural-post--pinned' : '' ?>">
  <header class="mural-post__header">
    <strong><?= \Core\View::e($post['title']) ?></strong>
    <?php if ((int) $post['pinned'] === 1): ?><span class="badge badge--info">Fixado</span><?php endif; ?>
  </header>
  <p class="mural-post__body"><?= nl2br(\Core\View::e($post['body'])) ?></p>
  <small>
    <?= $showTurma ? \Core\View::e($post['turma_name']) . ' · ' : '' ?><?= date('d/m/Y H:i', strtotime((string) $post['created_at'])) ?>
  </small>
</article>
