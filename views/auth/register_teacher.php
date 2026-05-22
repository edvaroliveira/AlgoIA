<?php global $session; ?>

<?php if (!empty($disabled)): ?>
  <div class="auth-eyebrow">Cadastro de docente</div>
  <h2 class="auth-title">Cadastro indisponível</h2>
  <p class="auth-copy">O cadastro público de docentes está temporariamente desabilitado. Entre em contato com a administração da instituição.</p>
  <p class="form-footer"><a href="<?= \Core\app_url('/login') ?>">Ir para o login</a></p>

<?php elseif (isset($success)): ?>
  <div class="alert alert--success"><?= \Core\View::e($success) ?></div>
  <p class="form-footer"><a href="<?= \Core\app_url('/login') ?>">Ir para o login</a></p>

<?php else: ?>

  <div class="auth-eyebrow">Cadastro de docente</div>
  <h2 class="auth-title">Solicitar acesso</h2>
  <p class="auth-copy">Preencha os dados abaixo para solicitar acesso ao sistema como docente. Sua solicitação será analisada pela administração.</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert--error"><?= \Core\View::e($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert--error">
      <?php foreach ($errors as $e): ?>
        <div><?= \Core\View::e($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="<?= \Core\app_url('/register/teacher') ?>" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

    <div class="form-group">
      <label class="form-label" for="name">Nome completo</label>
      <input class="form-input" type="text" id="name" name="name"
        value="<?= \Core\View::e($old['name'] ?? '') ?>"
        maxlength="200" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">E-mail institucional</label>
      <input class="form-input" type="email" id="email" name="email"
        value="<?= \Core\View::e($old['email'] ?? '') ?>"
        required autocomplete="email">
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Senha <span class="hint">(mínimo 10 caracteres, com maiúscula, minúscula e número)</span></label>
      <input class="form-input" type="password" id="password" name="password"
        required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar senha</label>
      <input class="form-input" type="password" id="password_confirm" name="password_confirm"
        required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label class="form-label" for="institution">Instituição / Justificativa <span class="hint">(máx. 500 caracteres)</span></label>
      <textarea class="form-input" id="institution" name="institution"
        rows="3" maxlength="500"
        required><?= \Core\View::e($old['institution'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="btn btn--primary btn--full">Enviar solicitação</button>
  </form>

  <p class="form-footer">
    Já tem conta? <a href="<?= \Core\app_url('/login') ?>">Entrar</a>
  </p>

<?php endif; ?>
