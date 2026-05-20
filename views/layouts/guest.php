<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="theme-color" content="#0b4d78">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <title><?= \Core\View::e($pageTitle ?? 'IAProg') ?></title>
  <link rel="stylesheet" href="<?= \Core\app_url('/assets/css/app.css') ?>">
</head>

<body class="guest-layout">
  <div class="guest-shell container py-5">
    <div class="guest-card card shadow-lg border-0">
      <div class="guest-brand">
        <span class="brand-mark">IA</span>
        <h1 class="brand-name">IAProg</h1>
        <p class="brand-sub">Plataforma de algoritmos com visual institucional inspirado no iSACI</p>
      </div>
      <div class="guest-card__body">
        <?= $content ?>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \Core\app_url('/assets/js/app.js') ?>"></script>
</body>

</html>
