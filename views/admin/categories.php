<?php
$pageTitle = 'Categorias';
global $session;
$tree = $tree ?? [];
$csrf = '<input type="hidden" name="_csrf_token" value="' . \Core\View::e($session->csrfToken()) . '">';
?>

<div class="page-header">
  <div>
    <h1>Categorias</h1>
    <p class="subtitle">Classificação dos Exercícios e do banco: uma categoria e uma ou mais subcategorias (ex.: Algoritmos → Vetores). Itens desativados somem das escolhas, mas as classificações existentes continuam.</p>
  </div>
</div>

<section class="surface-block">
  <div class="surface-block__body">
    <form method="POST" action="<?= \Core\app_url('/admin/categorias') ?>" class="form-group">
      <?= $csrf ?>
      <label class="form-label" for="new-category">Nova categoria</label>
      <div class="category-inline">
        <input class="form-input" type="text" id="new-category" name="name" placeholder="Ex.: Algoritmos" maxlength="<?= \App\Models\Category::NAME_MAX ?>" required>
        <button type="submit" class="btn btn--primary btn--sm">Criar categoria</button>
      </div>
    </form>
  </div>
</section>

<?php if ($tree === []): ?>
  <p class="empty-state section">Nenhuma categoria cadastrada.</p>
<?php endif; ?>

<?php foreach ($tree as $category): ?>
  <div class="section">
    <section class="surface-block<?= $category['active'] ? '' : ' category--inactive' ?>">
      <div class="surface-block__header category-header">
        <div>
          <h2 class="surface-title">
            <?= \Core\View::e($category['name']) ?>
            <?php if (!$category['active']): ?><span class="badge badge--neutral">Desativada</span><?php endif; ?>
          </h2>
        </div>
        <div class="category-actions">
          <form method="POST" action="<?= \Core\app_url('/admin/categorias/' . $category['id'] . '/renomear') ?>" class="category-inline">
            <?= $csrf ?>
            <input class="form-input" type="text" name="name" value="<?= \Core\View::e($category['name']) ?>" maxlength="<?= \App\Models\Category::NAME_MAX ?>" aria-label="Novo nome da categoria">
            <button type="submit" class="btn btn--ghost btn--sm">Renomear</button>
          </form>
          <form method="POST" action="<?= \Core\app_url('/admin/categorias/' . $category['id'] . '/status') ?>">
            <?= $csrf ?>
            <input type="hidden" name="active" value="<?= $category['active'] ? '0' : '1' ?>">
            <button type="submit" class="btn btn--ghost btn--sm"><?= $category['active'] ? 'Desativar' : 'Reativar' ?></button>
          </form>
        </div>
      </div>
      <div class="surface-block__body">
        <ul class="category-subs">
          <?php foreach ($category['subs'] as $sub): ?>
            <li class="<?= $sub['active'] ? '' : 'category--inactive' ?>">
              <form method="POST" action="<?= \Core\app_url('/admin/subcategorias/' . $sub['id'] . '/renomear') ?>" class="category-inline">
                <?= $csrf ?>
                <input class="form-input" type="text" name="name" value="<?= \Core\View::e($sub['name']) ?>" maxlength="<?= \App\Models\Category::SUB_NAME_MAX ?>" aria-label="Nome da subcategoria">
                <button type="submit" class="btn btn--ghost btn--sm">Renomear</button>
              </form>
              <form method="POST" action="<?= \Core\app_url('/admin/subcategorias/' . $sub['id'] . '/status') ?>">
                <?= $csrf ?>
                <input type="hidden" name="active" value="<?= $sub['active'] ? '0' : '1' ?>">
                <button type="submit" class="btn btn--ghost btn--sm"><?= $sub['active'] ? 'Desativar' : 'Reativar' ?></button>
              </form>
              <?php if (!$sub['active']): ?><span class="badge badge--neutral">Desativada</span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <form method="POST" action="<?= \Core\app_url('/admin/categorias/' . $category['id'] . '/subcategorias') ?>" class="category-inline">
          <?= $csrf ?>
          <input class="form-input" type="text" name="name" placeholder="Nova subcategoria" maxlength="<?= \App\Models\Category::SUB_NAME_MAX ?>" required aria-label="Nova subcategoria">
          <button type="submit" class="btn btn--primary btn--sm">Adicionar subcategoria</button>
        </form>
      </div>
    </section>
  </div>
<?php endforeach; ?>
