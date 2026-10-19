<?php
$themes = $themes ?? [];
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
    <p class="subtitle">Seu desempenho por Tema, somando a Prática e as Tentativas com Questões do banco de exercícios.</p>
  </div>
</div>

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
          <?php foreach ($themes as $theme):
            [$label, $badge] = $levels[$theme['level']];
          ?>
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
