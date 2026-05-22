<?php
$exercise = $exercise ?? [];
$questions = $questions ?? [];
$results = $results ?? [];
$maxScore = $maxScore ?? 0;
$returnPath = $returnPath ?? '/admin/exercises';
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
$defaultReopenUntil = date('Y-m-d\TH:i', strtotime('+7 days'));
$defaultPublicationMin = date('Y-m-d\TH:i', strtotime('+1 hour'));
$currentPath = '/admin/exercises/' . (int) ($exercise['id'] ?? 0) . '?return_to=' . urlencode($returnPath);
$exerciseReviewStatus = (string) ($exercise['admin_review_status'] ?? 'approved');
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($exercise['title'] ?? 'Exercício') ?></h1>
    <p class="subtitle">Visão administrativa do exercício com autoria, publicações, questões e desempenho dos alunos.</p>
  </div>
  <div class="td-actions">
    <a href="<?= \Core\app_url($returnPath) ?>" class="btn btn--ghost">Voltar</a>
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
    <div class="content-note">
      <strong>Moderação administrativa</strong>
      <div class="badge-strip">
        <?php if ($exerciseReviewStatus === 'blocked'): ?>
          <span class="badge badge--error">bloqueado</span>
        <?php elseif ($exerciseReviewStatus === 'flagged'): ?>
          <span class="badge badge--warning">sinalizado</span>
        <?php else: ?>
          <span class="badge badge--success">aprovado</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($exercise['admin_review_note'])): ?>
        <p><?= nl2br(\Core\View::e((string) $exercise['admin_review_note'])) ?></p>
      <?php endif; ?>
      <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/moderate') ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="exercise-admin-review-status">Status de revisão</label>
            <select id="exercise-admin-review-status" name="admin_review_status" class="form-input">
              <option value="approved" <?= $exerciseReviewStatus === 'approved' ? 'selected' : '' ?>>Aprovado</option>
              <option value="flagged" <?= $exerciseReviewStatus === 'flagged' ? 'selected' : '' ?>>Sinalizado</option>
              <option value="blocked" <?= $exerciseReviewStatus === 'blocked' ? 'selected' : '' ?>>Bloqueado</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="exercise-admin-review-note">Observação administrativa</label>
            <textarea id="exercise-admin-review-note" name="admin_review_note" class="form-input form-textarea" rows="3" placeholder="Contexto interno da moderação"><?= \Core\View::e((string) ($exercise['admin_review_note'] ?? '')) ?></textarea>
          </div>
        </div>
        <div class="td-actions">
          <button type="submit" class="btn btn--ghost">Salvar moderação</button>
        </div>
      </form>
    </div>
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

    <?php if (($exercise['status'] ?? '') === 'active' && !empty($exercise['publication_settings'])): ?>
      <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/reopen') ?>" class="form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="reopen-until">Reabrir até</label>
            <input
              id="reopen-until"
              type="datetime-local"
              name="reopen_until"
              class="form-input"
              value="<?= date('Y-m-d\TH:i', strtotime('+7 days')) ?>"
              min="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
          </div>
          <div class="form-group" style="justify-content: flex-end;">
            <label class="form-label">Ação administrativa</label>
            <button type="submit" class="btn btn--primary">Reabrir publicações</button>
          </div>
        </div>
      </form>
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
        <div class="content-note">
          <strong>Ações em lote</strong>
          <p>Marque as turmas desejadas e aplique a ação em lote sem usar seleção múltipla do navegador.</p>
          <form method="POST" class="form">
            <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
            <table class="table">
              <thead>
                <tr>
                  <th>
                    <label>
                      <input type="checkbox" data-select-all="exercise-publications" aria-label="Selecionar todas as turmas desta publicação">
                      Todas
                    </label>
                  </th>
                  <th>Turma</th>
                  <th>Chave</th>
                  <th>Fecha em</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exercise['publication_settings'] as $publication): ?>
                  <?php
                  $publicationClosesTimestamp = !empty($publication['closes_at']) ? strtotime((string) $publication['closes_at']) : false;
                  $publicationOpensTimestamp = !empty($publication['opens_at']) ? strtotime((string) $publication['opens_at']) : false;
                  $publicationBatchState = 'scheduled';
                  if ($publicationClosesTimestamp !== false && $publicationClosesTimestamp < time()) {
                    $publicationBatchState = 'closed';
                  } elseif ($publicationOpensTimestamp !== false && $publicationClosesTimestamp !== false && $publicationOpensTimestamp <= time() && $publicationClosesTimestamp >= time()) {
                    $publicationBatchState = 'open';
                  }
                  $publicationBatchLabel = $publicationBatchState === 'open' ? 'abertas' : ($publicationBatchState === 'closed' ? 'encerradas' : 'agendadas');
                  ?>
                  <tr>
                    <td><input type="checkbox" name="turma_ids[]" value="<?= (int) ($publication['turma_id'] ?? 0) ?>" data-select-item="exercise-publications" data-item-state="<?= $publicationBatchState ?>" data-item-state-label="<?= $publicationBatchLabel ?>"></td>
                    <td><?= \Core\View::e($publication['turma_name'] ?? 'Turma') ?></td>
                    <td><?= \Core\View::e($publication['access_key'] ?? '—') ?></td>
                    <td><?= !empty($publication['closes_at']) ? date('d/m/Y H:i', strtotime((string) $publication['closes_at'])) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="batch-reopen-until">Reabrir até</label>
                <input id="batch-reopen-until" type="datetime-local" name="reopen_until" class="form-input" value="<?= $defaultReopenUntil ?>" min="<?= $defaultPublicationMin ?>">
              </div>
              <div class="form-group" style="justify-content: flex-end;">
                <label class="form-label">Ações em lote</label>
                <div class="td-actions">
                  <span class="selection-summary" data-selection-count="exercise-publications">0 selecionadas</span>
                  <span class="selection-summary" data-selection-breakdown="exercise-publications"></span>
                  <span class="selection-summary" data-selection-compatibility="exercise-publications"></span>
                  <button type="submit" formaction="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/publications/batch-close') ?>" class="btn btn--danger" data-requires-selection="exercise-publications" data-allowed-states="open,scheduled" onclick="return confirm('Encerrar todas as publicações selecionadas?');" disabled>Encerrar selecionadas</button>
                  <button type="submit" formaction="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/publications/batch-reopen') ?>" class="btn btn--primary" data-requires-selection="exercise-publications" data-allowed-states="closed" disabled>Reabrir selecionadas</button>
                </div>
              </div>
            </div>
          </form>
        </div>
        <?php foreach ($exercise['publication_settings'] as $publication): ?>
          <?php
          $publicationClosesAt = !empty($publication['closes_at']) ? strtotime((string) $publication['closes_at']) : false;
          $publicationOpensAt = !empty($publication['opens_at']) ? strtotime((string) $publication['opens_at']) : false;
          $publicationIsClosed = $publicationClosesAt !== false && $publicationClosesAt < time();
          $publicationIsOpen = $publicationOpensAt !== false
            && $publicationClosesAt !== false
            && $publicationOpensAt <= time()
            && $publicationClosesAt >= time();
          ?>
          <div class="content-note">
            <strong><a href="<?= \Core\app_url('/admin/turmas/' . ($publication['turma_id'] ?? 0) . '?return_to=' . urlencode($currentPath)) ?>"><?= \Core\View::e($publication['turma_name']) ?></a> <span class="hint">(<?= \Core\View::e($publication['access_key']) ?>)</span></strong>
            <p>
              Abre em <?= date('d/m/Y H:i', strtotime((string) $publication['opens_at'])) ?> ·
              Fecha em <?= date('d/m/Y H:i', strtotime((string) $publication['closes_at'])) ?> ·
              Tentativas: <?= ((string) $publication['max_attempts']) === '0' ? 'Ilimitadas' : $publication['max_attempts'] ?>
            </p>
            <p class="hint">
              Situação: <?= $publicationIsClosed ? 'Encerrada' : ($publicationIsOpen ? 'Aberta' : 'Agendada') ?>
            </p>
            <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/publications/' . ($publication['turma_id'] ?? 0)) ?>" class="form">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="publication-opens-<?= (int) ($publication['turma_id'] ?? 0) ?>">Abre em</label>
                  <input
                    id="publication-opens-<?= (int) ($publication['turma_id'] ?? 0) ?>"
                    type="datetime-local"
                    name="opens_at"
                    class="form-input"
                    value="<?= !empty($publication['opens_at']) ? date('Y-m-d\TH:i', strtotime((string) $publication['opens_at'])) : '' ?>">
                </div>
                <div class="form-group">
                  <label class="form-label" for="publication-closes-<?= (int) ($publication['turma_id'] ?? 0) ?>">Fecha em</label>
                  <input
                    id="publication-closes-<?= (int) ($publication['turma_id'] ?? 0) ?>"
                    type="datetime-local"
                    name="closes_at"
                    class="form-input"
                    value="<?= !empty($publication['closes_at']) ? date('Y-m-d\TH:i', strtotime((string) $publication['closes_at'])) : '' ?>">
                </div>
                <div class="form-group">
                  <label class="form-label" for="publication-attempts-<?= (int) ($publication['turma_id'] ?? 0) ?>">Tentativas</label>
                  <input
                    id="publication-attempts-<?= (int) ($publication['turma_id'] ?? 0) ?>"
                    type="number"
                    min="0"
                    name="max_attempts"
                    class="form-input"
                    value="<?= (int) ($publication['max_attempts'] ?? 1) ?>">
                </div>
                <div class="form-group" style="justify-content: flex-end;">
                  <label class="form-label">Janela da publicação</label>
                  <button type="submit" class="btn btn--sm">Salvar janela</button>
                </div>
              </div>
            </form>
            <div class="td-actions">
              <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/publications/' . ($publication['turma_id'] ?? 0) . '/close') ?>" onsubmit="return confirm('Encerrar administrativamente apenas esta publicação?');">
                <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                <button type="submit" class="btn btn--sm btn--ghost">Encerrar esta publicação</button>
              </form>
            </div>
            <form method="POST" action="<?= \Core\app_url('/admin/exercises/' . ($exercise['id'] ?? 0) . '/publications/' . ($publication['turma_id'] ?? 0) . '/reopen') ?>" class="form">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="publication-reopen-<?= (int) ($publication['turma_id'] ?? 0) ?>">Reabrir só esta turma até</label>
                  <input
                    id="publication-reopen-<?= (int) ($publication['turma_id'] ?? 0) ?>"
                    type="datetime-local"
                    name="reopen_until"
                    class="form-input"
                    value="<?= $defaultReopenUntil ?>"
                    min="<?= $defaultPublicationMin ?>">
                </div>
                <div class="form-group" style="justify-content: flex-end;">
                  <label class="form-label">Ação por turma</label>
                  <button type="submit" class="btn btn--sm btn--primary">Reabrir esta publicação</button>
                </div>
              </div>
            </form>
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
            <div class="badge-strip">
              <?php if (($question['admin_review_status'] ?? 'approved') === 'blocked'): ?>
                <span class="badge badge--error">bloqueada</span>
              <?php elseif (($question['admin_review_status'] ?? 'approved') === 'flagged'): ?>
                <span class="badge badge--warning">sinalizada</span>
              <?php else: ?>
                <span class="badge badge--success">aprovada</span>
              <?php endif; ?>
            </div>
            <p><?= nl2br(\Core\View::e($question['text'])) ?></p>
            <?php if (!empty($question['admin_review_note'])): ?>
              <p class="hint">Observação: <?= nl2br(\Core\View::e((string) $question['admin_review_note'])) ?></p>
            <?php endif; ?>
            <form method="POST" action="<?= \Core\app_url('/admin/questions/' . ($question['id'] ?? 0) . '/moderate') ?>" class="form">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="question-review-status-<?= (int) ($question['id'] ?? 0) ?>">Status da questão</label>
                  <select id="question-review-status-<?= (int) ($question['id'] ?? 0) ?>" name="admin_review_status" class="form-input">
                    <option value="approved" <?= ($question['admin_review_status'] ?? 'approved') === 'approved' ? 'selected' : '' ?>>Aprovada</option>
                    <option value="flagged" <?= ($question['admin_review_status'] ?? 'approved') === 'flagged' ? 'selected' : '' ?>>Sinalizada</option>
                    <option value="blocked" <?= ($question['admin_review_status'] ?? 'approved') === 'blocked' ? 'selected' : '' ?>>Bloqueada</option>
                  </select>
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="question-review-note-<?= (int) ($question['id'] ?? 0) ?>">Observação administrativa</label>
                  <textarea id="question-review-note-<?= (int) ($question['id'] ?? 0) ?>" name="admin_review_note" class="form-input form-textarea" rows="2" placeholder="Contexto interno da moderação"><?= \Core\View::e((string) ($question['admin_review_note'] ?? '')) ?></textarea>
                </div>
              </div>
              <div class="td-actions">
                <button type="submit" class="btn btn--sm btn--ghost">Salvar revisão da questão</button>
              </div>
            </form>
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
