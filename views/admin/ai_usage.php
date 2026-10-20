<?php
use App\Models\AiUsageLog;

$pageTitle = 'Uso da IA';

global $session;
$filters = $filters ?? [];
$settings = $settings ?? [];
$summary = $summary ?? [];
$rate = (float) ($settings['usd_brl'] ?? 0);
$usd = static fn(float $v): string => 'US$ ' . number_format($v, $v < 1 ? 4 : 2, ',', '.');
$brl = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
$int = static fn($v): string => number_format((int) $v, 0, ',', '.');
$periods = ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', 'month' => 'Mês atual', 'prev_month' => 'Mês anterior', 'custom' => 'Intervalo'];
$errorRate = $summary['calls'] > 0 ? $summary['errors'] / $summary['calls'] * 100 : 0;
$costPerAnswer = $summary['ok_calls'] > 0 ? $summary['cost_usd'] / $summary['ok_calls'] : 0;
$budget = (float) ($settings['budget_brl'] ?? 0);
$monthBrl = (float) ($monthSpendUsd ?? 0) * $rate;
$budgetPct = $budget > 0 ? min(100, $monthBrl / $budget * 100) : 0;
$exportQuery = http_build_query(array_filter($filters, static fn($v) => $v !== '' && $v !== 0));
$table = static function (array $rows, string $title, string $labelHeader) use ($usd, $brl, $int, $rate): void { ?>
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong><?= \Core\View::e($title) ?></strong></p>
      <?php if ($rows === []): ?>
        <p class="empty-state">Sem uso no período.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th><?= \Core\View::e($labelHeader) ?></th><th>Chamadas</th><th>Tokens</th><th>Custo</th><th>Por resposta</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row):
              $ok = max(1, (int) $row['ok_calls']);
            ?>
              <tr>
                <td><?= \Core\View::e($row['label']) ?></td>
                <td><?= $int($row['calls']) ?></td>
                <td><?= $int($row['tokens']) ?></td>
                <td><?= $usd((float) $row['cost_usd']) ?><br><small><?= $brl((float) $row['cost_usd'] * $rate) ?></small></td>
                <td><?= $usd((float) $row['cost_usd'] / $ok) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
<?php };
?>

<div class="page-header">
  <div>
    <h1>Uso da IA</h1>
    <p class="subtitle">Chamadas à OpenAI das correções de Tentativas e da Prática: tokens, custo estimado, falhas e latência.</p>
  </div>
  <a href="<?= \Core\app_url('/admin/ia/export' . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>" class="btn btn--ghost">Exportar CSV</a>
</div>

<section class="surface-block">
  <div class="surface-block__body">
    <form method="GET" action="<?= \Core\app_url('/admin/ia') ?>" class="ai-filters">
      <div class="form-group">
        <label class="form-label" for="ai-period">Período</label>
        <select class="form-input" id="ai-period" name="period">
          <?php foreach ($periods as $value => $label): ?>
            <option value="<?= $value ?>"<?= $filters['period'] === $value ? ' selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-from">De</label>
        <input class="form-input" type="date" id="ai-from" name="from" value="<?= \Core\View::e($filters['from']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-to">Até</label>
        <input class="form-input" type="date" id="ai-to" name="to" value="<?= \Core\View::e($filters['to']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-teacher">Docente</label>
        <select class="form-input" id="ai-teacher" name="teacher_id">
          <option value="0">Todos</option>
          <?php foreach ($teacherOptions as $option): ?>
            <option value="<?= (int) $option['id'] ?>"<?= $filters['teacher_id'] === (int) $option['id'] ? ' selected' : '' ?>><?= \Core\View::e($option['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-turma">Turma</label>
        <select class="form-input" id="ai-turma" name="turma_id">
          <option value="0">Todas</option>
          <?php foreach ($turmaOptions as $option): ?>
            <option value="<?= (int) $option['id'] ?>"<?= $filters['turma_id'] === (int) $option['id'] ? ' selected' : '' ?>><?= \Core\View::e($option['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" for="ai-kind">Tipo</label>
        <select class="form-input" id="ai-kind" name="kind">
          <option value="">Todos</option>
          <option value="grading"<?= $filters['kind'] === 'grading' ? ' selected' : '' ?>>Correção de Tentativas</option>
          <option value="practice"<?= $filters['kind'] === 'practice' ? ' selected' : '' ?>>Prática</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
      </div>
    </form>
  </div>
</section>

<div class="overview-grid section">
  <article class="overview-card">
    <span class="overview-card__label">Custo estimado</span>
    <strong class="overview-card__value"><?= $brl($summary['cost_usd'] * $rate) ?></strong>
    <p class="overview-card__copy"><?= $usd($summary['cost_usd']) ?> · câmbio <?= number_format($rate, 2, ',', '.') ?></p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Chamadas</span>
    <strong class="overview-card__value"><?= $int($summary['calls']) ?></strong>
    <p class="overview-card__copy"><?= $int($summary['errors']) ?> com falha (<?= number_format($errorRate, 1, ',', '.') ?>%)</p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Tokens</span>
    <strong class="overview-card__value"><?= $int($summary['prompt_tokens'] + $summary['completion_tokens']) ?></strong>
    <p class="overview-card__copy">entrada <?= $int($summary['prompt_tokens']) ?> · saída <?= $int($summary['completion_tokens']) ?></p>
  </article>
  <article class="overview-card">
    <span class="overview-card__label">Por resposta corrigida</span>
    <strong class="overview-card__value"><?= $usd($costPerAnswer) ?></strong>
    <p class="overview-card__copy">latência média <?= $int($summary['avg_latency']) ?> ms · p95 <?= $int($summary['p95_latency']) ?> ms</p>
  </article>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Modelo em uso</strong></p>
      <p class="ai-model">
        <span class="badge badge--info"><?= \Core\View::e($currentModel ?? '') ?></span>
        entrada <?= $usd((float) $settings['price_input']) ?> · saída <?= $usd((float) $settings['price_output']) ?> por 1 milhão de tokens
        <?php if ($summary['ok_calls'] > 0): ?>
          · custo médio por resposta corrigida no período: <?= $usd($costPerAnswer) ?> (<?= $brl($costPerAnswer * $rate) ?>)
        <?php endif; ?>
      </p>
      <p><small>O modelo vem de <code>OPENAI_MODEL</code> no .env do servidor. Ao trocar de modelo, atualize os preços abaixo; o custo já registrado não muda.</small></p>
    </div>
  </section>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Orçamento do mês</strong></p>
      <?php if ($budget <= 0): ?>
        <p>Sem orçamento definido. Gasto do mês: <?= $brl($monthBrl) ?>.</p>
      <?php else: ?>
        <div class="ai-budget<?= $budgetPct >= 100 ? ' ai-budget--over' : ($budgetPct >= 80 ? ' ai-budget--warn' : '') ?>">
          <progress class="ai-budget__bar" max="100" value="<?= number_format($budgetPct, 1, '.', '') ?>"><?= number_format($budgetPct, 0) ?>%</progress>
          <p><?= $brl($monthBrl) ?> de <?= $brl($budget) ?> (<?= number_format($monthBrl / $budget * 100, 1, ',', '.') ?>%). Os Admins recebem aviso a 80% e a 100%; nada é bloqueado.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="ai-charts section">
  <section class="surface-block"><div class="surface-block__body"><p><strong>Custo por dia (US$)</strong></p><canvas id="chart-cost-day" height="220"></canvas></div></section>
  <section class="surface-block"><div class="surface-block__body"><p><strong>Chamadas e falhas por dia</strong></p><canvas id="chart-calls-day" height="220"></canvas></div></section>
  <section class="surface-block"><div class="surface-block__body"><p><strong>Top 10 Docentes por custo (US$)</strong></p><canvas id="chart-teachers" height="260"></canvas></div></section>
  <section class="surface-block"><div class="surface-block__body"><p><strong>Top 10 Turmas por custo (US$)</strong></p><canvas id="chart-turmas" height="260"></canvas></div></section>
  <section class="surface-block"><div class="surface-block__body"><p><strong>Correção x Prática (custo)</strong></p><canvas id="chart-kinds" height="220"></canvas></div></section>
</div>
<script type="application/json" id="ai-usage-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="section"><?php $table($byTeacher, 'Por Docente', 'Docente'); ?></div>
<div class="section"><?php $table($byTurma, 'Por Turma', 'Turma'); ?></div>
<div class="section"><?php $table($byModel ?? [], 'Por modelo', 'Modelo'); ?></div>
<div class="section"><?php $table($byExercise, 'Por Exercício (top 20)', 'Exercício'); ?></div>
<div class="section"><?php $table($topStudents, 'Alunos que mais usam a Prática (top 10)', 'Aluno'); ?></div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Falhas recentes</strong></p>
      <?php if ($recentFailures === []): ?>
        <p class="empty-state">Nenhuma falha no período.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Quando</th><th>Tipo</th><th>Categoria</th><th>Docente</th><th>Aluno</th></tr></thead>
          <tbody>
            <?php foreach ($recentFailures as $failure): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime((string) $failure['created_at'])) ?></td>
                <td><?= $failure['kind'] === AiUsageLog::KIND_PRACTICE ? 'Prática' : 'Correção' ?></td>
                <td><?= \Core\View::e($failure['error_category'] ?? '') ?></td>
                <td><?= \Core\View::e($failure['teacher_name'] ?? '—') ?></td>
                <td><?= \Core\View::e($failure['student_name'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="section">
  <section class="surface-block">
    <div class="surface-block__body">
      <p><strong>Preços, câmbio e orçamento</strong> — valem para as próximas chamadas; o custo já registrado não muda.</p>
      <form method="POST" action="<?= \Core\app_url('/admin/ia/configuracoes') ?>" class="ai-filters">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="form-group">
          <label class="form-label" for="ai-price-in">Entrada (US$ / 1M tokens)</label>
          <input class="form-input" type="text" inputmode="decimal" id="ai-price-in" name="price_input" value="<?= \Core\View::e((string) $settings['price_input']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="ai-price-out">Saída (US$ / 1M tokens)</label>
          <input class="form-input" type="text" inputmode="decimal" id="ai-price-out" name="price_output" value="<?= \Core\View::e((string) $settings['price_output']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="ai-rate">Cotação US$ → R$</label>
          <input class="form-input" type="text" inputmode="decimal" id="ai-rate" name="usd_brl_rate" value="<?= \Core\View::e((string) $settings['usd_brl']) ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="ai-budget">Orçamento mensal (R$, 0 = sem)</label>
          <input class="form-input" type="text" inputmode="decimal" id="ai-budget" name="monthly_budget_brl" value="<?= \Core\View::e((string) $settings['budget_brl']) ?>">
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary btn--sm">Salvar</button>
        </div>
      </form>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" integrity="sha384-NrKB+u6Ts6AtkIhwPixiKTzgSKNblyhlk0Sohlgar9UHUBzai/sgnNNWWd291xqt" crossorigin="anonymous"></script>
<script src="<?= \Core\app_url('/assets/js/admin-ai-usage.js') ?>"></script>
