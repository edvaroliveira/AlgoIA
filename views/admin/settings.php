<?php
use App\Services\EnvSettings;

$pageTitle = 'Configurações';

global $session;
$groups = $groups ?? [];
$values = $values ?? [];
$overridden = $overridden ?? [];
?>

<div class="page-header">
  <div>
    <h1>Configurações do ambiente</h1>
    <p class="subtitle">Edita o arquivo .env do servidor. Cada grupo é testado antes de salvar; senhas e chaves nunca aparecem inteiras. Para salvar, confirme sua senha.</p>
  </div>
</div>

<?php if (!($writable ?? false)): ?>
  <div class="alert alert--error" role="alert">O servidor não consegue gravar o arquivo .env (permissões). As configurações só podem ser vistas.</div>
<?php endif; ?>

<?php foreach ($groups as $groupKey => $group): ?>
  <div class="section" id="grupo-<?= $groupKey ?>">
    <section class="surface-block">
      <div class="surface-block__header">
        <div>
          <h2 class="surface-title"><?= \Core\View::e($group['title']) ?></h2>
          <p class="surface-copy"><?= \Core\View::e($group['help']) ?></p>
        </div>
      </div>
      <div class="surface-block__body">
        <form method="POST" action="<?= \Core\app_url('/admin/configuracoes/' . $groupKey) ?>" autocomplete="off" class="settings-form">
          <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
          <div class="settings-grid">
            <?php foreach ($group['fields'] as $key => $field):
              $current = $values[$key] ?? null;
              $id = 'env-' . strtolower($key);
            ?>
              <div class="form-group">
                <label class="form-label" for="<?= $id ?>">
                  <?= \Core\View::e($field['label']) ?> <small><code><?= $key ?></code></small>
                </label>
                <?php if ($field['type'] === 'select'): ?>
                  <select class="form-input" id="<?= $id ?>" name="<?= $key ?>">
                    <?php foreach ($field['options'] as $option => $label): ?>
                      <option value="<?= \Core\View::e($option) ?>"<?= (string) ($current ?? $field['default'] ?? '') === (string) $option ? ' selected' : '' ?>><?= \Core\View::e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php elseif ($field['type'] === 'secret'): ?>
                  <input class="form-input" type="password" id="<?= $id ?>" name="<?= $key ?>" value="" autocomplete="new-password"
                    placeholder="<?= $current !== null && $current !== '' ? \Core\View::e(EnvSettings::display($field, $current)) . ' — em branco mantém' : 'não definido' ?>">
                <?php else: ?>
                  <input class="form-input" type="<?= $field['type'] === 'int' ? 'number' : 'text' ?>" id="<?= $id ?>" name="<?= $key ?>"
                    value="<?= \Core\View::e(EnvSettings::display($field, $current)) ?>"
                    <?= isset($field['min']) ? 'min="' . (int) $field['min'] . '" max="' . (int) $field['max'] . '"' : '' ?>>
                <?php endif; ?>
                <?php if (in_array($key, $overridden, true)): ?>
                  <small class="settings-warning">Definido também nas variáveis do servidor, que têm prioridade sobre o .env.</small>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="settings-confirm">
            <div class="form-group">
              <label class="form-label" for="pwd-<?= $groupKey ?>">Sua senha de Admin</label>
              <input class="form-input" type="password" id="pwd-<?= $groupKey ?>" name="admin_password" required autocomplete="current-password">
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn--primary"<?= ($writable ?? false) ? '' : ' disabled' ?>>Testar e salvar</button>
            </div>
          </div>
        </form>
      </div>
    </section>
  </div>
<?php endforeach; ?>

<?php
$bankSimilarity = $bankSimilarity ?? ['block' => 97.0, 'show' => 85.0, 'judge' => false];
$pctValue = static fn(float $v): string => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
?>
<div class="section" id="banco">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Banco de exercícios</h2>
        <p class="surface-copy">Similaridade entre enunciados de Questões, calculada pelo próprio sistema. Fica no banco de dados (não no .env) e vale para a aprovação de Sugestões e a inclusão direta.</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/admin/configuracoes/banco') ?>" class="settings-form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <div class="settings-grid">
          <div class="form-group">
            <label class="form-label" for="bank-block">Limite de similaridade (%)</label>
            <input class="form-input" type="text" inputmode="decimal" id="bank-block" name="bank_similarity_block_pct" value="<?= $pctValue((float) $bankSimilarity['block']) ?>" aria-describedby="bank-block-help" required>
            <small id="bank-block-help" class="hint">A partir deste valor a Questão não entra no banco (as demais do Exercício entram). Entre 50 e 100.</small>
          </div>
          <div class="form-group">
            <label class="form-label" for="bank-show">Mostrar semelhança a partir de (%)</label>
            <input class="form-input" type="text" inputmode="decimal" id="bank-show" name="bank_similarity_show_pct" value="<?= $pctValue((float) $bankSimilarity['show']) ?>" aria-describedby="bank-show-help" required>
            <small id="bank-show-help" class="hint">Abaixo do limite, a Questão entra, mas a semelhança aparece para o Admin. Entre 0 e 100.</small>
          </div>
        </div>
        <label class="checkbox-label" for="bank-judge">
          <input type="checkbox" id="bank-judge" name="bank_ai_judge_enabled" value="1"<?= !empty($bankSimilarity['judge']) ? ' checked' : '' ?> aria-describedby="bank-judge-help">
          Usar IA como juiz dos casos duvidosos
        </label>
        <small id="bank-judge-help" class="hint">
          Questões de Sugestões com semelhança entre “mostrar a partir de” e o limite são enviadas à IA (pelo cron, mesma chave e modelo da correção),
          que diz se pedem a mesma Questão. Só informa: quem decide continua sendo o Admin. O custo aparece em Uso da IA como “Similaridade”.
        </small>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Salvar</button>
        </div>
      </form>
    </div>
  </section>
</div>

<div class="section" id="python">
  <section class="surface-block">
    <div class="surface-block__header">
      <div>
        <h2 class="surface-title">Resposta em Python</h2>
        <p class="surface-copy">Código da resposta correta de cada Questão, mostrado ao Aluno depois que o Docente aprova. Fica no banco de dados (não no .env).</p>
      </div>
    </div>
    <div class="surface-block__body">
      <form method="POST" action="<?= \Core\app_url('/admin/configuracoes/python') ?>" class="settings-form">
        <input type="hidden" name="_csrf_token" value="<?= \Core\View::e($session->csrfToken()) ?>">
        <label class="checkbox-label" for="python-ai">
          <input type="checkbox" id="python-ai" name="python_solution_ai_enabled" value="1"<?= !empty($pythonSolutionEnabled) ? ' checked' : '' ?> aria-describedby="python-ai-help">
          Gerar a Resposta em Python com IA
        </label>
        <small id="python-ai-help" class="hint">
          Quando o Docente conclui um Exercício, o cron pede à IA o código de cada Questão, uma vez só (mesma chave e modelo da correção).
          Questões importadas do Banco reaproveitam o código já aprovado, sem nova chamada. Desligado, os Docentes ainda podem escrever o código à mão.
          O custo aparece em Uso da IA como “Resposta em Python”.
        </small>
        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Salvar</button>
        </div>
      </form>
    </div>
  </section>
</div>
