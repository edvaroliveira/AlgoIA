<!DOCTYPE html>
<html lang="pt-br">

<?php
$currentPath = '/' . trim(\Core\app_request_path(), '/');
global $session;
$content = $content ?? '';

// Menu por perfil: grupos → itens [rótulo, rota, prefixos que marcam o item como atual].
// Prefixo casa com a rota inteira ou seguido de "/" (evita /admin/ia casar /admin/iaxyz).
if (\Core\Auth::isAdmin()) {
  $areaLabel = 'Área administrativa';
  $navGroups = [
    'Visão geral' => [['Painel', '/admin/dashboard', ['/admin/dashboard', '/admin/attempts']]],
    'Pessoas'     => [
      ['Usuários', '/admin/users', ['/admin/users', '/admin/teacher-requests']],
      ['Turmas', '/admin/turmas', ['/admin/turmas']],
    ],
    'Conteúdo'    => [
      ['Exercícios', '/admin/exercises', ['/admin/exercises', '/admin/questions']],
      ['Banco de exercícios', '/admin/bank', ['/admin/bank']],
      ['Categorias', '/admin/categorias', ['/admin/categorias']],
    ],
    'Sistema'     => [
      ['Auditoria', '/admin/audit', ['/admin/audit']],
      ['Uso da IA', '/admin/ia', ['/admin/ia']],
      ['Configurações', '/admin/configuracoes', ['/admin/configuracoes']],
    ],
  ];
} elseif (\Core\Auth::isTeacher()) {
  $areaLabel = 'Área docente';
  $navGroups = [
    'Navegação' => [
      ['Painel', '/teacher/dashboard', ['/teacher/dashboard']],
      ['Turmas', '/teacher/turmas', ['/teacher/turmas']],
      ['Exercícios', '/teacher/exercises', ['/teacher/exercises', '/teacher/bank', '/teacher/attempts', '/teacher/questions', '/teacher/answers', '/teacher/score-reviews']],
      ['Alunos', '/teacher/students', ['/teacher/students']],
    ],
  ];
} else {
  $areaLabel = 'Área do aluno';
  $navGroups = [
    'Estudar'     => [
      ['Meu painel', '/student/dashboard', ['/student/dashboard']],
      ['Exercícios', '/student/exercises', ['/student/exercises', '/student/attempts', '/student/answers']],
      ['Prática', '/student/pratica', ['/student/pratica']],
      ['Agenda', '/student/agenda', ['/student/agenda']],
    ],
    'Acompanhar'  => [
      ['Progresso', '/student/progresso', ['/student/progresso']],
      ['Ranking', '/student/ranking', ['/student/ranking']],
      ['Mural', '/student/mural', ['/student/mural']],
    ],
  ];
}
$isCurrent = static function (array $prefixes) use ($currentPath): bool {
  foreach ($prefixes as $prefix) {
    if ($currentPath === $prefix || str_starts_with($currentPath, $prefix . '/')) {
      return true;
    }
  }
  return false;
};
?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#173f31">
  <title><?= \Core\View::e(\Core\View::documentTitle($pageTitle ?? null)) ?></title>
  <link rel="icon" type="image/x-icon" href="<?= \Core\app_url('/assets/img/algoIA.ico') ?>">
  <link rel="stylesheet" href="<?= \Core\asset_url('/assets/css/app.css') ?>">
</head>

<body class="layout">
  <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
  <div class="app-shell">
    <aside class="sidebar" id="menu-lateral" aria-label="Menu principal">
      <div class="sidebar__brand">
        <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo_branco.png') ?>" alt="AlgoIA" class="brand-logo brand-logo--sidebar">
        <small class="brand-subtitle">Aprendizagem • Algoritmos • Amazônia</small>
      </div>

      <nav class="sidebar__nav" aria-label="Navegação principal">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
          <div class="sidebar__group">
            <div class="sidebar__section-label"><?= \Core\View::e($groupLabel) ?></div>
            <?php foreach ($items as [$label, $route, $prefixes]): $current = $isCurrent($prefixes); ?>
              <a href="<?= \Core\app_url($route) ?>" class="nav-link<?= $current ? ' active' : '' ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= \Core\View::e($label) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
    </aside>
    <div class="sidebar-backdrop" data-drawer-close hidden></div>

    <div class="main-content">
      <div class="content-shell">
        <header class="app-topbar">
          <button type="button" class="topbar-menu" aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menu">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
          </button>
          <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo_transp.png') ?>" alt="AlgoIA" class="topbar-logo">
          <div class="app-context"><?= \Core\View::e($areaLabel) ?></div>
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

        <main class="content-panel" id="conteudo" tabindex="-1">
          <?php
          $flash_success = $session->getFlash('success');
          $flash_error   = $session->getFlash('error');
          ?>
          <?php if ($flash_success): ?>
            <div class="alert alert--success alert--dismissible" role="status">
              <span><?= \Core\View::e($flash_success) ?></span>
              <button type="button" class="alert__close" data-dismiss-alert aria-label="Fechar aviso">×</button>
            </div>
          <?php endif; ?>
          <?php if ($flash_error): ?>
            <div class="alert alert--error alert--dismissible" role="alert">
              <span><?= \Core\View::e($flash_error) ?></span>
              <button type="button" class="alert__close" data-dismiss-alert aria-label="Fechar aviso">×</button>
            </div>
          <?php endif; ?>

          <?= $content ?>
        </main>
      </div>
    </div>
  </div>

  <script src="<?= \Core\asset_url('/assets/js/app.js') ?>"></script>
</body>

</html>
