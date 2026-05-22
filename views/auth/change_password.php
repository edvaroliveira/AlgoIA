<?php global $session; ?>

<div class="auth-eyebrow">Troca obrigatória</div>
<h2 class="auth-title">Defina uma nova senha</h2>
<p class="auth-copy">Sua senha foi redefinida pela administração. Escolha uma senha definitiva para continuar.</p>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?php foreach ($errors as $e): ?>
      <div><?= \Core\View::e($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST" action="<?= \Core\app_url('/password/change') ?>" class="form">
  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

  <div class="form-group">
    <label class="form-label" for="current_password">Senha temporária</label>
    <input class="form-input" type="password" id="current_password" name="current_password"
      required autofocus autocomplete="current-password">
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Nova senha <span class="hint">(mínimo 10 caracteres, com maiúscula, minúscula e número)</span></label>
    <input class="form-input" type="password" id="password" name="password"
      required autocomplete="new-password">
  </div>

  <div class="form-group">
    <label class="form-label" for="password_confirm">Confirmar nova senha</label>
    <input class="form-input" type="password" id="password_confirm" name="password_confirm"
      required autocomplete="new-password">
  </div>

  <button type="submit" class="btn btn--primary btn--full">Atualizar senha</button>
</form>

<form method="POST" action="<?= \Core\app_url('/logout') ?>" class="form-footer">
  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
  <button type="submit" class="btn btn--ghost btn--full">Sair</button>
</form>
