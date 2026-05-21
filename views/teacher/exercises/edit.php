<?php
$exercise = $exercise ?? [];
$pageTitle = 'Editar Exercício';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Editar exercício</h1>
    <p class="subtitle">Ajuste apenas os metadados pedagógicos antes da conclusão. A publicação por turma acontece depois.</p>
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
        <p class="surface-copy">Atualize a configuração geral sem alterar aqui o conjunto de questões ou as turmas ativadas.</p>
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
          <strong>Turmas separadas</strong>
          <p>A publicação para uma ou mais turmas fica disponível só depois da conclusão do exercício.</p>
        </div>
        <div class="info-step">
          <strong>Agenda por turma</strong>
          <p>Abertura, fechamento e tentativas não são mais configurados aqui. Cada turma recebe sua própria janela na publicação.</p>
        </div>
        <div class="info-step">
          <strong>Questões separadas</strong>
          <p>Para mexer no conteúdo das questões, volte à tela do exercício e use a área específica.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
