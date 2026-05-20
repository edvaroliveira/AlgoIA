<!DOCTYPE html>
<html lang="pt-br">

<?php $currentPath = \Core\app_request_path(); ?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= \Core\View::e($pageTitle ?? 'IAProg') ?></title>
  <link rel="stylesheet" href="<?= \Core\app_url('/assets/css/app.css') ?>">
</head>

<body class="layout">
  <?php global $session; ?>

  <aside class="sidebar">
    <div class="sidebar__brand">
      <span class="brand-icon">⚙</span>
      <span class="brand-name">IAProg</span>
    </div>

    <nav class="sidebar__nav">
      <?php if (\Core\Auth::isTeacher()): ?>
        <a href="<?= \Core\app_url('/teacher/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="nav-link <?= str_contains($currentPath, 'turmas') ? 'active' : '' ?>">🏫 Turmas</a>
        <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">📝 Exercícios</a>
        <a href="<?= \Core\app_url('/teacher/students') ?>" class="nav-link <?= str_contains($currentPath, 'students') ? 'active' : '' ?>">👨‍🎓 Alunos</a>
      <?php else: ?>
        <a href="<?= \Core\app_url('/student/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="<?= \Core\app_url('/student/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">📝 Exercícios</a>
      <?php endif; ?>
    </nav>

    <div class="sidebar__footer">
      <span class="user-name"><?= \Core\View::e(\Core\Auth::user()['name'] ?? '') ?></span>
      <form method="POST" action="<?= \Core\app_url('/logout') ?>">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <button type="submit" class="logout-link">Sair</button>
      </form>
    </div>
  </aside>

  <main class="main-content">
    <?php
    $flash_success = $session->getFlash('success');
    $flash_error   = $session->getFlash('error');
    ?>
    <?php if ($flash_success): ?>
      <div class="alert alert--success"><?= \Core\View::e($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert--error"><?= \Core\View::e($flash_error) ?></div>
    <?php endif; ?>

    <?= $content ?>
  </main>

  <script src="<?= \Core\app_url('/assets/js/app.js') ?>"></script>
</body>

</html>
