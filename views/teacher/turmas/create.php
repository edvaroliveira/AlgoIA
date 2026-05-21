<?php
$pageTitle = 'Nova Turma';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Criar nova turma</h1>
    <p class="subtitle">Abra um novo grupo com nome claro e uma chave de acesso que será gerada automaticamente.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (isset($error)): ?>
  <div class="alert alert--error"><?= \Core\View::e($error) ?></div>
<?php endif; ?>

<div class="editor-layout">
  <section class="surface-block editor-main">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Dados essenciais</h2>
        <p class="surface-copy">Defina o nome que será exibido no ambiente e usado como referência para os alunos.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/teacher/turmas') ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

        <div class="form-group">
          <label class="form-label" for="name">Nome da turma</label>
          <input class="form-input" type="text" id="name" name="name"
            value="<?= \Core\View::e($old['name'] ?? '') ?>"
            required autofocus placeholder="Ex: Algoritmos 2026.1 - Turma A">
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Criar turma</button>
          <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">Cancelar</a>
        </div>
      </form>
    </div>
  </section>

  <aside class="editor-side">
    <section class="surface-block info-panel">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">O que acontece depois</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <div class="info-step">
          <strong>1. Chave gerada</strong>
          <p>O sistema cria uma chave curta para matrícula dos alunos.</p>
        </div>
        <div class="info-step">
          <strong>2. Aprovação manual</strong>
          <p>Ingressos podem ficar pendentes até a sua liberação na página da turma.</p>
        </div>
        <div class="info-step">
          <strong>3. Publicação de exercícios</strong>
          <p>Depois da turma criada, você já pode associar exercícios a ela.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
