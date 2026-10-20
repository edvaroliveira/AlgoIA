<?php
$pageTitle = 'Agenda';
$items = $items ?? [];
$now = time();
?>

<div class="page-header">
  <div>
    <h1>Agenda</h1>
    <p class="subtitle">Exercícios abertos e futuros de todas as suas turmas, do prazo mais próximo para o mais distante.</p>
  </div>
</div>

<?php if ($items === []): ?>
  <p class="empty-state">Nenhum exercício aberto ou agendado nas suas turmas.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <?php foreach ($items as $item):
        $opens = strtotime((string) $item['opens_at']);
        $closes = strtotime((string) $item['closes_at']);
        $maxAttempts = (int) $item['max_attempts'];
        $used = (int) $item['used_attempts'];
        $isFuture = $opens > $now;
        $dueSoon = !$isFuture && $closes - $now <= 86400;
      ?>
        <article class="agenda-item">
          <div class="agenda-item__main">
            <strong>
              <?php if ($isFuture): ?>
                <?= \Core\View::e($item['title']) ?>
              <?php else: ?>
                <a href="<?= \Core\app_url('/student/exercises/' . (int) $item['exercise_id']) ?>"><?= \Core\View::e($item['title']) ?></a>
              <?php endif; ?>
            </strong>
            <small><?= \Core\View::e($item['turma_name']) ?></small>
            <span>
              <?= $isFuture ? 'Abre em ' . date('d/m/Y H:i', $opens) : 'Encerra em ' . date('d/m/Y H:i', $closes) ?>
            </span>
          </div>
          <div class="agenda-item__status">
            <?php if ($isFuture): ?>
              <span class="badge badge--neutral">Agendado</span>
            <?php else: ?>
              <?php if ($dueSoon): ?><span class="badge badge--error">Vence em menos de 24 h</span><?php endif; ?>
              <?php if ((int) $item['in_progress'] > 0): ?>
                <span class="badge badge--warning">Tentativa em andamento</span>
              <?php elseif ($used === 0): ?>
                <span class="badge badge--warning">Ainda não tentado</span>
              <?php endif; ?>
              <?php if ($used > 0): ?>
                <span class="badge badge--info"><?= $maxAttempts === 0 ? $used . ' tentativa(s) usada(s)' : $used . ' de ' . $maxAttempts . ' tentativas usadas' ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
