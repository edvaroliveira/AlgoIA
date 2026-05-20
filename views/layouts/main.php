<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= \Core\View::e($pageTitle ?? 'IAProg') ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="layout">

  <aside class="sidebar">
    <div class="sidebar__brand">
      <span class="brand-icon">⚙</span>
      <span class="brand-name">IAProg</span>
    </div>

    <nav class="sidebar__nav">
      <?php if (\Core\Auth::isTeacher()): ?>
        <a href="/teacher/dashboard" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="/teacher/turmas" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'turmas') ? 'active' : '' ?>">🏫 Turmas</a>
        <a href="/teacher/exercises" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'exercises') ? 'active' : '' ?>">📝 Exercícios</a>
        <a href="/teacher/students" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'students') ? 'active' : '' ?>">👨‍🎓 Alunos</a>
      <?php else: ?>
        <a href="/student/dashboard" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="/student/exercises" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'], 'exercises') ? 'active' : '' ?>">📝 Exercícios</a>
      <?php endif; ?>
    </nav>

    <div class="sidebar__footer">
      <span class="user-name"><?= \Core\View::e(\Core\Auth::user()['name'] ?? '') ?></span>
      <a href="/logout" class="logout-link">Sair</a>
    </div>
  </aside>

  <main class="main-content">
    <?php
    global $session;
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

  <script src="/assets/js/app.js"></script>
</body>

</html>
