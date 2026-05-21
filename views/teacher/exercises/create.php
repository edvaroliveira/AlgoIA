<?php
$pageTitle = 'Novo Exercício';
$turmas = $turmas ?? [];
global $session;
?>

<div class="page-header">
  <div>
    <h1>Criar exercício</h1>
    <p class="subtitle">Configure janela de abertura, turma e política de tentativas antes de adicionar as questões.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="btn btn--ghost">← Voltar</a>
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
        <h2 class="surface-title">Configuração inicial</h2>
        <p class="surface-copy">Nesta etapa você define o contêiner do exercício. As questões entram logo depois.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/teacher/exercises') ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

        <div class="form-group">
          <label class="form-label" for="title">Título</label>
          <input class="form-input" type="text" id="title" name="title"
            value="<?= \Core\View::e($old['title'] ?? '') ?>" required autofocus placeholder="Ex: Estruturas de repetição e decisão">
        </div>

        <div class="form-group">
          <label class="form-label" for="description">Descrição <span class="hint">(opcional)</span></label>
          <textarea class="form-input form-textarea" id="description" name="description"
            rows="4" placeholder="Contextualize o exercício, objetivos e critérios gerais."><?= \Core\View::e($old['description'] ?? '') ?></textarea>
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
          <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="btn btn--ghost">Cancelar</a>
        </div>
      </form>
    </div>
  </section>

  <aside class="editor-side">
    <section class="surface-block info-panel">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Boas práticas rápidas</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <div class="info-step">
          <strong>Janela objetiva</strong>
          <p>Defina abertura e fechamento realistas para evitar bloqueios desnecessários.</p>
        </div>
        <div class="info-step">
          <strong>Título específico</strong>
          <p>Nomes claros ajudam o aluno a reconhecer conteúdo e prioridade imediatamente.</p>
        </div>
        <div class="info-step">
          <strong>Questões depois</strong>
          <p>Após salvar, complete a atividade adicionando enunciados e gabaritos esperados.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
