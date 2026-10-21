<?php
/**
 * Similaridade de uma Questão com o Banco de exercícios (só para o Admin).
 * Espera $row = ['pct', 'blocked', 'matches'] (BankSimilarityService::report).
 */
$row = $row ?? ['pct' => 0.0, 'blocked' => false, 'matches' => []];
$fmt = static fn(float $pct): string => rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',');
?>
<?php if ($row['matches'] !== []): ?>
  <div class="similarity">
    <?php if ($row['blocked']): ?>
      <span class="badge badge--error"><?= $fmt($row['pct']) ?>% — não entra</span>
    <?php else: ?>
      <span class="badge badge--warning"><?= $fmt($row['pct']) ?>% semelhante</span>
    <?php endif; ?>
    <ul class="similarity__matches">
      <?php foreach ($row['matches'] as $match): ?>
        <li>
          <strong><?= $fmt((float) $match['pct']) ?>%</strong> com
          “<?= \Core\View::e(mb_strimwidth((string) $match['text'], 0, 140, '…')) ?>”
          <small>(<?= \Core\View::e((string) $match['title']) ?> · <?= $match['status'] === 'active' ? 'No banco' : 'Sugestão' ?>)</small>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
