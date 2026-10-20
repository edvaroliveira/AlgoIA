<?php
$tab        = $tab ?? 'bank';
$search     = $search ?? '';
$theme      = $theme ?? '';
$category   = (int) ($category ?? 0);
$themes     = $themes ?? [];
$items      = $items ?? [];
$target     = $target ?? null;
$pagination = $pagination ?? [];
$pageTitle  = 'Banco de exercícios';
global $session;

$targetId = $target ? (int) $target['id'] : 0;
$bankUrl = static function (array $params) use ($targetId): string {
  $query = array_filter($params + ['exercise' => $targetId ?: ''], static fn($v): bool => $v !== '' && $v !== null);
  return \Core\app_url('/teacher/bank' . ($query ? '?' . http_build_query($query) : ''));
};
$source = $tab === 'own' ? 'own' : 'bank';
?>

<div class="page-header">
  <div>
    <h1>Banco de exercícios</h1>
    <?php if ($target): ?>
      <p class="subtitle">Escolha questões para acrescentar ao Rascunho <strong><?= \Core\View::e($target['title']) ?></strong>. Elas entram depois das questões que ele já tem.</p>
    <?php else: ?>
      <p class="subtitle">Escolha um exercício, marque as questões que quer aproveitar e crie um Rascunho seu. Depois você pode excluir questões e acrescentar novas.</p>
    <?php endif; ?>
  </div>
  <?php if ($target): ?>
    <a href="<?= \Core\app_url('/teacher/exercises/' . $targetId . '/questions/create') ?>" class="btn btn--ghost">← Voltar ao Rascunho</a>
  <?php else: ?>
    <a href="<?= \Core\app_url('/teacher/exercises/create') ?>" class="btn btn--ghost">← Voltar</a>
  <?php endif; ?>
</div>

<div class="td-actions" role="tablist" aria-label="Origem das questões">
  <a href="<?= $bankUrl(['tab' => 'bank']) ?>" class="btn <?= $tab === 'bank' ? 'btn--primary' : 'btn--ghost' ?>" role="tab" aria-selected="<?= $tab === 'bank' ? 'true' : 'false' ?>">Banco institucional</a>
  <a href="<?= $bankUrl(['tab' => 'own']) ?>" class="btn <?= $tab === 'own' ? 'btn--primary' : 'btn--ghost' ?>" role="tab" aria-selected="<?= $tab === 'own' ? 'true' : 'false' ?>">Meus exercícios</a>
</div>

<div class="section">
  <form method="GET" action="<?= \Core\app_url('/teacher/bank') ?>" class="form form--inline">
    <input type="hidden" name="tab" value="<?= \Core\View::e($tab) ?>">
    <?php if ($targetId): ?><input type="hidden" name="exercise" value="<?= $targetId ?>"><?php endif; ?>
    <?php if ($theme !== '' && $tab === 'bank'): ?><input type="hidden" name="theme" value="<?= \Core\View::e($theme) ?>"><?php endif; ?>
    <?php if ($category > 0 && $tab === 'bank'): ?><input type="hidden" name="category" value="<?= $category ?>"><?php endif; ?>
    <input class="form-input" type="search" name="q" value="<?= \Core\View::e($search) ?>"
      placeholder="Buscar por título, descrição ou enunciado" aria-label="Buscar no banco">
    <button type="submit" class="btn">Buscar</button>
    <?php if ($search !== '' || $theme !== '' || $category > 0): ?>
      <a href="<?= $bankUrl(['tab' => $tab]) ?>" class="btn btn--ghost">Limpar filtros</a>
    <?php endif; ?>
  </form>

  <?php if ($tab === 'bank' && $themes !== []):
    $byCategory = [];
    foreach ($themes as $t) {
      $byCategory[(string) $t['category_name']]['id'] = (int) ($t['category_id'] ?? 0);
      $byCategory[(string) $t['category_name']]['subs'][] = $t;
    }
  ?>
    <?php foreach ($byCategory as $categoryName => $group):
      $categoryActive = $category > 0 && $category === $group['id'];
    ?>
      <div class="badge-strip" aria-label="Filtrar por categoria e subcategoria">
        <a href="<?= $bankUrl(['tab' => 'bank', 'q' => $search, 'category' => $categoryActive ? '' : $group['id']]) ?>"
          class="badge <?= $categoryActive ? 'badge--info' : 'badge--neutral' ?>"<?= $categoryActive ? ' aria-current="true"' : '' ?>>
          <strong><?= \Core\View::e($categoryName) ?></strong>
        </a>
        <?php foreach ($group['subs'] as $t): $isActive = $theme === $t['name']; ?>
          <a href="<?= $bankUrl(['tab' => 'bank', 'q' => $search, 'category' => $group['id'] ?: '', 'theme' => $isActive ? '' : $t['name']]) ?>"
            class="badge <?= $isActive ? 'badge--info' : 'badge--neutral' ?>"
            <?= $isActive ? 'aria-current="true"' : '' ?>>
            <?= \Core\View::e($t['name']) ?> (<?= (int) $t['item_count'] ?>)
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($items === []): ?>
  <p class="empty-state">
    <?php if ($tab === 'own'): ?>
      Nenhum exercício seu Concluído ou Publicado<?= $search !== '' ? ' com esse termo' : '' ?>.
    <?php elseif ($search !== '' || $theme !== '' || $category > 0): ?>
      Nenhum exercício do banco corresponde aos filtros.
    <?php else: ?>
      O banco institucional ainda não tem exercícios. Você pode sugerir os seus a partir do detalhe de um exercício Concluído.
    <?php endif; ?>
  </p>
<?php else: ?>
  <?php foreach ($items as $item):
    $group = 'bank-' . $source . '-' . (int) $item['id'];
  ?>
    <section class="surface-block section">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title"><?= \Core\View::e($item['title']) ?></h2>
          <?php if (!empty($item['description'])): ?>
            <p class="surface-copy"><?= \Core\View::e($item['description']) ?></p>
          <?php endif; ?>
          <div class="badge-strip">
            <span class="badge badge--neutral"><?= (int) $item['question_count'] ?> questão(ões)</span>
            <span class="badge badge--neutral"><?= number_format((float) $item['total_score'], 1) ?> pts</span>
            <?php if (!empty($item['category_name'])): ?><span class="badge badge--neutral"><?= \Core\View::e($item['category_name']) ?></span><?php endif; ?>
            <?php foreach ($item['themes'] as $itemTheme): ?>
              <span class="badge badge--info"><?= \Core\View::e($itemTheme) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="surface-block__body">
        <details class="hint-details">
          <summary>Ver e escolher questões</summary>
          <form method="POST" action="<?= \Core\app_url('/teacher/bank/import') ?>" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <input type="hidden" name="source" value="<?= $source ?>">
            <input type="hidden" name="source_id" value="<?= (int) $item['id'] ?>">
            <?php if ($targetId): ?><input type="hidden" name="exercise_id" value="<?= $targetId ?>"><?php endif; ?>

            <label class="form-label">
              <input type="checkbox" data-select-all="<?= $group ?>" aria-label="Selecionar todas as questões deste exercício">
              Selecionar todas
            </label>

            <div class="checkbox-grid">
              <?php foreach ($item['questions'] as $i => $q): ?>
                <label class="choice-card">
                  <input type="checkbox" name="question_ids[]" value="<?= (int) $q['id'] ?>" data-select-item="<?= $group ?>">
                  <span>
                    <small>Q<?= $i + 1 ?> · <?= number_format((float) $q['max_score'], 1) ?> pts</small>
                    <strong><?= nl2br(\Core\View::e($q['text'])) ?></strong>
                    <?php if (!empty($q['expected_answer_hint'])): ?>
                      <details class="hint-details">
                        <summary>Resposta de referência</summary>
                        <p class="hint-text"><?= nl2br(\Core\View::e($q['expected_answer_hint'])) ?></p>
                      </details>
                    <?php endif; ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <?php if (!$targetId): ?>
              <div class="form-group">
                <label class="form-label" for="title-<?= $group ?>">Título do novo Rascunho</label>
                <input class="form-input" type="text" id="title-<?= $group ?>" name="title" value="<?= \Core\View::e($item['title']) ?>" maxlength="200" required>
              </div>
            <?php endif; ?>

            <div class="form-actions">
              <span class="selection-summary" data-selection-count="<?= $group ?>">0 selecionadas</span>
              <button type="submit" class="btn btn--primary" data-requires-selection="<?= $group ?>" disabled>
                <?= $targetId ? 'Adicionar ao Rascunho' : 'Criar Rascunho com as selecionadas' ?>
              </button>
            </div>
          </form>
        </details>
      </div>
    </section>
  <?php endforeach; ?>

  <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
<?php endif; ?>
