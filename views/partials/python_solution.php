<?php
/**
 * Resposta em Python aprovada pelo Docente, recolhida atrás de um botão.
 * Espera $code (null = sem código aprovado: nada aparece). Só exibe; nunca executa.
 */
$code = isset($code) ? trim((string) $code) : '';
if ($code === '') {
  return;
}
?>
<details class="hint-details python-solution">
  <summary>Ver resposta em Python</summary>
  <pre class="code-block" tabindex="0"><code><?= \Core\View::e($code) ?></code></pre>
  <p class="hint">Uma resposta possível, revisada pelo docente. Outras soluções também podem estar certas.</p>
</details>
