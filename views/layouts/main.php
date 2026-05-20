<!DOCTYPE html>
<html lang="pt-br">

<?php $currentPath = \Core\app_request_path(); ?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0b4d78">
  <title><?= \Core\View::e($pageTitle ?? 'IAProg') ?></title>
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= \Core\app_url('/assets/css/app.css') ?>">
</head>

<body class="layout">
  <?php global $session; ?>

  <div class="app-shell d-lg-flex">
    <aside class="sidebar d-flex flex-column">
      <div class="sidebar__brand">
        <span class="brand-mark">IA</span>
        <div>
          <span class="brand-name d-block">IAProg</span>
          <small class="brand-subtitle">Aprendizagem orientada por IA</small>
        </div>
      </div>

      <nav class="sidebar__nav nav nav-pills flex-column gap-2">
        <?php if (\Core\Auth::isTeacher()): ?>
          <a href="<?= \Core\app_url('/teacher/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Dashboard</a>
          <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="nav-link <?= str_contains($currentPath, 'turmas') ? 'active' : '' ?>">Turmas</a>
          <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">Exercícios</a>
          <a href="<?= \Core\app_url('/teacher/students') ?>" class="nav-link <?= str_contains($currentPath, 'students') ? 'active' : '' ?>">Alunos</a>
        <?php else: ?>
          <a href="<?= \Core\app_url('/student/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Dashboard</a>
          <a href="<?= \Core\app_url('/student/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">Exercícios</a>
        <?php endif; ?>
      </nav>

      <div class="sidebar__footer mt-auto">
        <div class="sidebar-user-label">Sessão ativa</div>
        <div class="user-name"><?= \Core\View::e(\Core\Auth::user()['name'] ?? '') ?></div>
        <form method="POST" action="<?= \Core\app_url('/logout') ?>" class="mt-3">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <button type="submit" class="btn btn-outline-light btn-sm w-100">Sair</button>
        </form>
      </div>
    </aside>

    <main class="main-content flex-grow-1">
      <div class="content-shell container-fluid">
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
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \Core\app_url('/assets/js/app.js') ?>"></script>
</body>

</html>
