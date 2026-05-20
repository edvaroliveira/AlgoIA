<?php $pageTitle = 'Nova Turma';
global $session; ?>

<div class="page-header">
  <h1>Nova Turma</h1>
  <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (isset($error)): ?>
  <div class="alert alert--error"><?= \Core\View::e($error) ?></div>
<?php endif; ?>

<div class="card card--narrow">
  <form method="POST" action="<?= \Core\app_url('/teacher/turmas') ?>" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

    <div class="form-group">
      <label class="form-label" for="name">Nome da turma</label>
      <input class="form-input" type="text" id="name" name="name"
        value="<?= \Core\View::e($old['name'] ?? '') ?>"
        required autofocus placeholder="Ex: Algoritmos 2025-1">
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Criar turma</button>
      <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">Cancelar</a>
    </div>
  </form>
</div>
