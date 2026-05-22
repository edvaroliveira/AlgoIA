<?php
$pageTitle = 'Dashboard — Administração';
$totalUsers = $totalUsers ?? 0;
$adminCount = $adminCount ?? 0;
$teacherCount = $teacherCount ?? 0;
$studentCount = $studentCount ?? 0;
$turmaCount = $turmaCount ?? 0;
$exerciseCount = $exerciseCount ?? 0;
$auditCount = $auditCount ?? 0;
$pendingEnrollmentCount = $pendingEnrollmentCount ?? 0;
$closingSoonCount = $closingSoonCount ?? 0;
$pendingTurmas = $pendingTurmas ?? [];
$closingExercises = $closingExercises ?? [];
?>

<section class="hero-panel hero-panel--teacher">
  <div>
    <div class="hero-panel__eyebrow">Administração</div>
    <h2 class="hero-panel__title">Painel administrativo operacional.</h2>
    <p class="hero-panel__copy">A área administrativa já cobre governança global de usuários, turmas, exercícios e auditoria, com filtros, detalhes e exportações.</p>
  </div>
  <div class="hero-panel__meta">
    <span class="hero-chip">Acesso global habilitado</span>
    <div class="td-actions">
      <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost btn--sm">Usuários</a>
      <a href="<?= \Core\app_url('/admin/audit') ?>" class="btn btn--ghost btn--sm">Auditoria</a>
    </div>
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
  <article class="overview-card">
    <span class="overview-card__label">Turmas</span>
    <strong class="overview-card__value"><?= $turmaCount ?></strong>
    <p class="overview-card__copy">Estruturas acadêmicas visíveis para supervisão administrativa.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Exercícios</span>
    <strong class="overview-card__value"><?= $exerciseCount ?></strong>
    <p class="overview-card__copy">Biblioteca total de atividades sob governança global.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Eventos de auditoria</span>
    <strong class="overview-card__value"><?= $auditCount ?></strong>
    <p class="overview-card__copy">Registros rastreáveis disponíveis para inspeção administrativa.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendências de entrada</span>
    <strong class="overview-card__value"><?= $pendingEnrollmentCount ?></strong>
    <p class="overview-card__copy">Solicitações aguardando aprovação docente nas turmas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Fechando em breve</span>
    <strong class="overview-card__value"><?= $closingSoonCount ?></strong>
    <p class="overview-card__copy">Exercícios ativos com encerramento previsto nas próximas 72 horas.</p>
  </article>
</div>

<div class="cards-grid">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Pendências que exigem atenção</h2>
        <p class="surface-copy">Turmas com solicitações de entrada aguardando decisão.</p>
      </div>
      <a href="<?= \Core\app_url('/admin/turmas') ?>" class="btn btn--ghost btn--sm">Ver turmas</a>
    </div>
    <div class="surface-block__body">
      <?php if (empty($pendingTurmas)): ?>
        <p class="empty-state">Nenhuma turma com pendência no momento.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Turma</th>
              <th>Docente</th>
              <th>Pendências</th>
              <th>Mais antiga</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingTurmas as $turma): ?>
              <tr>
                <td><strong><?= \Core\View::e($turma['name'] ?? '—') ?></strong></td>
                <td><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></td>
                <td><?= (int) ($turma['pending_count'] ?? 0) ?></td>
                <td><?= !empty($turma['oldest_pending_at']) ? date('d/m/Y H:i', strtotime((string) $turma['oldest_pending_at'])) : '—' ?></td>
                <td class="td-actions"><a href="<?= \Core\app_url('/admin/turmas/' . ($turma['id'] ?? 0)) ?>" class="btn btn--sm">Detalhes</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Exercícios encerrando em breve</h2>
        <p class="surface-copy">Publicações ativas com janela de encerramento próxima.</p>
      </div>
      <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--ghost btn--sm">Ver exercícios</a>
    </div>
    <div class="surface-block__body">
      <?php if (empty($closingExercises)): ?>
        <p class="empty-state">Nenhum exercício ativo com fechamento próximo.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Exercício</th>
              <th>Docente</th>
              <th>Turmas</th>
              <th>Fecha em</th>
              <th>Tentativas</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($closingExercises as $exercise): ?>
              <tr>
                <td><strong><?= \Core\View::e($exercise['title'] ?? '—') ?></strong></td>
                <td><?= \Core\View::e($exercise['teacher_name'] ?? '—') ?></td>
                <td><?= \Core\View::e($exercise['turma_label'] ?? '—') ?></td>
                <td><?= !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime((string) $exercise['closes_at'])) : '—' ?></td>
                <td><?= (int) ($exercise['attempt_count'] ?? 0) ?></td>
                <td class="td-actions"><a href="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0)) ?>" class="btn btn--sm">Detalhes</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Acessos rápidos</h2>
      <p class="surface-copy">Entradas diretas para operação diária e exportação administrativa sem depender de navegação em profundidade.</p>
    </div>
  </div>
  <div class="surface-block__body surface-block__body--stack">
    <div class="content-note">
      <strong>Usuários</strong>
      <p>Gestão global com filtros, edição, ativação, reset de senha e exportação CSV.</p>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/users/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Turmas</strong>
      <p>Supervisão global da operação das turmas com drill-down e reativação administrativa.</p>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/turmas') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/turmas/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Exercícios</strong>
      <p>Visão global das atividades com encerramento, reabertura e exportação administrativa.</p>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/exercises/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Auditoria</strong>
      <p>Trilha operacional com filtros por ação, entidade, período e exportação CSV.</p>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/audit') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/audit/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
      </div>
    </div>
  </div>
</section>
