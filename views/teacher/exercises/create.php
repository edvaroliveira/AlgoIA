<?php $pageTitle = 'Novo Exercício';
global $session; ?>

<div class="page-header">
  <h1>Novo Exercício</h1>
  <a href="/teacher/exercises" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card card--narrow">
  <form method="POST" action="/teacher/exercises" class="form">
    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

    <div class="form-group">
      <label class="form-label" for="title">Título</label>
      <input class="form-input" type="text" id="title" name="title"
        value="<?= \Core\View::e($old['title'] ?? '') ?>" required autofocus>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Descrição <span class="hint">(opcional)</span></label>
      <textarea class="form-input form-textarea" id="description" name="description"
        rows="3"><?= \Core\View::e($old['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
      <label class="form-label" for="turma_id">Turma</label>
      <select class="form-input" id="turma_id" name="turma_id" required>
        <option value="">Selecione uma turma</option>
        <?php foreach ($turmas as $t): ?>
          <option value="<?= $t['id'] ?>" <?= ($old['turmaId'] ?? 0) == $t['id'] ? 'selected' : '' ?>>
            <?= \Core\View::e($t['name']) ?> (<?= \Core\View::e($t['access_key']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="opens_at">Abertura</label>
        <input class="form-input" type="datetime-local" id="opens_at" name="opens_at"
          value="<?= \Core\View::e($old['opensAt'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="closes_at">Fechamento</label>
        <input class="form-input" type="datetime-local" id="closes_at" name="closes_at"
          value="<?= \Core\View::e($old['closesAt'] ?? '') ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="max_attempts">
        Máximo de tentativas <span class="hint">(0 = ilimitado)</span>
      </label>
      <input class="form-input form-input--short" type="number" id="max_attempts" name="max_attempts"
        value="<?= \Core\View::e($old['maxAttempts'] ?? 1) ?>" min="0" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary">Criar exercício</button>
      <a href="/teacher/exercises" class="btn btn--ghost">Cancelar</a>
    </div>
  </form>
</div>
