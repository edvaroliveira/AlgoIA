<?php
$pageTitle = 'Novo Exercício';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Criar exercício</h1>
    <p class="subtitle">Crie o rascunho, cadastre todas as questões e só depois ative o exercício para uma ou mais turmas.</p>
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
        <p class="surface-copy">Nesta etapa o exercício nasce como pendente de finalização. A ativação para turmas acontece depois das questões.</p>
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

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Salvar rascunho</button>
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
          <strong>Rascunho primeiro</strong>
          <p>O exercício ficará pendente de finalização até você concluir as questões e ativar para as turmas desejadas.</p>
        </div>
        <div class="info-step">
          <strong>Publicação depois</strong>
          <p>Datas de abertura, fechamento e quantidade de tentativas serão definidas por turma, só na etapa de publicação.</p>
        </div>
        <div class="info-step">
          <strong>Título específico</strong>
          <p>Nomes claros ajudam o aluno a reconhecer conteúdo e prioridade imediatamente.</p>
        </div>
        <div class="info-step">
          <strong>Ativação no final</strong>
          <p>Depois de cadastrar as questões, escolha uma ou mais turmas para publicar a atividade.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
