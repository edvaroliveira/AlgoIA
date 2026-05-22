<?php
$turma = $turma ?? [];
$pending = $pending ?? [];
$students = $students ?? [];
$publications = $publications ?? [];
$pageTitle = 'Turma — Administração';
global $session;
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($turma['name'] ?? 'Turma') ?></h1>
    <p class="subtitle">Visão administrativa da turma com docente responsável, alunos vinculados e publicações associadas.</p>
  </div>
  <div class="td-actions">
    <a href="<?= \Core\app_url('/admin/turmas') ?>" class="btn btn--ghost">Voltar</a>
    <?php if ((bool) ($turma['active'] ?? false)): ?>
      <form method="POST" action="<?= \Core\app_url('/admin/turmas/' . ($turma['id'] ?? 0) . '/deactivate') ?>" onsubmit="return confirm('Inativar esta turma para novas entradas?');">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <button type="submit" class="btn btn--danger">Inativar turma</button>
      </form>
    <?php else: ?>
      <form method="POST" action="<?= \Core\app_url('/admin/turmas/' . ($turma['id'] ?? 0) . '/reactivate') ?>" onsubmit="return confirm('Reativar esta turma para novas entradas?');">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <button type="submit" class="btn btn--primary">Reativar turma</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Docente</span>
    <strong class="overview-card__value"><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></strong>
    <p class="overview-card__copy"><?= \Core\View::e($turma['teacher_email'] ?? '—') ?></p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Alunos ativos</span>
    <strong class="overview-card__value"><?= (int) ($turma['active_count'] ?? count($students)) ?></strong>
    <p class="overview-card__copy">Participantes aprovados atualmente vinculados à turma.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Pendências</span>
    <strong class="overview-card__value"><?= (int) ($turma['pending_count'] ?? count($pending)) ?></strong>
    <p class="overview-card__copy">Solicitações aguardando decisão docente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Situação</span>
    <strong class="overview-card__value"><?= (bool) ($turma['active'] ?? false) ? 'Ativa' : 'Inativa' ?></strong>
    <p class="overview-card__copy">Chave atual: <span class="overview-card__value--mono"><?= \Core\View::e($turma['access_key'] ?? '------') ?></span></p>
  </article>
</div>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Exercícios publicados para esta turma</h2>
      <p class="surface-copy">Leitura administrativa da carga pedagógica já vinculada a este grupo.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($publications)): ?>
      <p class="empty-state">Nenhum exercício publicado para esta turma.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Exercício</th>
            <th>Docente</th>
            <th>Abre</th>
            <th>Fecha</th>
            <th>Tentativas</th>
            <th>Submissões</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($publications as $publication): ?>
            <tr>
              <td><strong><?= \Core\View::e($publication['title']) ?></strong></td>
              <td><?= \Core\View::e($publication['teacher_name'] ?? '—') ?></td>
              <td><?= !empty($publication['opens_at']) ? date('d/m/Y H:i', strtotime((string) $publication['opens_at'])) : '—' ?></td>
              <td><?= !empty($publication['closes_at']) ? date('d/m/Y H:i', strtotime((string) $publication['closes_at'])) : '—' ?></td>
              <td><?= ((string) ($publication['max_attempts'] ?? '')) === '0' ? 'Ilimitadas' : (int) ($publication['max_attempts'] ?? 0) ?></td>
              <td><?= (int) ($publication['attempt_count'] ?? 0) ?></td>
              <td class="td-actions"><a href="<?= \Core\app_url('/admin/exercises/' . $publication['id']) ?>" class="btn btn--sm">Ver exercício</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>

<div class="cards-grid">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Solicitações pendentes</h2>
        <p class="surface-copy">Leitura das entradas ainda não aprovadas pelo docente.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($pending)): ?>
        <p class="empty-state">Nenhuma solicitação pendente.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Solicitado em</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pending as $student): ?>
              <tr>
                <td><?= \Core\View::e($student['name']) ?></td>
                <td><?= \Core\View::e($student['email']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime((string) $student['joined_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Alunos ativos</h2>
        <p class="surface-copy">Participantes aprovados que seguem vinculados ao grupo.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <?php if (empty($students)): ?>
        <p class="empty-state">Nenhum aluno ativo nesta turma.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Ingressou em</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $student): ?>
              <tr>
                <td><?= \Core\View::e($student['name']) ?></td>
                <td><?= \Core\View::e($student['email']) ?></td>
                <td><?= date('d/m/Y', strtotime((string) $student['joined_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>
