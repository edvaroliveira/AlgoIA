<?php
$user = $user ?? [];
$teacherTurmas = $teacherTurmas ?? [];
$teacherExercises = $teacherExercises ?? [];
$studentTurmas = $studentTurmas ?? [];
$studentAttempts = $studentAttempts ?? [];
$auditLogs = $auditLogs ?? [];
$pageTitle = 'Usuário — Administração';
$role = $user['role'] ?? 'student';
$status = $user['status'] ?? 'inactive';
?>

<div class="page-header">
  <div>
    <h1><?= \Core\View::e($user['name'] ?? 'Usuário') ?></h1>
    <p class="subtitle">Visão administrativa consolidada do perfil, vínculo operacional e eventos recentes deste usuário.</p>
  </div>
  <div class="td-actions">
    <a href="<?= \Core\app_url('/admin/users') ?>" class="btn btn--ghost">Voltar</a>
    <a href="<?= \Core\app_url('/admin/users/' . ($user['id'] ?? 0) . '/edit') ?>" class="btn btn--primary">Editar usuário</a>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Perfil</span>
    <strong class="overview-card__value"><?= $role === 'admin' ? 'Administrador' : ($role === 'teacher' ? 'Docente' : 'Aluno') ?></strong>
    <p class="overview-card__copy"><?= \Core\View::e($user['email'] ?? '—') ?></p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Status</span>
    <strong class="overview-card__value"><?= $status === 'active' ? 'Ativo' : ($status === 'pending' ? 'Pendente' : 'Inativo') ?></strong>
    <p class="overview-card__copy">Criado em <?= !empty($user['created_at']) ? date('d/m/Y', strtotime((string) $user['created_at'])) : '—' ?></p>
  </article>
  <?php if ($role === 'teacher'): ?>
    <article class="overview-card">
      <span class="overview-card__label">Turmas</span>
      <strong class="overview-card__value"><?= (int) ($user['owned_turma_count'] ?? count($teacherTurmas)) ?></strong>
      <p class="overview-card__copy">Grupos sob responsabilidade direta deste docente.</p>
    </article>
    <article class="overview-card">
      <span class="overview-card__label">Exercícios</span>
      <strong class="overview-card__value"><?= (int) ($user['exercise_count'] ?? count($teacherExercises)) ?></strong>
      <p class="overview-card__copy">Biblioteca de atividades vinculada ao docente.</p>
    </article>
  <?php elseif ($role === 'student'): ?>
    <article class="overview-card">
      <span class="overview-card__label">Turmas</span>
      <strong class="overview-card__value"><?= (int) ($user['turma_count'] ?? count($studentTurmas)) ?></strong>
      <p class="overview-card__copy"><?= \Core\View::e($user['turma_names'] ?? 'Sem turma') ?></p>
    </article>
    <article class="overview-card">
      <span class="overview-card__label">Tentativas</span>
      <strong class="overview-card__value"><?= (int) ($user['attempt_count'] ?? count($studentAttempts)) ?></strong>
      <p class="overview-card__copy">Última submissão: <?= !empty($user['last_attempt_at']) ? date('d/m/Y H:i', strtotime((string) $user['last_attempt_at'])) : '—' ?></p>
    </article>
  <?php else: ?>
    <article class="overview-card">
      <span class="overview-card__label">Escopo</span>
      <strong class="overview-card__value">Global</strong>
      <p class="overview-card__copy">Este perfil possui acesso administrativo ao sistema.</p>
    </article>
    <article class="overview-card">
      <span class="overview-card__label">Eventos</span>
      <strong class="overview-card__value"><?= count($auditLogs) ?></strong>
      <p class="overview-card__copy">Ocorrências recentes relacionadas ao usuário no recorte atual.</p>
    </article>
  <?php endif; ?>
</div>

<?php if ($role === 'teacher'): ?>
  <div class="cards-grid">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Turmas do docente</h2>
          <p class="surface-copy">Grupos atualmente associados a este usuário.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <?php if (empty($teacherTurmas)): ?>
          <p class="empty-state">Nenhuma turma vinculada.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Turma</th>
                <th>Chave</th>
                <th>Ativos</th>
                <th>Pendentes</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teacherTurmas as $turma): ?>
                <tr>
                  <td><?= \Core\View::e($turma['name']) ?></td>
                  <td><span class="overview-card__value overview-card__value--mono"><?= \Core\View::e($turma['access_key']) ?></span></td>
                  <td><?= (int) ($turma['active_count'] ?? 0) ?></td>
                  <td><?= (int) ($turma['pending_count'] ?? 0) ?></td>
                  <td class="td-actions"><a href="<?= \Core\app_url('/admin/turmas/' . $turma['id']) ?>" class="btn btn--sm">Ver turma</a></td>
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
          <h2 class="surface-title">Exercícios do docente</h2>
          <p class="surface-copy">Atividades criadas sob autoria deste usuário.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <?php if (empty($teacherExercises)): ?>
          <p class="empty-state">Nenhum exercício vinculado.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Título</th>
                <th>Turmas</th>
                <th>Submissões</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($teacherExercises as $exercise): ?>
                <tr>
                  <td><?= \Core\View::e($exercise['title']) ?></td>
                  <td><?= \Core\View::e($exercise['turma_label'] ?? 'Pendente de finalização') ?></td>
                  <td><?= (int) ($exercise['attempt_count'] ?? 0) ?></td>
                  <td><?= \Core\View::e($exercise['status'] ?? 'draft') ?></td>
                  <td class="td-actions"><a href="<?= \Core\app_url('/admin/exercises/' . $exercise['id']) ?>" class="btn btn--sm">Ver exercício</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </div>
<?php elseif ($role === 'student'): ?>
  <div class="cards-grid">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Turmas do aluno</h2>
          <p class="surface-copy">Vínculos atuais deste estudante na plataforma.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <?php if (empty($studentTurmas)): ?>
          <p class="empty-state">Nenhuma turma vinculada.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Turma</th>
                <th>Docente</th>
                <th>Status</th>
                <th>Entrada</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentTurmas as $turma): ?>
                <tr>
                  <td><?= \Core\View::e($turma['name']) ?></td>
                  <td><?= \Core\View::e($turma['teacher_name'] ?? '—') ?></td>
                  <td><?= \Core\View::e($turma['enrollment_status'] ?? '—') ?></td>
                  <td><?= !empty($turma['joined_at']) ? date('d/m/Y', strtotime((string) $turma['joined_at'])) : '—' ?></td>
                  <td class="td-actions"><a href="<?= \Core\app_url('/admin/turmas/' . $turma['id']) ?>" class="btn btn--sm">Ver turma</a></td>
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
          <h2 class="surface-title">Tentativas do aluno</h2>
          <p class="surface-copy">Histórico recente de submissões e progresso.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <?php if (empty($studentAttempts)): ?>
          <p class="empty-state">Nenhuma tentativa registrada.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Exercício</th>
                <th>Docente</th>
                <th>Status</th>
                <th>Nota</th>
                <th>Último evento</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentAttempts as $attempt): ?>
                <tr>
                  <td><?= \Core\View::e($attempt['exercise_title'] ?? '—') ?></td>
                  <td><?= \Core\View::e($attempt['teacher_name'] ?? '—') ?></td>
                  <td><?= \Core\View::e($attempt['status'] ?? '—') ?></td>
                  <td><?= $attempt['total_score'] !== null ? number_format((float) $attempt['total_score'], 1) : '—' ?></td>
                  <td><?= !empty($attempt['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $attempt['submitted_at'])) : (!empty($attempt['started_at']) ? date('d/m/Y H:i', strtotime((string) $attempt['started_at'])) : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>
  </div>
<?php endif; ?>

<section class="surface-block">
  <div class="surface-block__header">
    <div>
      <h2 class="surface-title">Auditoria recente</h2>
      <p class="surface-copy">Eventos mais recentes em que este usuário aparece como ator ou como entidade afetada.</p>
    </div>
  </div>
  <div class="surface-block__body">
    <?php if (empty($auditLogs)): ?>
      <p class="empty-state">Nenhum evento recente encontrado para este usuário.</p>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Quando</th>
            <th>Ação</th>
            <th>Ator</th>
            <th>Entidade</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($auditLogs as $log): ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime((string) $log['created_at'])) ?></td>
              <td><?= \Core\View::e($log['action']) ?></td>
              <td><?= \Core\View::e($log['actor_name'] ?? ($log['actor_role'] ?? 'Sistema')) ?></td>
              <td><?= \Core\View::e(($log['entity_type'] ?? '—') . (($log['entity_id'] ?? null) ? ' #' . $log['entity_id'] : '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
