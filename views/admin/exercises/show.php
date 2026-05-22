<?php
$exercise = $exercise ?? [];
$questions = $questions ?? [];
$results = $results ?? [];
$maxScore = $maxScore ?? 0;
$pageTitle = 'Exercício — Administração';
$isDraft = ($exercise['status'] ?? '') === 'draft';
$isReady = ($exercise['status'] ?? '') === 'ready';
$isOpen = ($exercise['status'] ?? '') === 'active'
  && !empty($exercise['opens_at'])
  && !empty($exercise['closes_at'])
  && strtotime((string) $exercise['opens_at']) <= time()
  && strtotime((string) $exercise['closes_at']) >= time();
$isClosed = ($exercise['status'] ?? '') === 'active'
  && !empty($exercise['closes_at'])
  && strtotime((string) $exercise['closes_at']) < time();
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($exercise['title'] ?? 'Exercício') ?></h1>
    <p class="subtitle">Visão administrativa do exercício com autoria, publicações, questões e desempenho dos alunos.</p>
  </div>
  <div class="td-actions">
    <a href="<?= \Core\app_url('/admin/exercises') ?>" class="btn btn--ghost">Voltar</a>
    <?php if (($exercise['status'] ?? '') === 'active' && !empty($exercise['publication_settings'])): ?>
      <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/close') ?>" onsubmit="return confirm('Encerrar administrativamente as publicações deste exercício?');">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <button type="submit" class="btn btn--danger">Encerrar publicações</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Docente</span>
    <strong class="overview-card__value"><?= \Core\View::e($exercise['teacher_name'] ?? '—') ?></strong>
    <p class="overview-card__copy"><?= \Core\View::e($exercise['teacher_email'] ?? '—') ?></p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Questões</span>
    <strong class="overview-card__value"><?= count($questions) ?></strong>
    <p class="overview-card__copy">Estrutura atual da atividade.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pontuação máxima</span>
    <strong class="overview-card__value"><?= number_format((float) $maxScore, 1) ?></strong>
    <p class="overview-card__copy">Soma do peso das questões cadastradas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Submissões</span>
    <strong class="overview-card__value"><?= (int) ($exercise['attempt_count'] ?? 0) ?></strong>
    <p class="overview-card__copy">Tentativas registradas para esta atividade.</p>
  </article>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Configuração geral</h2>
      <p class="surface-copy">Resumo operacional da atividade no contexto administrativo.</p>
    </div>
  </div>
  <div class="surface-block__body surface-block__body--stack">
    <div class="meta-grid">
      <div><strong>Status</strong><?= $isDraft ? 'Rascunho' : ($isReady ? 'Pronto' : ($isClosed ? 'Encerrado' : ($isOpen ? 'Aberto' : 'Agendado'))) ?></div>
      <div><strong>Turmas</strong><?= \Core\View::e($exercise['turma_label'] ?? 'Pendente de finalização') ?></div>
      <div><strong>Chaves</strong><?= \Core\View::e($exercise['turma_keys'] ?? '—') ?></div>
      <div><strong>Abre</strong><?= !empty($exercise['opens_at']) ? date('d/m/Y H:i', strtotime((string) $exercise['opens_at'])) : '—' ?></div>
      <div><strong>Fecha</strong><?= !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime((string) $exercise['closes_at'])) : '—' ?></div>
      <div><strong>Tentativas</strong><?= ((string) ($exercise['max_attempts'] ?? '')) === '0' ? 'Ilimitadas' : (($exercise['max_attempts'] ?? null) === null ? '—' : (string) $exercise['max_attempts']) ?></div>
    </div>
    <?php if (!empty($exercise['description'])): ?>
      <div class="content-note">
        <strong>Descrição</strong>
        <p><?= nl2br(\Core\View::e($exercise['description'])) ?></p>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="cards-grid">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Publicações por turma</h2>
        <p class="surface-copy">Janelas de disponibilidade e limite operacional por turma.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($exercise['publication_settings'])): ?>
        <p class="empty-state">Este exercício ainda não possui publicação por turma.</p>
      <?php else: ?>
        <?php foreach ($exercise['publication_settings'] as $publication): ?>
          <div class="content-note">
            <strong><?= \Core\View::e($publication['turma_name']) ?> <span class="hint">(<?= \Core\View::e($publication['access_key']) ?>)</span></strong>
            <p>
              Abre em <?= date('d/m/Y H:i', strtotime((string) $publication['opens_at'])) ?> ·
              Fecha em <?= date('d/m/Y H:i', strtotime((string) $publication['closes_at'])) ?> ·
              Tentativas: <?= ((string) $publication['max_attempts']) === '0' ? 'Ilimitadas' : $publication['max_attempts'] ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Questões</h2>
        <p class="surface-copy">Estrutura pedagógica registrada no exercício.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($questions)): ?>
        <p class="empty-state">Nenhuma questão cadastrada.</p>
      <?php else: ?>
        <?php foreach ($questions as $index => $question): ?>
          <div class="content-note">
            <strong>Q<?= $index + 1 ?> · <?= number_format((float) $question['max_score'], 1) ?> pts</strong>
            <p><?= nl2br(\Core\View::e($question['text'])) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Resultados</h2>
      <p class="surface-copy">Melhores notas e volume de tentativas já corrigidas.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($results)): ?>
      <p class="empty-state">Nenhuma submissão corrigida até o momento.</p>
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
          <?php foreach ($results as $result): ?>
            <tr>
              <td><?= \Core\View::e($result['name']) ?></td>
              <td><?= \Core\View::e($result['email']) ?></td>
              <td><?= number_format((float) $result['best_score'], 1) ?> / <?= number_format((float) $maxScore, 1) ?></td>
              <td><?= (int) $result['attempt_count'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
