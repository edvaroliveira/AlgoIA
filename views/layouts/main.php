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
  <link rel="stylesheet" href="<?= \Core\asset_url('/assets/css/app.css') ?>">
</head>

<body class="layout">
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar__brand">
        <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo_branco.png') ?>" alt="Logo AlgoIA" class="brand-logo brand-logo--sidebar">
        <small class="brand-subtitle">Aprendizagem • Algoritmos • Amazônia</small>
      </div>

      <div class="sidebar__section-label">Navegação</div>
      <nav class="sidebar__nav">
        <?php if (\Core\Auth::isAdmin()): ?>
          <a href="<?= \Core\app_url('/admin/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'admin/dashboard') ? 'active' : '' ?>">Painel administrativo</a>
          <a href="<?= \Core\app_url('/admin/users') ?>" class="nav-link <?= str_contains($currentPath, 'admin/users') ? 'active' : '' ?>">Usuários</a>
          <a href="<?= \Core\app_url('/admin/turmas') ?>" class="nav-link <?= str_contains($currentPath, 'admin/turmas') ? 'active' : '' ?>">Turmas</a>
          <a href="<?= \Core\app_url('/admin/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'admin/exercises') ? 'active' : '' ?>">Exercícios</a>
          <a href="<?= \Core\app_url('/admin/bank') ?>" class="nav-link <?= str_contains($currentPath, 'admin/bank') ? 'active' : '' ?>">Banco de exercícios</a>
          <a href="<?= \Core\app_url('/admin/audit') ?>" class="nav-link <?= str_contains($currentPath, 'admin/audit') ? 'active' : '' ?>">Auditoria</a>
          <a href="<?= \Core\app_url('/admin/ia') ?>" class="nav-link <?= str_contains($currentPath, 'admin/ia') ? 'active' : '' ?>">Uso da IA</a>
          <a href="<?= \Core\app_url('/admin/configuracoes') ?>" class="nav-link <?= str_contains($currentPath, 'admin/configuracoes') ? 'active' : '' ?>">Configurações</a>
        <?php elseif (\Core\Auth::isTeacher()): ?>
          <a href="<?= \Core\app_url('/teacher/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Painel docente</a>
          <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="nav-link <?= str_contains($currentPath, 'turmas') ? 'active' : '' ?>">Turmas</a>
          <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') || str_contains($currentPath, 'teacher/bank') ? 'active' : '' ?>">Exercícios</a>
          <a href="<?= \Core\app_url('/teacher/students') ?>" class="nav-link <?= str_contains($currentPath, 'students') ? 'active' : '' ?>">Alunos</a>
        <?php else: ?>
          <a href="<?= \Core\app_url('/student/dashboard') ?>" class="nav-link <?= str_contains($currentPath, 'dashboard') ? 'active' : '' ?>">Meu painel</a>
          <a href="<?= \Core\app_url('/student/exercises') ?>" class="nav-link <?= str_contains($currentPath, 'exercises') ? 'active' : '' ?>">Exercícios</a>
          <a href="<?= \Core\app_url('/student/agenda') ?>" class="nav-link <?= str_contains($currentPath, 'student/agenda') ? 'active' : '' ?>">Agenda</a>
          <a href="<?= \Core\app_url('/student/mural') ?>" class="nav-link <?= str_contains($currentPath, 'student/mural') ? 'active' : '' ?>">Mural</a>
          <a href="<?= \Core\app_url('/student/pratica') ?>" class="nav-link <?= str_contains($currentPath, 'student/pratica') ? 'active' : '' ?>">Prática</a>
          <a href="<?= \Core\app_url('/student/progresso') ?>" class="nav-link <?= str_contains($currentPath, 'student/progresso') ? 'active' : '' ?>">Progresso</a>
          <a href="<?= \Core\app_url('/student/ranking') ?>" class="nav-link <?= str_contains($currentPath, 'student/ranking') ? 'active' : '' ?>">Ranking</a>
        <?php endif; ?>
      </nav>

    </aside>

    <main class="main-content">
      <div class="content-shell">
        <header class="app-topbar">
          <div>
            <div class="app-kicker">Aprendizagem • Algoritmos • Amazônia</div>
            <h1 class="app-title"><?= \Core\View::e($pageTitle ?? 'Painel') ?></h1>
          </div>
          <?php
          $userName = \Core\Auth::user()['name'] ?? '';
          $userRole = \Core\Auth::isAdmin() ? 'Administrador' : (\Core\Auth::isTeacher() ? 'Docente' : 'Aluno');
          $userInitial = mb_strtoupper(mb_substr(trim($userName), 0, 1));
          $userAvatar = \Core\Auth::avatar();
          // Avisos: lembretes de prazo do Aluno são gerados aqui (sem depender de cron).
          // Falhar ao montar o sino nunca pode derrubar a página.
          $unreadNotifications = 0;
          try {
            $notificationModel = new \App\Models\Notification();
            if (\Core\Auth::isStudent()) {
              $notificationModel->generateDeadlineReminders((int) \Core\Auth::id());
            }
            $unreadNotifications = $notificationModel->unreadCount((int) \Core\Auth::id());
          } catch (\Throwable $e) {
            error_log('notifications bell failed: ' . $e->getMessage());
          }
          ?>
          <div class="topbar-actions">
            <a class="topbar-bell <?= $unreadNotifications > 0 ? 'topbar-bell--unread' : '' ?>" href="<?= \Core\app_url('/notificacoes') ?>"
              data-unread="<?= $unreadNotifications ?>"
              title="<?= $unreadNotifications > 0 ? $unreadNotifications . ' aviso(s) não lido(s)' : 'Avisos' ?>"
              aria-label="Avisos<?= $unreadNotifications > 0 ? ': ' . $unreadNotifications . ' não lido(s)' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3.5a1.5 1.5 0 0 0-3 0v.66A7 7 0 0 0 5 11v5l-2 2v1h18v-1Z"/></svg>
              <?php if ($unreadNotifications > 0): ?>
                <span class="topbar-bell__count"><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
              <?php endif; ?>
            </a>
            <span class="topbar-card">
              <span class="topbar-dot"></span>
              <span><?= \Core\Auth::isAdmin() ? 'Modo administrativo ativo' : (\Core\Auth::isTeacher() ? 'Modo docente ativo' : 'Modo aluno ativo') ?></span>
            </span>
            <div class="topbar-user">
              <a class="topbar-user__identity" href="<?= \Core\app_url('/conta') ?>" title="Minha conta">
                <?php if ($userAvatar): ?>
                  <img class="topbar-user__avatar topbar-user__avatar--img"
                       src="<?= \Core\app_url('/assets/uploads/' . $userAvatar) ?>"
                       alt="Foto de <?= \Core\View::e($userName) ?>">
                <?php else: ?>
                  <span class="topbar-user__avatar" aria-hidden="true"><?= \Core\View::e($userInitial) ?></span>
                <?php endif; ?>
                <span class="topbar-user__meta">
                  <span class="topbar-user__name"><?= \Core\View::e($userName) ?></span>
                  <span class="topbar-user__role"><?= \Core\View::e($userRole) ?></span>
                </span>
              </a>
              <form method="POST" action="<?= \Core\app_url('/logout') ?>">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button type="submit" class="btn btn--ghost btn--sm">Sair</button>
              </form>
            </div>
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
  <script src="<?= \Core\asset_url('/assets/js/app.js') ?>"></script>
</body>

</html>
