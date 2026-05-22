<?php $pageTitle = 'Dashboard — Docente'; ?>
<?php
$turmas = $turmas ?? [];
$totalExs = $totalExs ?? 0;
$openCount = $openCount ?? 0;
$pendingTotal = $pendingTotal ?? 0;
$activeTotal = $activeTotal ?? 0;
$exercises = $exercises ?? [];
$recentStudents = $recentStudents ?? [];
$pendingGradingCount = $pendingGradingCount ?? 0;
$pendingGradingAttempts = $pendingGradingAttempts ?? [];
?>

<section class="hero-panel hero-panel--teacher">
  <div>
    <div class="hero-panel__eyebrow">Visão geral docente</div>
    <h2 class="hero-panel__title">Central de acompanhamento da sua operação acadêmica.</h2>
    <p class="hero-panel__copy">Acompanhe turmas, aprovações, exercícios em andamento e ações rápidas sem navegar em telas fragmentadas.</p>
  </div>
  <div class="hero-panel__meta">
    <span class="hero-chip">Docente: <?= \Core\View::e(\Core\Auth::user()['name']) ?></span>
    <span class="hero-chip hero-chip--soft">Turmas ativas: <?= count($turmas) ?></span>
  </div>
</section>

<div class="section-header section-header--tight">
  <h2>Indicadores principais</h2>
  <a href="<?= \Core\app_url('/teacher/exercises/create') ?>" class="btn btn--primary btn--sm">Criar exercício</a>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value"><?= count($turmas) ?></div>
    <div class="stat-label">Turmas</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $totalExs ?></div>
    <div class="stat-label">Exercícios</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $openCount ?></div>
    <div class="stat-label">Abertos agora</div>
  </div>
  <div class="stat-card stat-card--warning">
    <div class="stat-value"><?= $pendingTotal ?></div>
    <div class="stat-label">Aprovações pendentes</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= $activeTotal ?></div>
    <div class="stat-label">Alunos ativos</div>
  </div>
  <div class="stat-card stat-card--warning">
    <div class="stat-value"><?= $pendingGradingCount ?></div>
    <div class="stat-label">Correções pendentes</div>
  </div>
</div>

<?php if ($pendingTotal > 0): ?>
  <div class="alert alert--warning">
    Você tem <strong><?= $pendingTotal ?></strong> aluno(s) aguardando aprovação.
    <a href="<?= \Core\app_url('/teacher/turmas') ?>">Revisar agora →</a>
  </div>
<?php endif; ?>

<?php global $session; ?>

<?php if (!empty($pendingGradingAttempts)): ?>
  <div class="section">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title">Correções pendentes</h2>
          <p class="surface-copy">Tentativas enviadas que aguardam reprocessamento da avaliação automática.</p>
        </div>
      </div>
      <div class="surface-block__body">
        <table class="table">
          <thead>
            <tr>
              <th>Aluno</th>
              <th>Exercício</th>
              <th>Turma</th>
              <th>Enviada em</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingGradingAttempts as $attempt): ?>
              <tr>
                <td><?= \Core\View::e($attempt['student_name'] ?? '—') ?></td>
                <td><?= \Core\View::e($attempt['exercise_title'] ?? '—') ?></td>
                <td><?= \Core\View::e($attempt['turma_name'] ?? '—') ?></td>
                <td><?= !empty($attempt['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $attempt['submitted_at'])) : '—' ?></td>
                <td class="td-actions">
                  <form method="POST" action="<?= \Core\app_url('/teacher/attempts/' . (int) ($attempt['id'] ?? 0) . '/regrade') ?>">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                    <input type="hidden" name="return_to" value="<?= \Core\View::e(\Core\app_request_path()) ?>">
                    <button type="submit" class="btn btn--sm btn--primary">Reprocessar</button>
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

<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Exercícios recentes</h2>
        <p class="surface-copy">Atalhos para as últimas atividades criadas e leitura rápida do status de cada janela.</p>
      </div>
      <a href="<?= \Core\app_url('/teacher/exercises/create') ?>" class="btn btn--primary btn--sm">+ Novo exercício</a>
    </div>

    <div class="surface-block__body">
      <?php if (empty($exercises)): ?>
        <p class="empty-state">Nenhum exercício criado ainda. <a href="<?= \Core\app_url('/teacher/exercises/create') ?>">Criar o primeiro</a>.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Turma</th>
              <th>Abre</th>
              <th>Fecha</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($exercises as $ex):
              $now    = time();
              $isDraft = ($ex['status'] ?? 'active') === 'draft';
              $isReady = ($ex['status'] ?? 'active') === 'ready';
              $open = !empty($ex['opens_at']) && !empty($ex['closes_at'])
                && strtotime($ex['opens_at']) <= $now && strtotime($ex['closes_at']) >= $now;
              $closed = !empty($ex['closes_at']) && strtotime($ex['closes_at']) < $now;
            ?>
              <tr>
                <td><?= \Core\View::e($ex['title']) ?></td>
                <td><?= \Core\View::e($ex['turma_label'] ?? 'Pendente de finalização') ?></td>
                <td><?= !empty($ex['opens_at']) ? date('d/m/Y H:i', strtotime($ex['opens_at'])) : '—' ?></td>
                <td><?= !empty($ex['closes_at']) ? date('d/m/Y H:i', strtotime($ex['closes_at'])) : '—' ?></td>
                <td>
                  <?php if ($isDraft): ?>
                    <span class="badge badge--warning">Rascunho</span>
                  <?php elseif ($isReady): ?>
                    <span class="badge badge--info">Pronto para publicar</span>
                  <?php elseif ($closed): ?>
                    <span class="badge badge--neutral">Encerrado</span>
                  <?php elseif ($open): ?>
                    <span class="badge badge--success">Aberto</span>
                  <?php else: ?>
                    <span class="badge badge--info">Agendado</span>
                  <?php endif; ?>
                </td>
                <td class="td-actions"><a href="<?= \Core\app_url('/teacher/exercises/' . $ex['id']) ?>" class="btn btn--sm">Ver</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <?php if (!empty($exercises)): ?>
      <div class="surface-block__footer">
        <a href="<?= \Core\app_url('/teacher/exercises') ?>" class="surface-link">Abrir biblioteca completa de exercícios →</a>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Alunos recentes</h2>
        <p class="surface-copy">Panorama dos últimos vínculos visíveis na sua base, mantendo as ações críticas acessíveis.</p>
      </div>
      <a href="<?= \Core\app_url('/teacher/students') ?>" class="btn btn--ghost btn--sm">Ver todos</a>
    </div>

    <div class="surface-block__body">
      <?php if (empty($recentStudents)): ?>
        <p class="empty-state">Nenhum aluno vinculado às suas turmas ainda.</p>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Turmas</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentStudents as $student): ?>
              <tr>
                <td><?= \Core\View::e($student['name']) ?></td>
                <td><?= \Core\View::e($student['email']) ?></td>
                <td><?= \Core\View::e($student['turma_names'] ?? '—') ?></td>
                <td>
                  <?php match ($student['status']) {
                    'active'   => print '<span class="badge badge--success">Ativo</span>',
                    'pending'  => print '<span class="badge badge--warning">Pendente</span>',
                    'inactive' => print '<span class="badge badge--neutral">Inativo</span>',
                    default    => print '',
                  }; ?>
                </td>
                <td class="td-actions">
                  <form method="POST" action="<?= \Core\app_url('/teacher/students/' . $student['id'] . '/detach') ?>" onsubmit="return confirm('Desvincular este aluno das suas turmas? O cadastro e o histórico serão preservados.');">
                    <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
                    <button type="submit" class="btn btn--danger btn--sm">Desvincular</button>
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
