<?php
$pageTitle = 'Novo Exercício';
global $session;
?>

<div class="page-header">
  <div>
    <h1>Criar exercício</h1>
    <p class="subtitle">Crie o rascunho, cadastre as questões, conclua a etapa pedagógica e só depois publique para as turmas desejadas.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="btn btn--ghost">← Voltar</a>
</div>

<?php if (!empty($errors)): ?>
  <div class="alert alert--error" role="alert">
    <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="cards-grid cards-grid--feature section" aria-label="Como começar">
  <article class="card card--feature">
    <div class="card-body card-body--stack">
      <strong>Começar do zero</strong>
      <p class="surface-copy">Defina título e descrição abaixo e cadastre as questões uma a uma.</p>
      <a href="#form-novo-exercicio" class="btn btn--primary">Preencher o formulário</a>
    </div>
  </article>
  <article class="card card--feature">
    <div class="card-body card-body--stack">
      <strong>Partir do banco</strong>
      <p class="surface-copy">Escolha um exercício do banco institucional ou um seu, marque as questões que quer aproveitar e acrescente novas depois.</p>
      <a href="<?= \Core\app_url('/teacher/bank') ?>" class="btn btn--ghost">Abrir o banco de exercícios</a>
    </div>
  </article>
</div>

<div class="editor-layout" id="form-novo-exercicio">
  <section class="surface-block editor-main">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Configuração inicial</h2>
        <p class="surface-copy">Nesta etapa o exercício nasce como rascunho. A conclusão e a publicação por turma acontecem depois das questões.</p>
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

        <?php \Core\View::partial('partials/classification_fields', [
          'tree' => $categoryTree ?? [],
          'selectedCategory' => isset($old['category_id']) ? (int) $old['category_id'] : null,
          'selectedThemes' => $old['theme_ids'] ?? [],
          'prefix' => 'create',
        ]); ?>
        <p class="hint">A classificação é obrigatória para concluir o exercício; pode ser feita agora ou depois.</p>

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
          <p>O exercício ficará em rascunho até você concluir as questões e encerrar a etapa pedagógica.</p>
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
          <strong>Conclusão e publicação</strong>
          <p>Depois de cadastrar as questões, conclua o exercício e publique a atividade para uma ou mais turmas.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
