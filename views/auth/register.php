<?php global $session; ?>

<?php if (isset($success)): ?>
  <div class="alert alert--success"><?= \Core\View::e($success) ?></div>
  <p class="form-footer"><a href="/login">Ir para o login</a></p>
<?php else: ?>

  <?php if (!empty($errors)): ?>
    <div class="alert alert--error">
      <?php foreach ($errors as $e): ?>
        <div><?= \Core\View::e($e) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/register" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

    <div class="form-group">
      <label class="form-label" for="name">Nome completo</label>
      <input class="form-input" type="text" id="name" name="name"
        value="<?= \Core\View::e($old['name'] ?? '') ?>"
        required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label" for="email">E-mail</label>
      <input class="form-input" type="email" id="email" name="email"
        value="<?= \Core\View::e($old['email'] ?? '') ?>"
        required autocomplete="email">
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Senha <span class="hint">(mínimo 8 caracteres)</span></label>
      <input class="form-input" type="password" id="password" name="password"
        required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label class="form-label" for="password_confirm">Confirmar senha</label>
      <input class="form-input" type="password" id="password_confirm" name="password_confirm"
        required autocomplete="new-password">
    </div>

    <div class="form-group">
      <label class="form-label" for="turma_key">
        Chave da Turma
        <span class="hint">(fornecida pelo seu docente — 6 caracteres)</span>
      </label>
      <input class="form-input form-input--key" type="text" id="turma_key" name="turma_key"
        value="<?= \Core\View::e($old['turmaKey'] ?? '') ?>"
        maxlength="6" style="text-transform:uppercase; letter-spacing:.25em"
        required>
    </div>

    <button type="submit" class="btn btn--primary btn--full">Cadastrar</button>
  </form>

  <p class="form-footer">
    Já tem conta? <a href="/login">Entrar</a>
  </p>

<?php endif; ?>
