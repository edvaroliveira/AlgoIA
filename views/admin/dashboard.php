<?php
$pageTitle = 'Dashboard — Administração';
$totalUsers = $totalUsers ?? 0;
$adminCount = $adminCount ?? 0;
$teacherCount = $teacherCount ?? 0;
$studentCount = $studentCount ?? 0;
$turmaCount = $turmaCount ?? 0;
$exerciseCount = $exerciseCount ?? 0;
$auditCount = $auditCount ?? 0;
$pendingUserCount = $pendingUserCount ?? 0;
$pendingEnrollmentCount = $pendingEnrollmentCount ?? 0;
$closingSoonCount = $closingSoonCount ?? 0;
$pendingGradingCount = $pendingGradingCount ?? 0;
$pendingUsers = $pendingUsers ?? [];
$pendingTurmas = $pendingTurmas ?? [];
$closingExercises = $closingExercises ?? [];
$pendingGradingAttempts = $pendingGradingAttempts ?? [];
$recentAdminEvents = $recentAdminEvents ?? [];
$pendingActions = $pendingActions ?? [];
global $session;
$today = date('Y-m-d');
$last7Days = date('Y-m-d', strtotime('-7 days'));
$last30Days = date('Y-m-d', strtotime('-30 days'));
$entityBadgeMap = [
  'user' => 'info',
  'turma' => 'warning',
  'exercise' => 'success',
  'student' => 'neutral',
];
$roleBadgeMap = [
  'admin' => 'neutral',
  'teacher' => 'info',
  'student' => 'success',
];
$pendingUsersBadgeVariant = $pendingUserCount > 0 ? 'warning' : 'neutral';
$pendingUsersBadgeText = $pendingUserCount > 0 ? 'exige triagem' : 'sem fila';
$pendingEnrollmentBadgeVariant = $pendingEnrollmentCount > 0 ? 'warning' : 'neutral';
$pendingEnrollmentBadgeText = $pendingEnrollmentCount > 0 ? 'aguardando docentes' : 'sem bloqueios';
$closingSoonBadgeVariant = $closingSoonCount > 0 ? 'error' : 'success';
$closingSoonBadgeText = $closingSoonCount > 0 ? 'janela crítica' : 'ritmo estável';
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
    <span class="overview-card__label">Usuários pendentes</span>
    <strong class="overview-card__value"><?= $pendingUserCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($pendingUsersBadgeVariant) ?>"><?= \Core\View::e($pendingUsersBadgeText) ?></span></span>
    <p class="overview-card__copy">Cadastros ainda aguardando ativação ou decisão operacional.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendências de entrada</span>
    <strong class="overview-card__value"><?= $pendingEnrollmentCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($pendingEnrollmentBadgeVariant) ?>"><?= \Core\View::e($pendingEnrollmentBadgeText) ?></span></span>
    <p class="overview-card__copy">Solicitações aguardando aprovação docente nas turmas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Fechando em breve</span>
    <strong class="overview-card__value"><?= $closingSoonCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($closingSoonBadgeVariant) ?>"><?= \Core\View::e($closingSoonBadgeText) ?></span></span>
    <p class="overview-card__copy">Exercícios ativos com encerramento previsto nas próximas 72 horas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Correções pendentes</span>
    <strong class="overview-card__value"><?= $pendingGradingCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= $pendingGradingCount > 0 ? 'error' : 'success' ?>"><?= $pendingGradingCount > 0 ? 'reprocessar' : 'sem fila' ?></span></span>
    <p class="overview-card__copy">Tentativas enviadas que ainda aguardam nota automática.</p>
  </article>
</div>

<?php if (!empty($pendingGradingAttempts)): ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Correções pendentes</h2>
        <p class="surface-copy">Tentativas enviadas que ficaram aguardando reprocessamento da avaliação automática.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Aluno</th>
            <th>Exercício</th>
            <th>Docente</th>
            <th>Turma</th>
            <th>Enviada em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingGradingAttempts as $attempt): ?>
            <tr>
              <td><?= \Core\View::e($attempt['student_name'] ?? '—') ?></td>
              <td><?= \Core\View::e($attempt['exercise_title'] ?? '—') ?></td>
              <td><?= \Core\View::e($attempt['teacher_name'] ?? '—') ?></td>
              <td><?= \Core\View::e($attempt['turma_name'] ?? '—') ?></td>
              <td><?= !empty($attempt['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $attempt['submitted_at'])) : '—' ?></td>
              <td class="td-actions">
                <form method="POST" action="<?= \Core\app_url('/admin/attempts/' . (int) ($attempt['id'] ?? 0) . '/regrade') ?>">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <input type="hidden" name="return_to" value="<?= \Core\View::e(\Core\app_request_path()) ?>">
                  <button type="submit" class="btn btn--sm btn--primary">Reprocessar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($pendingActions)): ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Fila operacional priorizada</h2>
        <p class="surface-copy">Pendências ordenadas por urgência para reduzir navegação dispersa no módulo administrativo.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Sinal</th>
            <th>Item</th>
            <th>Contexto</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingActions as $pendingAction): ?>
            <tr>
              <td><span class="badge badge--<?= \Core\View::e($pendingAction['variant'] ?? 'neutral') ?>"><?= \Core\View::e($pendingAction['label'] ?? 'ação') ?></span></td>
              <td><strong><?= \Core\View::e($pendingAction['title'] ?? 'Item') ?></strong></td>
              <td><?= \Core\View::e($pendingAction['description'] ?? 'Sem contexto adicional') ?></td>
              <td class="td-actions"><a href="<?= \Core\app_url((string) ($pendingAction['path'] ?? '/admin/dashboard')) ?>" class="btn btn--sm"><?= \Core\View::e($pendingAction['action_label'] ?? 'Abrir') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

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
              <?php
              $pendingCount = (int) ($turma['pending_count'] ?? 0);
              $oldestPendingAt = !empty($turma['oldest_pending_at']) ? date('d/m/Y H:i', strtotime((string) $turma['oldest_pending_at'])) : 'Sem fila antiga';
              ?>
              <tr>
                <td><strong><?= \Core\View::e($turma['name'] ?? '—') ?></strong></td>
                <td><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></td>
                <td><span class="badge badge--warning"><?= $pendingCount ?> pendência<?= $pendingCount === 1 ? '' : 's' ?></span></td>
                <td><span class="badge badge--neutral badge--code"><?= \Core\View::e($oldestPendingAt) ?></span></td>
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
              <?php
              $closingAt = !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime((string) $exercise['closes_at'])) : 'Sem fechamento';
              $attemptCount = (int) ($exercise['attempt_count'] ?? 0);
              ?>
              <tr>
                <td><strong><?= \Core\View::e($exercise['title'] ?? '—') ?></strong></td>
                <td><?= \Core\View::e($exercise['teacher_name'] ?? '—') ?></td>
                <td><span class="badge badge--info"><?= \Core\View::e($exercise['turma_label'] ?? '—') ?></span></td>
                <td><span class="badge badge--error badge--code"><?= \Core\View::e($closingAt) ?></span></td>
                <td><span class="badge badge--neutral"><?= $attemptCount ?> tentativa<?= $attemptCount === 1 ? '' : 's' ?></span></td>
                <td class="td-actions"><a href="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0)) ?>" class="btn btn--sm">Detalhes</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="cards-grid">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Usuários aguardando decisão</h2>
        <p class="surface-copy">Cadastros pendentes mais recentes que ainda exigem acompanhamento.</p>
      </div>
      <a href="<?= \Core\app_url('/admin/users?status=pending') ?>" class="btn btn--ghost btn--sm">Ver pendentes</a>
    </div>
    <div class="surface-block__body">
      <?php if (empty($pendingUsers)): ?>
        <p class="empty-state">Nenhum usuário pendente no momento.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Perfil</th>
              <th>Criado em</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingUsers as $user): ?>
              <?php
              $userRole = (string) ($user['role'] ?? '—');
              $userRoleVariant = $roleBadgeMap[$userRole] ?? 'neutral';
              $createdAt = !empty($user['created_at']) ? date('d/m/Y H:i', strtotime((string) $user['created_at'])) : '—';
              ?>
              <tr>
                <td><strong><?= \Core\View::e($user['name'] ?? '—') ?></strong></td>
                <td><?= \Core\View::e($user['email'] ?? '—') ?></td>
                <td><span class="badge badge--<?= \Core\View::e($userRoleVariant) ?> badge--code"><?= \Core\View::e($userRole) ?></span></td>
                <td><span class="badge badge--neutral badge--code"><?= \Core\View::e($createdAt) ?></span></td>
                <td class="td-actions"><a href="<?= \Core\app_url('/admin/users/' . ($user['id'] ?? 0)) ?>" class="btn btn--sm">Detalhes</a></td>
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
        <h2 class="surface-title">Atividade administrativa recente</h2>
        <p class="surface-copy">Últimas ações de administração registradas na trilha de auditoria.</p>
      </div>
      <a href="<?= \Core\app_url('/admin/audit?action=admin.') ?>" class="btn btn--ghost btn--sm">Abrir auditoria</a>
    </div>
    <div class="surface-block__body">
      <?php if (empty($recentAdminEvents)): ?>
        <p class="empty-state">Nenhuma ação administrativa registrada ainda.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Quando</th>
              <th>Ator</th>
              <th>Ação</th>
              <th>Entidade</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentAdminEvents as $event): ?>
              <?php
              $eventAction = (string) ($event['action'] ?? '—');
              $eventEntityType = (string) ($event['entity_type'] ?? '—');
              $eventEntityLabel = $eventEntityType . (($event['entity_id'] ?? null) ? ' #' . $event['entity_id'] : '');
              $eventActionVariant = str_starts_with($eventAction, 'admin.') ? 'neutral' : 'info';
              $eventEntityVariant = $entityBadgeMap[$eventEntityType] ?? 'neutral';
              ?>
              <tr>
                <td><?= !empty($event['created_at']) ? date('d/m/Y H:i', strtotime((string) $event['created_at'])) : '—' ?></td>
                <td>
                  <strong><?= \Core\View::e($event['actor_name'] ?? 'Sistema') ?></strong><br>
                  <span class="text-muted"><?= \Core\View::e($event['actor_email'] ?? ($event['actor_role'] ?? 'admin')) ?></span>
                </td>
                <td><span class="badge badge--<?= \Core\View::e($eventActionVariant) ?> badge--code"><?= \Core\View::e($eventAction) ?></span></td>
                <td><span class="badge badge--<?= \Core\View::e($eventEntityVariant) ?> badge--code"><?= \Core\View::e($eventEntityLabel) ?></span></td>
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
      <div class="badge-strip">
        <span class="badge badge--info">filtros globais</span>
        <span class="badge badge--success">ativação</span>
        <span class="badge badge--warning">pendentes</span>
        <span class="badge badge--neutral">exportação</span>
      </div>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/users/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/users/export.json') ?>" class="btn btn--sm btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/users?status=pending') ?>" class="btn btn--sm btn--ghost">Pendentes</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Turmas</strong>
      <p>Supervisão global da operação das turmas com drill-down e reativação administrativa.</p>
      <div class="badge-strip">
        <span class="badge badge--info">drill-down</span>
        <span class="badge badge--warning">pendências</span>
        <span class="badge badge--success">reativação</span>
        <span class="badge badge--neutral">exportação</span>
      </div>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/turmas') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/turmas/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/turmas/export.json') ?>" class="btn btn--sm btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/turmas?attention=pending') ?>" class="btn btn--sm btn--ghost">Com pendências</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Exercícios</strong>
      <p>Visão global das atividades com encerramento, reabertura e exportação administrativa.</p>
      <div class="badge-strip">
        <span class="badge badge--error">encerramento</span>
        <span class="badge badge--success">reabertura</span>
        <span class="badge badge--warning">fechando em breve</span>
        <span class="badge badge--neutral">exportação</span>
      </div>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/exercises/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/exercises/export.json') ?>" class="btn btn--sm btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/exercises?timing=closing_soon') ?>" class="btn btn--sm btn--ghost">Fechando em breve</a>
      </div>
    </div>
    <div class="content-note">
      <strong>Auditoria</strong>
      <p>Trilha operacional com filtros por ação, entidade, período e exportação CSV.</p>
      <div class="badge-strip">
        <span class="badge badge--neutral">atividade admin</span>
        <span class="badge badge--info">entidade</span>
        <span class="badge badge--warning">7 dias</span>
        <span class="badge badge--neutral">30 dias</span>
      </div>
      <div class="td-actions">
        <a href="<?= \Core\app_url('/admin/audit') ?>" class="btn btn--sm">Abrir módulo</a>
        <a href="<?= \Core\app_url('/admin/audit/export') ?>" class="btn btn--sm btn--ghost">Exportar CSV</a>
        <a href="<?= \Core\app_url('/admin/audit/export.json') ?>" class="btn btn--sm btn--ghost">Exportar JSON</a>
        <a href="<?= \Core\app_url('/admin/audit?action=admin.') ?>" class="btn btn--sm btn--ghost">Atividade admin</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=user') ?>" class="btn btn--sm btn--ghost">Usuários</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=turma') ?>" class="btn btn--sm btn--ghost">Turmas</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=exercise') ?>" class="btn btn--sm btn--ghost">Exercícios</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=user&from_date=' . $last7Days . '&to_date=' . $today) ?>" class="btn btn--sm btn--ghost">Usuários 7 dias</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=turma&from_date=' . $last7Days . '&to_date=' . $today) ?>" class="btn btn--sm btn--ghost">Turmas 7 dias</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=exercise&from_date=' . $last7Days . '&to_date=' . $today) ?>" class="btn btn--sm btn--ghost">Exercícios 7 dias</a>
        <a href="<?= \Core\app_url('/admin/audit?entity_type=student&from_date=' . $last7Days . '&to_date=' . $today) ?>" class="btn btn--sm btn--ghost">Alunos 7 dias</a>
        <a href="<?= \Core\app_url('/admin/audit?from_date=' . $last30Days . '&to_date=' . $today) ?>" class="btn btn--sm btn--ghost">Últimos 30 dias</a>
      </div>
    </div>
  </div>
</section>
