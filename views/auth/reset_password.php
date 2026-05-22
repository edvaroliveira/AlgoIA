<?php global $session; ?>
<?php $validToken = $validToken ?? false; ?>

<div class="auth-eyebrow">Redefinição de senha</div>
<h2 class="auth-title">Crie uma nova senha</h2>
<p class="auth-copy">Use o link de redefinição recebido da administração para definir uma nova senha de acesso.</p>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?php foreach ($errors as $e): ?>
      <div><?= \Core\View::e($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!$validToken): ?>
  <div class="alert alert--error">Link de redefinição inválido ou expirado. Solicite um novo reset de senha.</div>
  <p class="form-footer"><a href="<?= \Core\app_url('/login') ?>">Voltar para o login</a></p>
<?php else: ?>
  <form method="POST" action="<?= \Core\app_url('/password/reset') ?>" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
    <input type="hidden" name="token" value="<?= \Core\View::e($token ?? '') ?>">

    <div class="form-group">
      <label class="form-label" for="password">Nova senha <span class="hint">(mínimo 10 caracteres, com maiúscula, minúscula e número)</span></label>
      <input class="form-input" type="password" id="password" name="password"
        required autofocus autocomplete="new-password">
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar nova senha</label>
      <input class="form-input" type="password" id="password_confirm" name="password_confirm"
        required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn--primary btn--full">Redefinir senha</button>
  </form>
<?php endif; ?>
