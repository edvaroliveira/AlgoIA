<?php
/**
 * Campos de classificação (categoria + subcategorias). Espera:
 * $tree (Category::tree(true)), $selectedCategory (?int), $selectedThemes (int[]), $prefix (string).
 */
$tree = $tree ?? [];
$selectedCategory = $selectedCategory ?? null;
$selectedThemes = array_map('intval', $selectedThemes ?? []);
$prefix = $prefix ?? 'cls';
?>
<div class="classification-fields">
  <p class="form-label">Categoria e subcategorias <span class="hint">(escolha uma categoria e marque as subcategorias dela)</span></p>
  <?php if ($tree === []): ?>
    <p class="empty-state">Nenhuma categoria cadastrada. Peça ao Admin para cadastrar em Categorias.</p>
  <?php endif; ?>
  <?php foreach ($tree as $category): ?>
    <fieldset class="classification-group">
      <legend>
        <label>
          <input type="radio" name="category_id" value="<?= (int) $category['id'] ?>"<?= $selectedCategory === (int) $category['id'] ? ' checked' : '' ?>>
          <?= \Core\View::e($category['name']) ?>
        </label>
      </legend>
      <div class="classification-subs">
        <?php foreach ($category['subs'] as $sub): ?>
          <label class="checkbox-label" for="<?= $prefix ?>-sub-<?= (int) $sub['id'] ?>">
            <input type="checkbox" id="<?= $prefix ?>-sub-<?= (int) $sub['id'] ?>" name="theme_ids[]" value="<?= (int) $sub['id'] ?>"<?= in_array((int) $sub['id'], $selectedThemes, true) ? ' checked' : '' ?>>
            <?= \Core\View::e($sub['name']) ?>
          </label>
        <?php endforeach; ?>
        <?php if ($category['subs'] === []): ?><small>Sem subcategorias ativas.</small><?php endif; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
</div>
