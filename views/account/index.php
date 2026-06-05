<?php
$pageTitle = 'Minha conta';
global $session;

$name   = $user['name'] ?? '';
$email  = $user['email'] ?? '';
$avatar = (is_string($user['avatar_path'] ?? null) && $user['avatar_path'] !== '') ? $user['avatar_path'] : null;
$initial = mb_strtoupper(mb_substr(trim($name), 0, 1));

$backUrl = \Core\Auth::isAdmin()
  ? '/admin/dashboard'
  : (\Core\Auth::isTeacher() ? '/teacher/dashboard' : '/student/dashboard');
?>

<div class="page-header">
  <div>
    <h1>Minha conta</h1>
    <p class="subtitle">Sua foto aparece no topo do painel. Use uma imagem nítida e bem enquadrada.</p>
  </div>
  <a href="<?= \Core\app_url($backUrl) ?>" class="btn btn--ghost">← Voltar</a>
</div>

<section class="surface-block account-card">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Foto de perfil</h2>
      <p class="surface-copy">JPG, PNG ou WebP, até 2&nbsp;MB. A imagem é recortada em um quadrado e otimizada automaticamente.</p>
    </div>
  </div>

  <div class="surface-block__body account-avatar-row">
    <div class="account-avatar" aria-hidden="<?= $avatar ? 'true' : 'false' ?>">
      <?php if ($avatar): ?>
        <img src="<?= \Core\app_url('/assets/uploads/' . $avatar) ?>" alt="Foto de <?= \Core\View::e($name) ?>">
      <?php else: ?>
        <span class="account-avatar__initial"><?= \Core\View::e($initial) ?></span>
      <?php endif; ?>
    </div>

    <div class="account-avatar-controls">
      <form method="POST" action="<?= \Core\app_url('/conta/foto') ?>" enctype="multipart/form-data" class="avatar-uploader">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp" class="avatar-uploader__input">
        <label for="avatar" class="btn btn--ghost avatar-uploader__pick">Escolher imagem…</label>
        <span class="avatar-uploader__filename" data-placeholder="Nenhum arquivo selecionado">Nenhum arquivo selecionado</span>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary"><?= $avatar ? 'Atualizar foto' : 'Enviar foto' ?></button>
        </div>
      </form>

      <?php if ($avatar): ?>
        <form method="POST" action="<?= \Core\app_url('/conta/foto/remover') ?>" class="avatar-remove">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <button type="submit" class="btn btn--ghost btn--sm avatar-remove__btn">Remover foto</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="surface-block account-card">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Dados da conta</h2>
      <p class="surface-copy">Informações de identificação. Alterações de cadastro são feitas pela administração.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <dl class="account-facts">
      <div class="account-fact">
        <dt>Nome</dt>
        <dd><?= \Core\View::e($name) ?></dd>
      </div>
      <div class="account-fact">
        <dt>E-mail</dt>
        <dd><?= \Core\View::e($email) ?></dd>
      </div>
      <div class="account-fact">
        <dt>Perfil</dt>
        <dd><?= \Core\View::e($roleLabel) ?></dd>
      </div>
    </dl>
  </div>
</section>
