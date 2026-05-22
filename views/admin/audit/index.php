<?php
$pageTitle = 'Auditoria — Administração';
$logs = $logs ?? [];
$filters = $filters ?? ['search' => '', 'action' => '', 'entity_type' => ''];
$pagination = $pagination ?? ['totalPages' => 1, 'currentPage' => 1, 'totalItems' => count($logs), 'path' => '/admin/audit', 'query' => $filters];
?>

<div class="page-header">
  <div>
    <h1>Auditoria administrativa</h1>
    <p class="subtitle">Eventos recentes do sistema com foco em rastreabilidade operacional das ações sensíveis.</p>
  </div>
</div>

<section class="card card--narrow">
  <div class="card-body">
    <form method="GET" action="<?= \Core\app_url('/admin/audit') ?>" class="form">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="audit-search">Buscar</label>
          <input id="audit-search" type="text" name="search" class="form-input" value="<?= \Core\View::e($filters['search'] ?? '') ?>" placeholder="Ator, e-mail, ação ou entidade">
        </div>
        <div class="form-group">
          <label class="form-label" for="audit-action">Ação</label>
          <input id="audit-action" type="text" name="action" class="form-input" value="<?= \Core\View::e($filters['action'] ?? '') ?>" placeholder="Ex.: admin.user">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="audit-entity">Entidade</label>
          <select id="audit-entity" name="entity_type" class="form-input">
            <option value="">Todas</option>
            <option value="user" <?= ($filters['entity_type'] ?? '') === 'user' ? 'selected' : '' ?>>Usuário</option>
            <option value="turma" <?= ($filters['entity_type'] ?? '') === 'turma' ? 'selected' : '' ?>>Turma</option>
            <option value="exercise" <?= ($filters['entity_type'] ?? '') === 'exercise' ? 'selected' : '' ?>>Exercício</option>
            <option value="student" <?= ($filters['entity_type'] ?? '') === 'student' ? 'selected' : '' ?>>Aluno</option>
          </select>
        </div>
        <div class="form-group" style="justify-content: flex-end;">
          <label class="form-label">Ações</label>
          <div class="td-actions">
            <button type="submit" class="btn btn--primary">Filtrar</button>
            <a href="<?= \Core\app_url('/admin/audit') ?>" class="btn btn--ghost">Limpar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>

<div class="overview-grid">
  <article class="overview-card">
    <span class="overview-card__label">Eventos listados</span>
    <strong class="overview-card__value"><?= count($logs) ?></strong>
    <p class="overview-card__copy">A listagem é limitada aos 200 eventos mais recentes no filtro atual.</p>
  </article>
</div>

<?php if (empty($logs)): ?>
  <p class="empty-state">Nenhum evento encontrado para os filtros selecionados.</p>
<?php else: ?>
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Eventos recentes</h2>
        <p class="surface-copy">Cada linha registra ator, ação, entidade e metadados operacionais disponíveis.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <table class="table">
        <thead>
          <tr>
            <th>Quando</th>
            <th>Ator</th>
            <th>Ação</th>
            <th>Entidade</th>
            <th>Contexto</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <?php
            $metadata = json_decode((string) ($log['metadata_json'] ?? ''), true);
            $contextParts = [];
            if (is_array($metadata)) {
              foreach ($metadata as $key => $value) {
                if (is_array($value)) {
                  $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if ($value === null || $value === '') {
                  continue;
                }
                $contextParts[] = $key . ': ' . (string) $value;
              }
            }
            $contextText = $contextParts ? implode(' | ', $contextParts) : 'Sem metadados adicionais';
            ?>
            <tr>
              <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
              <td>
                <strong><?= \Core\View::e($log['actor_name'] ?? 'Sistema') ?></strong><br>
                <span class="text-muted"><?= \Core\View::e($log['actor_email'] ?? ($log['actor_role'] ?? 'guest')) ?></span>
              </td>
              <td><?= \Core\View::e($log['action']) ?></td>
              <td><?= \Core\View::e(($log['entity_type'] ?? '—') . (($log['entity_id'] ?? null) ? ' #' . $log['entity_id'] : '')) ?></td>
              <td><?= \Core\View::e($contextText) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php \Core\View::partial('partials/pagination', ['pagination' => $pagination]); ?>
    </div>
  </section>
<?php endif; ?>
