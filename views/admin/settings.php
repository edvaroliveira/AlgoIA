<?php
use App\Services\EnvSettings;

$pageTitle = 'Configurações';

global $session;
$groups = $groups ?? [];
$values = $values ?? [];
$overridden = $overridden ?? [];
?>

<div class="page-header">
  <div>
    <h1>Configurações do ambiente</h1>
    <p class="subtitle">Edita o arquivo .env do servidor. Cada grupo é testado antes de salvar; senhas e chaves nunca aparecem inteiras. Para salvar, confirme sua senha.</p>
  </div>
</div>

<?php if (!($writable ?? false)): ?>
  <div class="alert alert--error">O servidor não consegue gravar o arquivo .env (permissões). As configurações só podem ser vistas.</div>
<?php endif; ?>

<?php foreach ($groups as $groupKey => $group): ?>
  <div class="section" id="grupo-<?= $groupKey ?>">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title"><?= \Core\View::e($group['title']) ?></h2>
          <p class="surface-copy"><?= \Core\View::e($group['help']) ?></p>
        </div>
      </div>
      <div class="surface-block__body">
        <form method="POST" action="<?= \Core\app_url('/admin/configuracoes/' . $groupKey) ?>" autocomplete="off" class="settings-form">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <div class="settings-grid">
            <?php foreach ($group['fields'] as $key => $field):
              $current = $values[$key] ?? null;
              $id = 'env-' . strtolower($key);
            ?>
              <div class="form-group">
                <label class="form-label" for="<?= $id ?>">
                  <?= \Core\View::e($field['label']) ?> <small><code><?= $key ?></code></small>
                </label>
                <?php if ($field['type'] === 'select'): ?>
                  <select class="form-input" id="<?= $id ?>" name="<?= $key ?>">
                    <?php foreach ($field['options'] as $option => $label): ?>
                      <option value="<?= \Core\View::e($option) ?>"<?= (string) ($current ?? $field['default'] ?? '') === (string) $option ? ' selected' : '' ?>><?= \Core\View::e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($field['type'] === 'secret'): ?>
                  <input class="form-input" type="password" id="<?= $id ?>" name="<?= $key ?>" value="" autocomplete="new-password"
                    placeholder="<?= $current !== null && $current !== '' ? \Core\View::e(EnvSettings::display($field, $current)) . ' — em branco mantém' : 'não definido' ?>">
                <?php else: ?>
                  <input class="form-input" type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>" id="<?= $id ?>" name="<?= $key ?>"
                    value="<?= \Core\View::e(EnvSettings::display($field, $current)) ?>"
                    <?= isset($field['min']) ? 'min="' . (int) $field['min'] . '" max="' . (int) $field['max'] . '"' : '' ?>>
                <?php endif; ?>
                <?php if (in_array($key, $overridden, true)): ?>
                  <small class="settings-warning">Definido também nas variáveis do servidor, que têm prioridade sobre o .env.</small>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="settings-confirm">
            <div class="form-group">
              <label class="form-label" for="pwd-<?= $groupKey ?>">Sua senha de Admin</label>
              <input class="form-input" type="password" id="pwd-<?= $groupKey ?>" name="admin_password" required autocomplete="current-password">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn--primary"<?= ($writable ?? false) ? '' : ' disabled' ?>>Testar e salvar</button>
            </div>
          </div>
        </form>
      </div>
    </section>
  </div>
<?php endforeach; ?>
