<?php $pageTitle = 'Alunos';
global $session;
$students = $students ?? [];
$totalStudents = count($students);
$activeStudents = count(array_filter($students, fn($student) => ($student['status'] ?? null) === 'active'));
$pendingStudents = count(array_filter($students, fn($student) => ($student['status'] ?? null) === 'pending'));
?>

<div class="page-header">
  <div>
    <h1>Alunos das suas turmas</h1>
    <p class="subtitle">Visão consolidada do vínculo, status e ações críticas da sua base ativa.</p>
  </div>
  <div class="hero-panel__meta">
    <span class="hero-chip hero-chip--surface">Total: <?= $totalStudents ?></span>
    <span class="hero-chip hero-chip--surface">Pendentes: <?= $pendingStudents ?></span>
  </div>
</div>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Alunos vinculados</span>
    <strong class="overview-card__value"><?= $totalStudents ?></strong>
    <p class="overview-card__copy">Todos os estudantes conectados a pelo menos uma das suas turmas.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Ativos</span>
    <strong class="overview-card__value"><?= $activeStudents ?></strong>
    <p class="overview-card__copy">Participando normalmente e com acesso liberado ao ambiente.</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Aguardando aprovação</span>
    <strong class="overview-card__value"><?= $pendingStudents ?></strong>
    <p class="overview-card__copy">Solicitações que ainda dependem da sua liberação em turma.</p>
  </article>
</div>

<?php if (empty($students)): ?>
  <p class="empty-state">Nenhum aluno cadastrado ainda.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Lista detalhada</h2>
        <p class="surface-copy">Exclusões seguem com remoção de respostas e registros relacionados.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Turmas</th>
            <th>Status</th>
            <th>Cadastrado em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td><?= \Core\View::e($s['name']) ?></td>
              <td><?= \Core\View::e($s['email']) ?></td>
              <td><?= \Core\View::e($s['turma_names'] ?? '—') ?></td>
              <td>
                <?php match ($s['status']) {
                  'active'   => print '<span class="badge badge--success">Ativo</span>',
                  'pending'  => print '<span class="badge badge--warning">Pendente</span>',
                  'inactive' => print '<span class="badge badge--neutral">Inativo</span>',
                  default    => print '',
                }; ?>
              </td>
              <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
              <td class="td-actions">
                <form method="POST" action="<?= \Core\app_url('/teacher/students/' . $s['id'] . '/delete') ?>" onsubmit="return confirm('Excluir este aluno e todos os registros dele?');">
                  <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                  <button type="submit" class="btn btn--danger btn--sm">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
