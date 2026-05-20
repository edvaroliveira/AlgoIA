<?php $pageTitle = 'Editar Exercício';
global $session; ?>

<div class="page-header">
  <h1>Editar Exercício</h1>
  <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card card--narrow">
  <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

    <div class="form-group">
      <label class="form-label" for="title">Título</label>
      <input class="form-input" type="text" id="title" name="title"
        value="<?= \Core\View::e($exercise['title']) ?>" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrição</label>
      <textarea class="form-input form-textarea" id="description" name="description" rows="3"><?= \Core\View::e($exercise['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="turma_id">Turma</label>
      <select class="form-input" id="turma_id" name="turma_id" required>
        <?php foreach ($turmas as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $exercise['turma_id'] == $t['id'] ? 'selected' : '' ?>>
            <?= \Core\View::e($t['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="opens_at">Abertura</label>
        <input class="form-input" type="datetime-local" id="opens_at" name="opens_at"
          value="<?= date('Y-m-d\TH:i', strtotime($exercise['opens_at'])) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="closes_at">Fechamento</label>
        <input class="form-input" type="datetime-local" id="closes_at" name="closes_at"
          value="<?= date('Y-m-d\TH:i', strtotime($exercise['closes_at'])) ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="max_attempts">Máximo de tentativas <span class="hint">(0 = ilimitado)</span></label>
      <input class="form-input form-input--short" type="number" id="max_attempts" name="max_attempts"
        value="<?= \Core\View::e($exercise['max_attempts']) ?>" min="0" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Salvar alterações</button>
      <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">Cancelar</a>
    </div>
  </form>
</div>
