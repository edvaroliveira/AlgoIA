<!DOCTYPE html>
<html lang="pt-br">

<?php $content = $content ?? ''; ?>

<head>
  <meta charset="UTF-8">
  <meta name="theme-color" content="#173f31">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="<?= \Core\app_url('/assets/img/algoIA.ico') ?>">
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <title><?= \Core\View::e($pageTitle ?? 'AlgoIA') ?></title>
  <link rel="stylesheet" href="<?= \Core\app_url('/assets/css/app.css') ?>">
</head>

<body class="guest-layout">
  <div class="guest-shell container-xxl py-4 py-lg-5">
    <div class="guest-grid">
      <section class="guest-hero">
        <div class="guest-hero__badge">
          <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo.png') ?>" alt="Logo AlgoIA" class="guest-hero__logo">
        </div>
        <h1 class="guest-hero__title">Algo IA</h1>
        <p class="guest-hero__copy">Aprendizagem, algoritmos e Amazônia em uma interface institucional mais sóbria, com respiro, contraste limpo e presença visual inspirada no manual de identidade 2025.</p>

        <div class="guest-feature-list">
          <div class="guest-feature">
            <strong>Aprendizagem</strong>
            <span>Fluxo claro para acompanhamento, prática e avaliação em ambiente acadêmico.</span>
          </div>
          <div class="guest-feature">
            <strong>Algoritmos</strong>
            <span>Estrutura pensada para exercícios, questões e correção assistida sem ruído visual.</span>
          </div>
          <div class="guest-feature">
            <strong>Amazônia</strong>
            <span>Verdes institucionais e base marfim aproximam a interface da identidade do manual AlgoIA.</span>
          </div>
        </div>
      </section>

      <section class="guest-card">
        <div class="guest-brand">
          <img src="<?= \Core\app_url('/assets/img/AlgoIA_logo.png') ?>" alt="Logo AlgoIA" class="brand-logo brand-logo--guest">
          <div>
            <h2 class="brand-name">AlgoIA</h2>
            <p class="brand-sub">Acesso ao ambiente institucional</p>
          </div>
        </div>
        <div class="guest-card__body">
          <?= $content ?>
        </div>
      </section>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \Core\app_url('/assets/js/app.js') ?>"></script>
</body>

</html>
