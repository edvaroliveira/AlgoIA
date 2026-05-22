<?php
$pageTitle = 'Dashboard — Administração';
$totalUsers = $totalUsers ?? 0;
$adminCount = $adminCount ?? 0;
$teacherCount = $teacherCount ?? 0;
$studentCount = $studentCount ?? 0;
?>

<section class="hero-panel hero-panel--teacher">
  <div>
    <div class="hero-panel__eyebrow">Administração</div>
    <h2 class="hero-panel__title">Painel administrativo inicial.</h2>
    <p class="hero-panel__copy">A fundação do perfil de administrador já está ativa. As próximas entregas entram por módulos de governança, usuários, auditoria e supervisão global.</p>
  </div>
  <div class="hero-panel__meta">
    <span class="hero-chip">Acesso global habilitado</span>
    <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost btn--sm">Abrir usuários</a>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Usuários</span>
    <strong class="overview-card__value"><?= $totalUsers ?></strong>
    <p class="overview-card__copy">Base total já visível para governança administrativa.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Administradores</span>
    <strong class="overview-card__value"><?= $adminCount ?></strong>
    <p class="overview-card__copy">Perfis com acesso global ao sistema.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Docentes</span>
    <strong class="overview-card__value"><?= $teacherCount ?></strong>
    <p class="overview-card__copy">Usuários que gerenciam turmas e exercícios.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos</span>
    <strong class="overview-card__value"><?= $studentCount ?></strong>
    <p class="overview-card__copy">Usuários vinculados às turmas da plataforma.</p>
  </article>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Próximos módulos</h2>
      <p class="surface-copy">A fundação administrativa já suporta login, navegação e leitura global de usuários. As próximas entregas entram como módulos dedicados.</p>
    </div>
  </div>
  <div class="surface-block__body surface-block__body--stack">
    <div class="content-note">
      <strong>Usuários</strong>
      <p>Listagem global concluída como primeira superfície operacional do administrador.</p>
    </div>
    <div class="content-note">
      <strong>Turmas e exercícios</strong>
      <p>Próxima expansão recomendada para supervisão cruzada entre docentes e alunos.</p>
    </div>
    <div class="content-note">
      <strong>Auditoria</strong>
      <p>Etapa seguinte para transformar a governança em trilha operacional confiável.</p>
    </div>
  </div>
</section>
