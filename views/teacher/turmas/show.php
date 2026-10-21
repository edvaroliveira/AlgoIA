<?php
$turma = $turma ?? [];
$pending = $pending ?? [];
$students = $students ?? [];
$posts = $posts ?? [];
$ranking = $ranking ?? [];
$rankingMode = (string) ($turma['ranking_mode'] ?? 'off');
$pageTitle = 'Turma — ' . ($turma['name'] ?? '');
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($turma['name'] ?? 'Turma') ?></h1>
    <p class="subtitle">Gerencie chave de acesso, solicitações pendentes e alunos já ativos em um único fluxo.</p>
  </div>
  <div class="form-actions">
    <a href="<?= \Core\app_url('/teacher/turmas/' . (int) ($turma['id'] ?? 0) . '/desempenho') ?>" class="btn btn--primary">Desempenho</a>
    <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">← Turmas</a>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Solicitações pendentes</span>
    <strong class="overview-card__value"><?= count($pending) ?></strong>
    <p class="overview-card__copy">Entradas aguardando sua decisão para liberar acesso à turma.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos ativos</span>
    <strong class="overview-card__value"><?= count($students) ?></strong>
    <p class="overview-card__copy">Participantes aprovados atualmente vinculados à turma.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Chave operacional</span>
    <strong class="overview-card__value overview-card__value--mono"><?= \Core\View::e($turma['access_key'] ?? '------') ?></strong>
    <p class="overview-card__copy">Código usado por novos alunos para solicitar ingresso.</p>
  </article>
</div>

<section class="surface-block key-panel">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Chave de acesso da turma</h2>
      <p class="surface-copy">Compartilhe este código com novos alunos. Ao gerar outro, o anterior deixa de valer para novas matrículas.</p>
    </div>
  </div>
  <div class="surface-block__body surface-block__body--stack">
    <div class="big-key"><?= \Core\View::e($turma['access_key'] ?? '') ?></div>
    <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . $turma['id'] . '/key') ?>"
      data-confirm="Gerar nova chave? A chave atual deixará de funcionar para novos cadastros.">
      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
      <button type="submit" class="btn btn--ghost btn--sm">↻ Gerar nova chave</button>
    </form>
  </div>
</section>

<!-- Pendentes -->
<?php if (!empty($pending)): ?>
  <div class="section">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Aguardando aprovação <span class="badge badge--warning"><?= count($pending) ?></span></h2>
          <p class="surface-copy">Decida rapidamente quais solicitações entram na turma e quais devem ser rejeitadas.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <div class="table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Solicitado em</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pending as $s): ?>
                <tr>
                  <td><?= \Core\View::e($s['name']) ?></td>
                  <td><?= \Core\View::e($s['email']) ?></td>
                  <td><?= date('d/m/Y H:i', strtotime($s['joined_at'])) ?></td>
                  <td class="td-actions">
                    <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . $turma['id'] . '/approve/' . $s['id']) ?>" class="inline-form">
                      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                      <button class="btn btn--success btn--sm">Aprovar</button>
                    </form>
                    <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . $turma['id'] . '/reject/' . $s['id']) ?>"
                      class="inline-form"
                      data-confirm="Rejeitar este aluno?">
                      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                      <button class="btn btn--danger btn--sm">Rejeitar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
<?php endif; ?>

<!-- Ranking -->
<div class="section" id="ranking">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Ranking <span class="badge badge--neutral"><?= \App\Models\TurmaRanking::modeLabel($rankingMode) ?></span></h2>
        <p class="surface-copy">Opcional, só com a Prática. Cada aluno vê o top 5 e a própria posição; quem quiser aparece como anônimo para os colegas. Aqui você vê a lista completa.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . (int) ($turma['id'] ?? 0) . '/ranking') ?>" class="ranking-form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="form-group">
          <label class="form-label" for="ranking-mode">Período</label>
          <select class="form-input" id="ranking-mode" name="ranking_mode">
            <?php foreach (\App\Models\TurmaRanking::MODES as $mode): ?>
              <option value="<?= $mode ?>"<?= $mode === $rankingMode ? ' selected' : '' ?>><?= \App\Models\TurmaRanking::modeLabel($mode) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary btn--sm">Salvar</button>
        </div>
      </form>
      <?php if ($rankingMode !== 'off'): ?>
        <?php if ($ranking === []): ?>
          <p>Ninguém pontuou neste período ainda.</p>
        <?php else: ?>
          <ol class="ranking-list">
            <?php foreach ($ranking as $row): ?>
              <li class="ranking-row">
                <span class="ranking-row__pos"><?= $row['position'] ?>º</span>
                <span class="ranking-row__name"><?= \Core\View::e($row['name']) ?><?= $row['anonymous'] ? ' <small>(anônimo para os colegas)</small>' : '' ?></span>
                <span class="ranking-row__pts"><?= number_format($row['points'], 1, ',', '') ?> pts</span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- Mural -->
<div class="section" id="mural">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Mural <span class="badge badge--neutral"><?= count($posts) ?></span></h2>
        <p class="surface-copy">Recados para os alunos ativos da turma. Cada recado novo vira aviso para eles.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . (int) ($turma['id'] ?? 0) . '/mural') ?>" class="mural-form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="form-group">
          <label class="form-label" for="post-title">Título</label>
          <input class="form-input" type="text" id="post-title" name="title" maxlength="<?= \App\Models\TurmaPost::TITLE_MAX ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="post-body">Recado</label>
          <textarea class="form-input" id="post-body" name="body" rows="4" maxlength="<?= \App\Models\TurmaPost::BODY_MAX ?>" required></textarea>
        </div>
        <div class="mural-form__options">
          <label class="checkbox-label" for="post-pinned">
            <input type="checkbox" id="post-pinned" name="pinned" value="1"> Fixar no topo
          </label>
          <div class="form-group">
            <label class="form-label" for="post-expires">Expira em (opcional)</label>
            <input class="form-input" type="date" id="post-expires" name="expires_on" min="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Publicar recado</button>
        </div>
      </form>

      <?php foreach ($posts as $post): ?>
        <div class="mural-post-admin<?= (int) $post['expired'] === 1 ? ' mural-post-admin--expired' : '' ?>">
          <?php \Core\View::partial('partials/mural_post', ['post' => $post]); ?>
          <div class="mural-post-admin__actions">
            <?php if ((int) $post['expired'] === 1): ?>
              <span class="badge badge--neutral">Expirado</span>
            <?php elseif ($post['expires_at'] !== null): ?>
              <small>Expira em <?= date('d/m/Y', strtotime((string) $post['expires_at'])) ?></small>
            <?php endif; ?>
            <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . (int) $turma['id'] . '/mural/' . (int) $post['id'] . '/fixar') ?>">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <button type="submit" class="btn btn--ghost btn--sm"><?= (int) $post['pinned'] === 1 ? 'Desafixar' : 'Fixar' ?></button>
            </form>
            <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . (int) $turma['id'] . '/mural/' . (int) $post['id'] . '/excluir') ?>" data-confirm="Excluir este recado?">
              <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
              <button type="submit" class="btn btn--ghost btn--sm">Excluir</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<!-- Alunos ativos -->
<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Alunos ativos <span class="badge badge--neutral"><?= count($students) ?></span></h2>
        <p class="surface-copy">Participantes aprovados e já operando dentro da turma.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($students)): ?>
        <p class="empty-state">Nenhum aluno ativo ainda.</p>
      <?php else: ?>
        <div class="table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Ingressou em</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $s): ?>
                <tr>
                  <td><?= \Core\View::e($s['name']) ?></td>
                  <td><?= \Core\View::e($s['email']) ?></td>
                  <td><?= date('d/m/Y', strtotime($s['joined_at'])) ?></td>
                  <td>
                    <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . $turma['id'] . '/detach/' . $s['id']) ?>"
                      data-confirm="Desvincular este aluno da turma? O cadastro e o histórico serão preservados.">
                      <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                      <button class="btn btn--danger btn--sm">Desvincular</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
