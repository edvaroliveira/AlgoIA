<?php
$pageTitle = 'Editar usuário — Administração';
$user = $user ?? [];
$errors = $errors ?? [];
global $session;
?>

<div class="page-header">
  <div>
    <h1>Editar usuário</h1>
    <p class="subtitle">Edição controlada de perfil, papel e status com preservação do acesso administrativo crítico.</p>
  </div>
  <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost">Voltar</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?= \Core\View::e(implode(' ', $errors)) ?>
  </div>
<?php endif; ?>

<section class="card card--narrow">
  <div class="card-body">
    <form method="POST" action="<?= \Core\app_url('/admin/users/' . ($user['id'] ?? 0)) ?>" class="form">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

      <div class="form-group">
        <label class="form-label" for="edit-user-name">Nome</label>
        <input id="edit-user-name" type="text" name="name" class="form-input" value="<?= \Core\View::e($user['name'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="edit-user-email">E-mail</label>
        <input id="edit-user-email" type="email" name="email" class="form-input" value="<?= \Core\View::e($user['email'] ?? '') ?>" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="edit-user-role">Perfil</label>
          <select id="edit-user-role" name="role" class="form-input">
            <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
            <option value="teacher" <?= ($user['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Docente</option>
            <option value="student" <?= ($user['role'] ?? '') === 'student' ? 'selected' : '' ?>>Aluno</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit-user-status">Status</label>
          <select id="edit-user-status" name="status" class="form-input">
            <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativo</option>
            <option value="pending" <?= ($user['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pendente</option>
            <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
      </div>

      <div class="form-actions td-actions">
        <button type="submit" class="btn btn--primary">Salvar alterações</button>
        <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost">Cancelar</a>
      </div>
    </form>
  </div>
</section>
