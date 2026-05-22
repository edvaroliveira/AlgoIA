<?php
$pageTitle = 'Usuários — Administração';
$users = $users ?? [];
$totalUsers = count($users);
$adminCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'admin'));
$teacherCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'teacher'));
$studentCount = count(array_filter($users, static fn(array $user): bool => ($user['role'] ?? '') === 'student'));
?>

<div class="page-header">
  <div>
    <h1>Usuários do sistema</h1>
    <p class="subtitle">Visão global inicial de administradores, docentes e alunos com seus vínculos principais.</p>
  </div>
</div>

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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
