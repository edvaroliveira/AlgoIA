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
    <p class="overview-card__copy">Todos os usuários cadastrados na base.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Administradores</span>
    <strong class="overview-card__value"><?= $adminCount ?></strong>
    <p class="overview-card__copy">Perfis com acesso administrativo global.</p>
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
      <table class="table">
        <thead>
          <tr>
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
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
