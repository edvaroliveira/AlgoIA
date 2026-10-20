<?php
use App\Models\TurmaRanking;

$pageTitle = 'Ranking';

$boards = $boards ?? [];
$studentId = (int) ($studentId ?? 0);
$display = static function (array $row) use ($studentId): string {
  if ($row['student_id'] === $studentId) {
    return \Core\View::e($row['name']) . ' (você)';
  }
  return $row['anonymous'] ? 'Aluno anônimo' : \Core\View::e($row['name']);
};
$pts = static fn(float $p): string => number_format($p, 1, ',', '') . ' pts';
?>

<div class="page-header">
  <div>
    <h1>Ranking</h1>
    <p class="subtitle">Pontos da Prática: em cada Questão do banco conta a sua melhor nota no período (0 a 10). Para aparecer como anônimo, ajuste em Minha conta.</p>
  </div>
</div>

<?php if ($boards === []): ?>
  <p class="empty-state">Você ainda não participa de nenhuma turma.</p>
<?php endif; ?>

<?php foreach ($boards as $i => $board):
  $mode = (string) ($board['turma']['ranking_mode'] ?? TurmaRanking::MODE_OFF);
?>
  <div class="<?= $i > 0 ? 'section' : '' ?>">
    <section class="surface-block">
      <div class="surface-block__body">
        <p><strong><?= \Core\View::e($board['turma']['name']) ?></strong>
          <?php if ($mode !== TurmaRanking::MODE_OFF): ?> · <?= TurmaRanking::modeLabel($mode) ?><?php endif; ?></p>

        <?php if ($mode === TurmaRanking::MODE_OFF): ?>
          <p class="empty-state">O docente não ativou o ranking nesta turma.</p>
        <?php else: ?>
          <?php if ($board['top'] === []): ?>
            <p>Ninguém pontuou neste período ainda. Pratique para sair na frente!</p>
          <?php else: ?>
            <ol class="ranking-list">
              <?php foreach ($board['top'] as $row): ?>
                <li class="ranking-row<?= $row['student_id'] === $studentId ? ' ranking-row--me' : '' ?>">
                  <span class="ranking-row__pos"><?= $row['position'] ?>º</span>
                  <span class="ranking-row__name"><?= $display($row) ?></span>
                  <span class="ranking-row__pts"><?= $pts($row['points']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>

          <p class="ranking-mine">
            <?php if ($board['mine'] !== null): ?>
              Você está em <?= $board['mine']['position'] ?>º de <?= $board['total'] ?>, com <?= $pts($board['mine']['points']) ?>.
            <?php else: ?>
              Você ainda não pontuou neste período.
            <?php endif; ?>
          </p>

          <?php if ($board['champion'] !== null && $mode !== TurmaRanking::MODE_ALL): ?>
            <p><small><?= $mode === TurmaRanking::MODE_WEEKLY ? 'Campeão da semana passada' : 'Campeão do mês passado' ?>: <?= $display($board['champion']) ?></small></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
  </div>
<?php endforeach; ?>
