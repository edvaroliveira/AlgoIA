<?php
$pageTitle = 'Usuários — Administração';
$users = $users ?? [];
$filters = $filters ?? ['search' => '', 'role' => '', 'status' => ''];
$pagination = $pagination ?? ['totalPages' => 1, 'currentPage' => 1, 'totalItems' => count($users), 'path' => '/admin/users', 'query' => $filters];
$exportQuery = http_build_query(array_filter($filters, static fn($value): bool => (string) $value !== ''));
$totalUsers = count($users);
$adminCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'admin'));
$teacherCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'teacher'));
$studentCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'student'));
$totalUsersBadgeVariant = $totalUsers > 0 ? 'neutral' : 'warning';
$totalUsersBadgeText = $totalUsers > 0 ? 'base filtrada' : 'sem resultados';
$adminCountBadgeVariant = $adminCount > 0 ? 'neutral' : 'warning';
$adminCountBadgeText = $adminCount > 0 ? 'governança ativa' : 'sem administradores';
$teacherCountBadgeVariant = $teacherCount > 0 ? 'info' : 'neutral';
$teacherCountBadgeText = $teacherCount > 0 ? 'operação docente' : 'sem docentes';
$studentCountBadgeVariant = $studentCount > 0 ? 'success' : 'neutral';
$studentCountBadgeText = $studentCount > 0 ? 'base estudantil' : 'sem alunos';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Usuários do sistema</h1>
    <p class="subtitle">Visão global inicial de administradores, docentes e alunos com seus vínculos principais.</p>
  </div>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <form method="GET" action="<?= \Core\app_url('/admin/users') ?>" class="form">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="user-search">Buscar</label>
          <input id="user-search" type="text" name="search" class="form-input" value="<?= \Core\View::e($filters['search'] ?? '') ?>" placeholder="Nome ou e-mail">
        </div>
        <div class="form-group">
          <label class="form-label" for="user-role">Perfil</label>
          <select id="user-role" name="role" class="form-input">
            <option value="">Todos</option>
            <option value="admin" <?= ($filters['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
            <option value="teacher" <?= ($filters['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Docente</option>
            <option value="student" <?= ($filters['role'] ?? '') === 'student' ? 'selected' : '' ?>>Aluno</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="user-status">Status</label>
          <select id="user-status" name="status" class="form-input">
            <option value="">Todos</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativo</option>
            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendente</option>
            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
        <div class="form-group" style="justify-content: flex-end;">
          <label class="form-label">Ações</label>
          <div class="td-actions">
            <button type="submit" class="btn btn--primary">Filtrar</button>
            <a href="<?= \Core\app_url('/admin/users/export' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar CSV</a>
            <a href="<?= \Core\app_url('/admin/users/export.json' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar JSON</a>
            <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost">Limpar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Total</span>
    <strong class="overview-card__value"><?= $totalUsers ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($totalUsersBadgeVariant) ?>"><?= \Core\View::e($totalUsersBadgeText) ?></span></span>
    <p class="overview-card__copy">Todos os usuários cadastrados na base.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Administradores</span>
    <strong class="overview-card__value"><?= $adminCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($adminCountBadgeVariant) ?>"><?= \Core\View::e($adminCountBadgeText) ?></span></span>
    <p class="overview-card__copy">Perfis com acesso administrativo global.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Docentes</span>
    <strong class="overview-card__value"><?= $teacherCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($teacherCountBadgeVariant) ?>"><?= \Core\View::e($teacherCountBadgeText) ?></span></span>
    <p class="overview-card__copy">Usuários que gerenciam turmas e exercícios.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos</span>
    <strong class="overview-card__value"><?= $studentCount ?></strong>
    <span class="overview-card__signal"><span class="badge badge--<?= \Core\View::e($studentCountBadgeVariant) ?>"><?= \Core\View::e($studentCountBadgeText) ?></span></span>
    <p class="overview-card__copy">Usuários vinculados às turmas da plataforma.</p>
  </article>
</div>

<?php if (empty($users)): ?>
  <p class="empty-state">Nenhum usuário encontrado.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista global</h2>
        <p class="surface-copy">Base inicial de governança com foco em leitura e conferência dos vínculos existentes.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form id="admin-users-batch-form" method="POST">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <input type="hidden" name="return_query" value="<?= \Core\View::e($exportQuery) ?>">
      </form>
      <table class="table">
        <thead>
          <tr>
            <th>
              <label>
                <input type="checkbox" form="admin-users-batch-form" data-select-all="admin-users-list" aria-label="Selecionar todos os usuários da listagem">
                Todos
              </label>
            </th>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Perfil</th>
            <th>Status</th>
            <th>Contexto</th>
            <th>Criado em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php
            $role = $user['role'] ?? 'student';
            $status = $user['status'] ?? 'inactive';
            $context = '—';
            $statusStateLabel = $status === 'active' ? 'ativos' : ($status === 'pending' ? 'pendentes' : 'inativos');

            if ($role === 'teacher') {
              $context = (int) ($user['owned_turma_count'] ?? 0) . ' turma(s) · ' . (int) ($user['exercise_count'] ?? 0) . ' exercício(s)';
            } elseif ($role === 'student') {
              $context = !empty($user['turma_names'])
                ? \Core\View::e((string) $user['turma_names'])
                : 'Sem turma';
            } elseif ($role === 'admin') {
              $context = 'Acesso global';
            }
            ?>
            <tr>
              <td><input type="checkbox" form="admin-users-batch-form" name="user_ids[]" value="<?= (int) ($user['id'] ?? 0) ?>" data-select-item="admin-users-list" data-item-state="<?= \Core\View::e($status) ?>" data-item-state-label="<?= \Core\View::e($statusStateLabel) ?>"></td>
              <td><strong><?= \Core\View::e($user['name']) ?></strong></td>
              <td><?= \Core\View::e($user['email']) ?></td>
              <td>
                <?php if ($role === 'admin'): ?>
                  <span class="badge badge--info">Administrador</span>
                <?php elseif ($role === 'teacher'): ?>
                  <span class="badge badge--success">Docente</span>
                <?php else: ?>
                  <span class="badge badge--neutral">Aluno</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($status === 'active'): ?>
                  <span class="badge badge--success">Ativo</span>
                <?php elseif ($status === 'pending'): ?>
                  <span class="badge badge--warning">Pendente</span>
                <?php else: ?>
                  <span class="badge badge--neutral">Inativo</span>
                <?php endif; ?>
              </td>
              <td><?= $context ?></td>
              <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
              <td class="td-actions">
                <a href="<?= \Core\app_url('/admin/users/' . $user['id']) ?>" class="btn btn--sm btn--ghost">Detalhes</a>
                <a href="<?= \Core\app_url('/admin/users/' . $user['id'] . '/edit') ?>" class="btn btn--sm">Editar</a>
                <form method="POST" action="<?= \Core\app_url('/admin/users/' . $user['id'] . '/status') ?>">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <input type="hidden" name="status" value="<?= $status === 'active' ? 'inactive' : 'active' ?>">
                  <button type="submit" class="btn btn--sm <?= $status === 'active' ? 'btn--ghost' : '' ?>">
                    <?= $status === 'active' ? 'Inativar' : 'Ativar' ?>
                  </button>
                </form>
                <form method="POST" action="<?= \Core\app_url('/admin/users/' . $user['id'] . '/reset-password') ?>">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <button type="submit" class="btn btn--sm btn--ghost">Resetar senha</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="form-row">
        <div class="form-group" style="justify-content: flex-end;">
          <label class="form-label">Ações em lote</label>
          <div class="td-actions">
            <span class="selection-summary" data-selection-count="admin-users-list">0 selecionados</span>
            <span class="selection-summary" data-selection-breakdown="admin-users-list"></span>
            <span class="selection-summary" data-selection-compatibility="admin-users-list"></span>
            <button type="submit" form="admin-users-batch-form" formaction="<?= \Core\app_url('/admin/users/batch-activate') ?>" class="btn btn--primary" data-requires-selection="admin-users-list" data-allowed-states="inactive,pending" disabled>Ativar selecionados</button>
            <button type="submit" form="admin-users-batch-form" formaction="<?= \Core\app_url('/admin/users/batch-deactivate') ?>" class="btn btn--danger" data-requires-selection="admin-users-list" data-allowed-states="active" onclick="return confirm('Inativar os usuários selecionados?');" disabled>Inativar selecionados</button>
          </div>
        </div>
      </div>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
