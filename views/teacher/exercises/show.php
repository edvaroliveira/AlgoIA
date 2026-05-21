<?php
$exercise = $exercise ?? [];
$questions = $questions ?? [];
$results = $results ?? [];
$maxScore = $maxScore ?? 0;
$pageTitle = $exercise['title'] ?? 'Exercício';
$isOpen = !empty($exercise) && strtotime((string) $exercise['opens_at']) <= time() && strtotime((string) $exercise['closes_at']) >= time();
$isClosed = !empty($exercise) && strtotime((string) $exercise['closes_at']) < time();
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($exercise['title'] ?? 'Exercício') ?></h1>
    <p class="subtitle">Gestão completa da atividade, com visão de janela, estrutura de correção e desempenho dos alunos.</p>
  </div>
  <div class="header-actions">
    <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/edit') ?>" class="btn btn--ghost">Editar</a>
    <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary">Gerir questões</a>
    <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="btn btn--ghost">← Exercícios</a>
  </div>
</div>

<section class="hero-panel hero-panel--teacher hero-panel--exercise">
  <div>
    <div class="hero-panel__eyebrow">Detalhe do exercício</div>
    <h2 class="hero-panel__title">Operação concentrada da atividade e leitura clara do seu ciclo.</h2>
    <p class="hero-panel__copy">Confira a turma associada, a janela de disponibilidade, o peso total da avaliação e o comportamento das submissões em um painel único.</p>
  </div>
  <div class="hero-panel__meta">
    <?php if ($isClosed): ?>
      <span class="hero-chip">Encerrado</span>
    <?php elseif ($isOpen): ?>
      <span class="hero-chip">Aberto agora</span>
    <?php else: ?>
      <span class="hero-chip">Agendado</span>
    <?php endif; ?>
    <span class="hero-chip hero-chip--soft">Turma: <?= \Core\View::e($exercise['turma_name'] ?? '') ?></span>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Questões</span>
    <strong class="overview-card__value"><?= count($questions) ?></strong>
    <p class="overview-card__copy">Itens atualmente usados para correção e composição da nota.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pontuação máxima</span>
    <strong class="overview-card__value"><?= number_format((float) $maxScore, 1) ?></strong>
    <p class="overview-card__copy">Soma total do peso das questões configuradas neste exercício.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Submissões avaliadas</span>
    <strong class="overview-card__value"><?= count($results) ?></strong>
    <p class="overview-card__copy">Alunos com pelo menos uma tentativa já corrigida.</p>
  </article>
</div>

<div class="exercise-show-grid">
  <section class="surface-block exercise-main-panel">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Configuração e contexto</h2>
        <p class="surface-copy">Resumo operacional da atividade antes da leitura das questões e dos resultados.</p>
      </div>
    </div>
    <div class="surface-block__body surface-block__body--stack">
      <div class="meta-grid">
        <div><strong>Turma</strong><?= \Core\View::e($exercise['turma_name'] ?? '') ?></div>
        <div><strong>Chave da turma</strong><?= \Core\View::e($exercise['turma_key'] ?? '—') ?></div>
        <div><strong>Abre</strong><?= !empty($exercise['opens_at']) ? date('d/m/Y H:i', strtotime($exercise['opens_at'])) : '—' ?></div>
        <div><strong>Fecha</strong><?= !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime($exercise['closes_at'])) : '—' ?></div>
        <div><strong>Tentativas</strong><?= ($exercise['max_attempts'] ?? '0') === '0' ? 'Ilimitadas' : $exercise['max_attempts'] ?></div>
        <div><strong>Status</strong><?= $isClosed ? 'Encerrado' : ($isOpen ? 'Aberto' : 'Agendado') ?></div>
      </div>
      <?php if (!empty($exercise['description'])): ?>
        <div class="content-note">
          <strong>Descrição</strong>
          <p><?= nl2br(\Core\View::e($exercise['description'])) ?></p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <aside class="exercise-side-panel">
    <section class="surface-block info-panel info-panel--static">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Ações rápidas</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/edit') ?>" class="btn btn--ghost btn--full">Editar configuração</a>
        <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary btn--full">Adicionar ou revisar questões</a>
      </div>
    </section>
  </aside>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Questões cadastradas (<?= count($questions) ?>)</h2>
        <p class="surface-copy">Estrutura usada na correção assistida por IA e no cálculo da nota final.</p>
      </div>
      <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary btn--sm">+ Questão</a>
    </div>

    <div class="surface-block__body">
      <?php if (empty($questions)): ?>
        <p class="empty-state">Nenhuma questão adicionada. <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>">Adicionar</a>.</p>
      <?php else: ?>
        <?php foreach ($questions as $i => $q): ?>
          <div class="question-card question-card--teacher">
            <div class="question-header">
              <span class="question-num">Q<?= $i + 1 ?></span>
              <span class="question-score"><?= number_format((float) $q['max_score'], 1) ?> pts</span>
            </div>
            <p class="question-text"><?= nl2br(\Core\View::e($q['text'])) ?></p>
            <details class="hint-details">
              <summary>Ver gabarito esperado</summary>
              <p class="hint-text"><?= nl2br(\Core\View::e($q['expected_answer_hint'])) ?></p>
            </details>
            <form method="POST" action="<?= \Core\app_url('/teacher/questions/' . $q['id'] . '/delete') ?>"
              class="inline-form"
              onsubmit="return confirm('Excluir esta questão?');">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <button class="btn btn--danger btn--sm">Excluir questão</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Resultados dos alunos</h2>
        <p class="surface-copy">Melhor nota registrada e número de tentativas por participante que já teve correção concluída.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($results)): ?>
        <p class="empty-state">Nenhuma submissão ainda.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Aluno</th>
              <th>E-mail</th>
              <th>Melhor nota</th>
              <th>Tentativas</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><?= \Core\View::e($r['name']) ?></td>
                <td><?= \Core\View::e($r['email']) ?></td>
                <td><?= number_format((float) $r['best_score'], 1) ?> / <?= number_format((float) $maxScore, 1) ?></td>
                <td><?= $r['attempt_count'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="section">
  <section class="danger-zone">
    <div>
      <h2 class="danger-zone__title">Zona de perigo</h2>
      <p class="danger-zone__copy">A exclusão remove o exercício, as questões e os registros relacionados de tentativas e respostas.</p>
    </div>
    <form method="POST" action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/delete') ?>"
      onsubmit="return confirm('Excluir este exercício e todos os seus dados?');">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
      <button type="submit" class="btn btn--danger">Excluir exercício</button>
    </form>
  </section>
</div>
