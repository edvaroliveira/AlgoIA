<?php
$exercise = $exercise ?? [];
$questions = $questions ?? [];
$results = $results ?? [];
$maxScore = $maxScore ?? 0;
$turmas = $turmas ?? [];
$activationErrors = array_values(array_filter(
  $activationErrors ?? [],
  static fn($error): bool => is_string($error) && trim($error) !== ''
));
$activationTurmaIds = $activationTurmaIds ?? ($exercise['assigned_turma_ids'] ?? []);
$pageTitle = $exercise['title'] ?? 'Exercício';
$isDraft = ($exercise['status'] ?? 'active') === 'draft';
global $session;
$flashError = $session->getFlash('error');
$isOpen = !empty($exercise) && strtotime((string) $exercise['opens_at']) <= time() && strtotime((string) $exercise['closes_at']) >= time();
$isClosed = !empty($exercise) && strtotime((string) $exercise['closes_at']) < time();
?>

<?php if ($flashError): ?>
  <div class="alert alert--error"><?= \Core\View::e($flashError) ?></div>
<?php endif; ?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($exercise['title'] ?? 'Exercício') ?></h1>
    <p class="subtitle"><?= $isDraft ? 'Rascunho pendente de finalização. Complete as questões e ative para as turmas desejadas.' : 'Gestão completa da atividade, com visão de janela, estrutura de correção e desempenho dos alunos.' ?></p>
  </div>
  <div class="header-actions">
    <?php if ($isDraft): ?>
      <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/edit') ?>" class="btn btn--ghost">Editar</a>
      <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary">Gerir questões</a>
    <?php endif; ?>
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
    <?php if ($isDraft): ?>
      <span class="hero-chip">Pendente de finalização</span>
    <?php elseif ($isClosed): ?>
      <span class="hero-chip">Encerrado</span>
    <?php elseif ($isOpen): ?>
      <span class="hero-chip">Aberto agora</span>
    <?php else: ?>
      <span class="hero-chip">Agendado</span>
    <?php endif; ?>
    <span class="hero-chip hero-chip--soft">Turmas: <?= \Core\View::e($exercise['turma_label'] ?? 'Pendente de finalização') ?></span>
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
    <span class="overview-card__label">Turmas vinculadas</span>
    <strong class="overview-card__value"><?= (int) ($exercise['turma_count'] ?? count($activationTurmaIds)) ?></strong>
    <p class="overview-card__copy"><?= $isDraft ? 'Selecione uma ou mais turmas ao finalizar o exercício.' : 'Distribuição atual da atividade entre as turmas já ativadas.' ?></p>
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
        <div><strong>Turmas</strong><?= \Core\View::e($exercise['turma_label'] ?? 'Pendente de finalização') ?></div>
        <div><strong>Chaves das turmas</strong><?= \Core\View::e($exercise['turma_keys'] ?? '—') ?></div>
        <div><strong>Abre</strong><?= !empty($exercise['opens_at']) ? date('d/m/Y H:i', strtotime($exercise['opens_at'])) : '—' ?></div>
        <div><strong>Fecha</strong><?= !empty($exercise['closes_at']) ? date('d/m/Y H:i', strtotime($exercise['closes_at'])) : '—' ?></div>
        <div><strong>Tentativas</strong><?= ($exercise['max_attempts'] ?? '0') === '0' ? 'Ilimitadas' : $exercise['max_attempts'] ?></div>
        <div><strong>Status</strong><?= $isDraft ? 'Pendente de finalização' : ($isClosed ? 'Encerrado' : ($isOpen ? 'Aberto' : 'Agendado')) ?></div>
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
    <section class="surface-block info-panel info-panel--static exercise-quick-actions">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Ações rápidas</h2>
        </div>
      </div>
      <div class="surface-block__body surface-block__body--stack">
        <?php if ($isDraft): ?>
          <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/edit') ?>" class="btn btn--ghost btn--full">Editar configuração</a>
          <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary btn--full">Adicionar ou revisar questões</a>
        <?php else: ?>
          <div class="content-note">
            <strong>Edição bloqueada</strong>
            <p>Depois da vinculação com turma, este exercício fica congelado para preservar o enunciado, as regras e as questões publicadas.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($isDraft): ?>
      <section class="surface-block info-panel info-panel--static">
        <div class="surface-block__header">
          <div>
            <h2 class="surface-title">Finalizar ativação</h2>
            <p class="surface-copy">O exercício só fica disponível aos alunos depois desta etapa.</p>
          </div>
        </div>
        <div class="surface-block__body surface-block__body--stack">
          <?php if (!empty($activationErrors)): ?>
            <div class="alert alert--error activation-feedback" data-activation-feedback>
              <?php foreach ($activationErrors as $error): ?><div><?= \Core\View::e($error) ?></div><?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="alert alert--error activation-feedback" data-activation-feedback hidden></div>
          <?php endif; ?>

          <?php if (empty($questions)): ?>
            <div class="content-note">
              <strong>Ativação bloqueada</strong>
              <p>Cadastre pelo menos uma questão antes de escolher as turmas e publicar esta atividade.</p>
            </div>
          <?php endif; ?>

          <form method="POST"
            action="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/activate') ?>"
            class="form"
            data-activation-form
            data-question-count="<?= count($questions) ?>">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">

            <div class="checkbox-grid">
              <?php foreach ($turmas as $turma): ?>
                <label class="choice-card">
                  <input type="checkbox" name="turma_ids[]" value="<?= $turma['id'] ?>" <?= in_array((int) $turma['id'], array_map('intval', $activationTurmaIds), true) ? 'checked' : '' ?>>
                  <span>
                    <strong><?= \Core\View::e($turma['name']) ?></strong>
                    <small><?= \Core\View::e($turma['access_key']) ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn--primary btn--full" <?= empty($questions) ? 'disabled' : '' ?>>
              Finalizar e ativar exercício
            </button>
          </form>
        </div>
      </section>
    <?php else: ?>
      <section class="surface-block info-panel info-panel--static">
        <div class="surface-block__header">
          <div>
            <h2 class="surface-title">Turmas vinculadas</h2>
            <p class="surface-copy">A publicação já foi concluída e o exercício está congelado para evitar alterações após a disponibilização.</p>
          </div>
        </div>
        <div class="surface-block__body surface-block__body--stack">
          <div class="content-note">
            <strong>Publicação concluída</strong>
            <p><?= \Core\View::e($exercise['turma_label'] ?? '—') ?></p>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </aside>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Questões cadastradas (<?= count($questions) ?>)</h2>
        <p class="surface-copy">Estrutura usada na correção assistida por IA e no cálculo da nota final.</p>
      </div>
      <?php if ($isDraft): ?>
        <a href="<?= \Core\app_url('/teacher/exercises/' . $exercise['id'] . '/questions/create') ?>" class="btn btn--primary btn--sm">+ Questão</a>
      <?php endif; ?>
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
            <?php if ($isDraft): ?>
              <form method="POST" action="<?= \Core\app_url('/teacher/questions/' . $q['id'] . '/delete') ?>"
                class="inline-form"
                onsubmit="return confirm('Excluir esta questão?');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button class="btn btn--danger btn--sm">Excluir questão</button>
              </form>
            <?php endif; ?>
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
        <p class="surface-copy"><?= $isDraft ? 'Resultados aparecerão aqui quando o exercício for finalizado, ativado e começar a receber submissões.' : 'Melhor nota registrada e número de tentativas por participante que já teve correção concluída.' ?></p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($results)): ?>
        <p class="empty-state"><?= $isDraft ? 'Exercício ainda não ativado para turmas.' : 'Nenhuma submissão ainda.' ?></p>
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
