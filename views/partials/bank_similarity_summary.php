<?php
/** Resumo da similaridade de um Exercício com o banco. Espera $report (BankSimilarityService::report). */
$report = $report ?? null;
if (!$report) {
  return;
}
$fmtLimit = rtrim(rtrim(number_format((float) $report['block'], 1, ',', ''), '0'), ',');
$withMatches = count(array_filter($report['questions'], static fn(array $q): bool => $q['matches'] !== []));
?>
<?php if ($report['blocked'] > 0): ?>
  <p class="similarity-summary similarity-summary--blocked">
    <?= (int) $report['blocked'] ?> de <?= (int) $report['total'] ?> Questões não <?= $report['blocked'] === 1 ? 'entra' : 'entram' ?> no banco (similaridade ≥ <?= $fmtLimit ?>% com Questões existentes).
  </p>
<?php elseif ($withMatches > 0): ?>
  <p class="similarity-summary">
    <?= $withMatches ?> de <?= (int) $report['total'] ?> Questões parecidas com o banco, abaixo do limite de <?= $fmtLimit ?>%: todas entram.
  </p>
<?php endif; ?>
