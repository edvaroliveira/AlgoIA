<?php
$exercise = $exercise ?? [];
$questions = $questions ?? [];
$nextOrder = $nextOrder ?? (count($questions) + 1);
$pageTitle = 'Questões — ' . ($exercise['title'] ?? 'Exercício');
global $session;
?>

<div class="page-header">
  <div>
    <h1>Questões do exercício</h1>
    <p class="subtitle">Monte a estrutura avaliativa e mantenha o gabarito esperado organizado para a correção da IA.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">← Voltar ao exercício</a>
</div>

<p class="subtitle">Exercício: <strong><?= \Core\View::e($exercise['title']) ?></strong></p>

<?php if (($exercise['status'] ?? 'active') === 'draft'): ?>
  <div class="alert alert--warning">
    Este exercício ainda está pendente de finalização. Depois de concluir as questões, volte ao detalhe do exercício para ativar em uma ou mais turmas.
  </div>
<?php endif; ?>

<!-- Lista atual -->
<?php if (!empty($questions)): ?>
  <div class="section">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Questões adicionadas (<?= count($questions) ?>)</h2>
          <p class="surface-copy">Revise rapidamente a estrutura já cadastrada antes de incluir novas entradas.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <?php foreach ($questions as $i => $q): ?>
          <div class="question-card">
            <div class="question-header">
              <span class="question-num">Q<?= $i + 1 ?></span>
              <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
            </div>
            <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
            <form method="POST" action="<?= \Core\app_url('/teacher/questions/' . $q['id'] . '/delete') ?>"
              class="inline-form"
              onsubmit="return confirm('Excluir esta questão?');">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <button class="btn btn--danger btn--sm">Excluir</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
<?php endif; ?>

<!-- Adicionar nova -->
<div class="editor-layout">
  <section class="surface-block editor-main">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Adicionar questão</h2>
        <p class="surface-copy">Defina enunciado, gabarito esperado e peso da questão com precisão suficiente para a correção automatizada.</p>
      </div>
    </div>

    <div class="surface-block__body">
      <?php if (!empty($errors)): ?>
        <div class="alert alert--error">
          <?php foreach ($errors as $e): ?><div><?= \Core\View::e($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php
      $flash = $session->getFlash('success');
      if ($flash): ?>
        <div class="alert alert--success"><?= \Core\View::e($flash) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions') ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <input type="hidden" name="order_index" value="<?= $nextOrder ?>">

        <div class="form-group">
          <label class="form-label" for="text">Enunciado da questão</label>
          <textarea class="form-input form-textarea" id="text" name="text"
            rows="5" required
            placeholder="Descreva a questão de forma clara, objetiva e alinhada ao que será avaliado."><?= \Core\View::e($old['text'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="expected_answer_hint">
            Conceitos esperados / Gabarito
            <span class="hint">
              (usado pela IA para corrigir — não será exibido ao aluno durante o exercício)
            </span>
          </label>
          <textarea class="form-input form-textarea" id="expected_answer_hint" name="expected_answer_hint"
            rows="5" required
            placeholder="Explique a solução esperada, os passos corretos ou os conceitos obrigatórios."><?= \Core\View::e($old['hint'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="max_score">Pontuação máxima (0–10)</label>
          <input class="form-input form-input--short" type="number" id="max_score" name="max_score"
            value="<?= \Core\View::e($old['maxScore'] ?? 10) ?>"
            min="0" max="10" step="0.5" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Adicionar questão</button>
          <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id']) ?>" class="btn btn--ghost">Voltar e finalizar depois</a>
        </div>
      </form>
    </div>
  </section>

  <aside class="editor-side">
    <section class="surface-block info-panel">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Critérios práticos</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <div class="info-step">
          <strong>Enunciado sem ambiguidade</strong>
          <p>Quanto mais claro o pedido, menor a chance de resposta boa parecer errada.</p>
        </div>
        <div class="info-step">
          <strong>Gabarito orientado</strong>
          <p>Liste conceitos esperados, não apenas uma frase curta, para melhorar a avaliação da IA.</p>
        </div>
        <div class="info-step">
          <strong>Eficiência só quando pedir</strong>
          <p>Se quiser cobrar melhor complexidade, otimização ou uma abordagem específica, diga isso claramente no enunciado e no gabarito. Se isso não for explicitado, a IA tende a aceitar caminhos alternativos corretos sem penalizar apenas pela eficiência.</p>
        </div>
        <div class="info-step">
          <strong>Peso coerente</strong>
          <p>Distribua pontos conforme complexidade real da questão dentro do exercício.</p>
        </div>
        <div class="info-step">
          <strong>Explique o critério de nota</strong>
          <p>Quando houver diferença entre solução correta, solução parcial e solução otimizada, descreva isso no gabarito para orientar melhor o crédito parcial e o feedback.</p>
        </div>
      </div>
    </section>
  </aside>
</div>
