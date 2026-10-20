<?php
$pageTitle = 'Avisos';
$items = $items ?? [];
?>

<div class="page-header">
  <div>
    <h1>Avisos</h1>
    <p class="subtitle">Publicações novas, prazos encerrando, correções concluídas e respostas às suas revisões.</p>
  </div>
</div>

<?php if ($items === []): ?>
  <p class="empty-state">Nenhum aviso por enquanto.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <?php foreach ($items as $item): ?>
        <article class="notification-item <?= $item['read_at'] === null ? 'notification-item--unread' : '' ?>">
          <strong>
            <?php if (!empty($item['link'])): ?>
              <a href="<?= \Core\app_url((string) $item['link']) ?>"><?= \Core\View::e($item['title']) ?></a>
            <?php else: ?>
              <?= \Core\View::e($item['title']) ?>
            <?php endif; ?>
            <?php if ($item['read_at'] === null): ?><span class="badge badge--info">Novo</span><?php endif; ?>
          </strong>
          <?php if (!empty($item['body'])): ?><span><?= \Core\View::e($item['body']) ?></span><?php endif; ?>
          <small><?= date('d/m/Y H:i', strtotime((string) $item['created_at'])) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
