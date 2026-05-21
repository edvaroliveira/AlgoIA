<?php
$exercise = $exercise ?? [];
$turmas = $turmas ?? [];
$pageTitle = 'Editar Exercício';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Editar exercício</h1>
    <p class="subtitle">Ajuste metadados, janela de acesso e política de tentativas sem sair da área docente.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error">
    <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="editor-layout">
  <section class="surface-block editor-main">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Parâmetros do exercício</h2>
        <p class="surface-copy">Atualize a configuração geral sem alterar aqui o conjunto de questões.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

        <div class="form-group">
          <label class="form-label" for="title">Título</label>
          <input class="form-input" type="text" id="title" name="title"
            value="<?= \Core\View::e($exercise['title'] ?? '') ?>" required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Descrição</label>
          <textarea class="form-input form-textarea" id="description" name="description" rows="4"><?= \Core\View::e($exercise['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="turma_id">Turma</label>
          <select class="form-input" id="turma_id" name="turma_id" required>
            <?php foreach ($turmas as $t): ?>
              <option value="<?= $t['id'] ?>" <?= ($exercise['turma_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>
                <?= \Core\View::e($t['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="opens_at">Abertura</label>
            <input class="form-input" type="datetime-local" id="opens_at" name="opens_at"
              value="<?= !empty($exercise['opens_at']) ? date('Y-m-d\TH:i', strtotime($exercise['opens_at'])) : '' ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="closes_at">Fechamento</label>
            <input class="form-input" type="datetime-local" id="closes_at" name="closes_at"
              value="<?= !empty($exercise['closes_at']) ? date('Y-m-d\TH:i', strtotime($exercise['closes_at'])) : '' ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="max_attempts">Máximo de tentativas <span class="hint">(0 = ilimitado)</span></label>
          <input class="form-input form-input--short" type="number" id="max_attempts" name="max_attempts"
            value="<?= \Core\View::e($exercise['max_attempts'] ?? 0) ?>" min="0" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Salvar alterações</button>
          <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">Cancelar</a>
        </div>
      </form>
    </div>
  </section>

  <aside class="editor-side">
    <section class="surface-block info-panel">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Leitura rápida</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <div class="info-step">
          <strong>Troca de turma</strong>
          <p>Ao mover o exercício, confira se a nova turma é realmente o destino correto da atividade.</p>
        </div>
        <div class="info-step">
          <strong>Janela de entrega</strong>
          <p>Evite fechar cedo demais se o exercício já estiver em andamento para alunos ativos.</p>
        </div>
        <div class="info-step">
          <strong>Questões separadas</strong>
          <p>Para mexer no conteúdo das questões, volte à tela do exercício e use a área específica.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
