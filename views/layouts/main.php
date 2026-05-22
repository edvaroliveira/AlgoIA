<!DOCTYPE html>
<html lang="pt-br">

<?php
$currentPath = \Core\app_request_path();
global $session;
$content = $content ?? '';
?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#173f31">
  <title><?= \Core\View::e($pageTitle ?? 'AlgoIA') ?></title>
  <link rel="icon" type="image/x-icon" href="<?= \Core\app_url('/assets/img/algoIA.ico') ?>">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= \Core\app_url('/assets/css/app.css') ?>">
</head>

<body class="layout">
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar__brand">
        <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo.png') ?>" alt="Logo AlgoIA" class="brand-logo brand-logo--sidebar">
        <small class="brand-subtitle">Aprendizagem • Algoritmos • Amazônia</small>
      </div>

      <div class="sidebar__section-label">Navegação</div>
      <nav class="sidebar__nav">
        <?php if (\Core\Auth::isAdmin()): ?>
          <a href="<?= \Core\app_url('/admin/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'admin') ? 'active' : '' ?>">Painel administrativo</a>
        <?php elseif (\Core\Auth::isTeacher()): ?>
          <a href="<?= \Core\app_url('/teacher/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Painel docente</a>
          <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="nav-link <?= str_contains($currentPath, 'turmas') ? 'active' : '' ?>">Turmas</a>
          <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">Exercícios</a>
          <a href="<?= \Core\app_url('/teacher/students') ?>" class="nav-link <?= str_contains($currentPath, 'students') ? 'active' : '' ?>">Alunos</a>
        <?php else: ?>
          <a href="<?= \Core\app_url('/student/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Meu painel</a>
          <a href="<?= \Core\app_url('/student/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">Exercícios</a>
        <?php endif; ?>
      </nav>

      <div class="sidebar__footer">
        <div class="sidebar-user-label">Usuário autenticado</div>
        <div class="user-name"><?= \Core\View::e(\Core\Auth::user()['name'] ?? '') ?></div>
        <div class="user-role"><?= \Core\Auth::isAdmin() ? 'Administrador' : (\Core\Auth::isTeacher() ? 'Docente' : 'Aluno') ?></div>
        <form method="POST" action="<?= \Core\app_url('/logout') ?>">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <button type="submit" class="btn btn--ghost btn--full">Encerrar sessão</button>
        </form>
      </div>
    </aside>

    <main class="main-content">
      <div class="content-shell">
        <header class="app-topbar">
          <div>
            <div class="app-kicker">Aprendizagem • Algoritmos • Amazônia</div>
            <h1 class="app-title"><?= \Core\View::e($pageTitle ?? 'Painel') ?></h1>
          </div>
          <div class="topbar-card">
            <span class="topbar-dot"></span>
            <span><?= \Core\Auth::isTeacher() ? 'Modo docente ativo' : 'Modo aluno ativo' ?></span>
          </div>
        </header>

        <section class="content-panel">
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
        </section>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \Core\app_url('/assets/js/app.js') ?>"></script>
</body>

</html>
