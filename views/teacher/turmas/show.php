<?php
$turma = $turma ?? [];
$pending = $pending ?? [];
$students = $students ?? [];
$pageTitle = 'Turma — ' . ($turma['name'] ?? '');
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($turma['name'] ?? 'Turma') ?></h1>
    <p class="subtitle">Gerencie chave de acesso, solicitações pendentes e alunos já ativos em um único fluxo.</p>
  </div>
  <a href="<?= \Core\app_url('/teacher/turmas') ?>" class="btn btn--ghost">← Turmas</a>
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
      onsubmit="return confirm('Gerar nova chave? A chave atual deixará de funcionar para novos cadastros.');">
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
                    onsubmit="return confirm('Rejeitar este aluno?');">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                    <button class="btn btn--danger btn--sm">Rejeitar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
<?php endif; ?>

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
                  <form method="POST" action="<?= \Core\app_url('/teacher/turmas/' . $turma['id'] . '/reject/' . $s['id']) ?>"
                    onsubmit="return confirm('Remover este aluno da turma?');">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                    <button class="btn btn--danger btn--sm">Remover</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>
