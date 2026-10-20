<?php
$pageTitle = 'Progresso';
$themes = $themes ?? [];
$evolution = $evolution ?? [];
$achievements = $achievements ?? [];
$levels = [
  \App\Models\ThemeProgress::LEVEL_STRONG   => ['Ponto forte', 'badge--success'],
  \App\Models\ThemeProgress::LEVEL_MIDDLE   => ['Em evolução', 'badge--info'],
  \App\Models\ThemeProgress::LEVEL_WEAK     => ['A melhorar', 'badge--error'],
  \App\Models\ThemeProgress::LEVEL_FEW_DATA => ['Poucos dados', 'badge--neutral'],
];
$strong = array_column(array_filter($themes, static fn(array $t): bool => $t['level'] === \App\Models\ThemeProgress::LEVEL_STRONG), 'name');
$weak = array_column(array_filter($themes, static fn(array $t): bool => $t['level'] === \App\Models\ThemeProgress::LEVEL_WEAK), 'name');
?>

<div class="page-header">
  <div>
    <h1>Progresso</h1>
    <p class="subtitle">Seu desempenho por Tema (Prática e Tentativas com Questões do banco) e a evolução nos exercícios que você refez.</p>
  </div>
</div>

<?php if ($achievements !== []):
  $earnedCount = count(array_filter($achievements, static fn(array $a): bool => $a['earned_at'] !== null));
  // Obtidas primeiro, mantendo a ordem do catálogo dentro de cada grupo.
  $achievements = array_merge(
    array_values(array_filter($achievements, static fn(array $a): bool => $a['earned_at'] !== null)),
    array_values(array_filter($achievements, static fn(array $a): bool => $a['earned_at'] === null))
  );
?>
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Conquistas</strong> — <?= $earnedCount ?> de <?= count($achievements) ?>. Só você vê.</p>
      <ul class="conquista-grid">
        <?php foreach ($achievements as $achievement): ?>
          <li class="conquista <?= $achievement['earned_at'] !== null ? 'conquista--earned' : 'conquista--locked' ?>">
            <strong><?= \Core\View::e($achievement['label']) ?></strong>
            <small>
              <?= $achievement['earned_at'] !== null
                ? 'Obtida em ' . date('d/m/Y', strtotime((string) $achievement['earned_at']))
                : \Core\View::e($achievement['hint']) ?>
            </small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <div class="section"></div>
<?php endif; ?>

<?php if ($themes === []): ?>
  <p class="empty-state">Ainda não há Temas no banco de exercícios.</p>
<?php else: ?>
  <?php if ($strong !== [] || $weak !== []): ?>
    <section class="surface-block">
      <div class="surface-block__body">
        <?php if ($strong !== []): ?><p><strong>Pontos fortes:</strong> <?= \Core\View::e(implode(', ', $strong)) ?></p><?php endif; ?>
        <?php if ($weak !== []): ?><p><strong>A melhorar:</strong> <?= \Core\View::e(implode(', ', $weak)) ?></p><?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Tema</th>
            <th>Média</th>
            <th>Situação</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $lastCategory = null; foreach ($themes as $theme):
            [$label, $badge] = $levels[$theme['level']];
          ?>
            <?php if ($theme['category'] !== $lastCategory): $lastCategory = $theme['category']; ?>
              <tr class="table-group"><th colspan="4"><?= \Core\View::e($lastCategory) ?></th></tr>
            <?php endif; ?>
            <tr>
              <td>
                <?= \Core\View::e($theme['name']) ?><br>
                <small><?= $theme['answered'] ?> <?= $theme['answered'] === 1 ? 'resposta corrigida' : 'respostas corrigidas' ?></small>
              </td>
              <td><?= $theme['average'] !== null ? (int) round($theme['average'] * 100) . '%' : '—' ?></td>
              <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
              <td class="td-actions">
                <?php if ($theme['practicable']): ?>
                  <a class="btn btn--sm btn--ghost" href="<?= \Core\app_url('/student/pratica/sortear?tema=' . rawurlencode($theme['name'])) ?>">Praticar</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  </div>
<?php endif; ?>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Evolução nas Tentativas</strong> — primeira e última nota em cada exercício que você refez.</p>
      <?php if ($evolution === []): ?>
        <p class="empty-state">Quando você refizer um exercício, a comparação entre a primeira e a última nota aparece aqui.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Exercício</th>
              <th>Primeira</th>
              <th>Última</th>
              <th>Diferença</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($evolution as $item):
              [$trendLabel, $trendBadge] = \App\Models\AttemptEvolution::trendBadge($item['trend']);
            ?>
              <tr>
                <td>
                  <?= \Core\View::e($item['title']) ?><br>
                  <small><?= $item['turma_name'] !== null ? \Core\View::e($item['turma_name']) . ' · ' : '' ?><?= $item['attempts'] ?> Tentativas</small>
                </td>
                <td><?= number_format($item['first'], 1, ',', '') ?> de <?= number_format($item['max_total'], 1, ',', '') ?></td>
                <td><?= number_format($item['last'], 1, ',', '') ?></td>
                <td>
                  <?= \App\Models\AttemptEvolution::deltaText($item['delta']) ?>
                  <br><span class="badge <?= $trendBadge ?>"><?= $trendLabel ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>
