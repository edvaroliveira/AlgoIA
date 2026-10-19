<?php
use App\Models\DeductionReason;
use App\Models\TurmaPerformance;

$exercises = $grid['exercises'] ?? [];
$students = $grid['students'] ?? [];
$turmaAverage = $grid['turmaAverage'] ?? null;
$selected = $selected ?? null;
$byQuestion = $byQuestion ?? [];
$turmaId = (int) ($turma['id'] ?? 0);
$fmt = static fn($n): string => number_format((float) $n, 1, ',', '');
$cellBadge = [
  TurmaPerformance::CELL_PENDING     => 'badge--warning',
  TurmaPerformance::CELL_IN_PROGRESS => 'badge--neutral',
];
?>

<div class="page-header">
  <div>
    <h1>Desempenho — <?= \Core\View::e($turma['name'] ?? 'Turma') ?></h1>
    <p class="subtitle">Melhor nota de cada Aluno por Exercício publicado nesta Turma.<?= $turmaAverage !== null ? ' Média da Turma: ' . TurmaPerformance::percent($turmaAverage) . '.' : '' ?></p>
  </div>
  <div class="form-actions">
    <a href="<?= \Core\app_url('/teacher/turmas/' . $turmaId . '/desempenho/csv') ?>" class="btn btn--primary">Exportar CSV</a>
    <a href="<?= \Core\app_url('/teacher/turmas/' . $turmaId) ?>" class="btn btn--ghost">← Turma</a>
  </div>
</div>

<?php if ($students === [] || $exercises === []): ?>
  <p class="empty-state">
    <?= $students === [] ? 'Nenhum Aluno ativo nesta Turma.' : 'Nenhum Exercício publicado nesta Turma.' ?>
  </p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Aluno</th>
            <?php foreach ($exercises as $exercise): ?>
              <th>
                <a href="<?= \Core\app_url('/teacher/turmas/' . $turmaId . '/desempenho?exercicio=' . (int) $exercise['id']) ?>"><?= \Core\View::e($exercise['title']) ?></a>
                <br><small>vale <?= $fmt($exercise['max_total']) ?></small>
              </th>
            <?php endforeach; ?>
            <th>Média</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $student): ?>
            <tr>
              <td>
                <?= \Core\View::e($student['name']) ?><br>
                <small><?= \Core\View::e($student['email']) ?></small>
              </td>
              <?php foreach ($exercises as $exercise):
                $cell = $student['cells'][(int) $exercise['id']];
                $text = TurmaPerformance::cellText($cell);
              ?>
                <td>
                  <?php if (isset($cellBadge[$cell[0]])): ?>
                    <span class="badge <?= $cellBadge[$cell[0]] ?>"><?= $text ?></span>
                  <?php else: ?>
                    <?= $text !== '' ? $text : '—' ?>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <?= $student['average'] !== null ? TurmaPerformance::percent($student['average']) : '—' ?>
                <?php if ($student['neverTried']): ?><br><span class="badge badge--error">Nunca tentou</span><?php endif; ?>
                <?php if ($student['belowAverage']): ?><br><span class="badge badge--warning">Abaixo da média</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="section">
    <section class="surface-block">
      <div class="surface-block__body">
        <?php if ($selected === null): ?>
          <p>Clique no título de um Exercício para ver a média de cada Questão e os Motivos de desconto mais comuns.</p>
        <?php else: ?>
          <p><strong>Por Questão — <?= \Core\View::e($selected['title']) ?></strong> (melhor Tentativa de cada Aluno)</p>
          <table class="table">
            <thead>
              <tr>
                <th>Questão</th>
                <th>Média</th>
                <th>Motivos de desconto mais comuns</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($byQuestion as $i => $question): ?>
                <tr>
                  <td>
                    <strong><?= $i + 1 ?>.</strong> <?= \Core\View::e(mb_strimwidth($question['text'], 0, 140, '…')) ?><br>
                    <small><?= $question['answered'] ?> resposta(s) corrigida(s), vale <?= $fmt($question['max_score']) ?></small>
                  </td>
                  <td><?= $question['average'] !== null ? TurmaPerformance::percent($question['average']) : '—' ?></td>
                  <td>
                    <?php if ($question['reasons'] === []): ?>
                      —
                    <?php else: ?>
                      <?php foreach ($question['reasons'] as $code => $count): ?>
                        <span class="badge badge--warning"><?= \Core\View::e(DeductionReason::label($code)) ?> (<?= (int) $count ?>)</span>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </div>
<?php endif; ?>
