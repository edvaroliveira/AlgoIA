<?php global $session; ?>
<?php if (isset($error)): ?>
  <div class="alert alert--error"><?= \Core\View::e($error) ?></div>
<?php endif; ?>

<div class="auth-eyebrow">Acesso do sistema</div>
<h2 class="auth-title">Entrar na plataforma</h2>
<p class="auth-copy">Use seu e-mail institucional e a senha cadastrada para continuar.</p>

<form method="POST" action="<?= \Core\app_url('/login') ?>" class="form">
  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

  <div class="form-group">
    <label class="form-label" for="email">E-mail</label>
    <input class="form-input" type="email" id="email" name="email"
      value="<?= \Core\View::e($old['email'] ?? '') ?>"
      required autofocus autocomplete="email">
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Senha</label>
    <input class="form-input" type="password" id="password" name="password"
      required autocomplete="current-password">
  </div>

  <button type="submit" class="btn btn--primary btn--full">Entrar</button>
</form>

<p class="form-footer">
  Não tem conta? <a href="<?= \Core\app_url('/register') ?>">Cadastre-se como aluno</a>
  &nbsp;·&nbsp;
  <a href="<?= \Core\app_url('/register/teacher') ?>">Sou docente</a>
</p>
