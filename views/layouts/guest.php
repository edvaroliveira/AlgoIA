<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= \Core\View::e($pageTitle ?? 'IAProg') ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="guest-layout">
  <div class="guest-card">
    <div class="guest-brand">
      <span class="brand-icon">⚙</span>
      <h1 class="brand-name">IAProg</h1>
      <p class="brand-sub">Aprendizado de Algoritmos com IA</p>
    </div>
    <?= $content ?>
  </div>
  <script src="/assets/js/app.js"></script>
</body>

</html>
