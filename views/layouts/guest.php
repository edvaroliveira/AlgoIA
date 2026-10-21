<!DOCTYPE html>
<html lang="pt-br">

<?php $content = $content ?? ''; ?>

<head>
  <meta charset="UTF-8">
  <meta name="theme-color" content="#173f31">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= \Core\asset_url('/assets/img/favicon-32.png') ?>">
  <link rel="apple-touch-icon" href="<?= \Core\asset_url('/assets/img/apple-touch-icon.png') ?>">
  <title><?= \Core\View::e(\Core\View::documentTitle($pageTitle ?? null)) ?></title>
  <link rel="stylesheet" href="<?= \Core\asset_url('/assets/css/app.css') ?>">
</head>

<body class="guest-layout">
  <div class="guest-shell">
    <div class="guest-grid">
      <section class="guest-hero">
        <div class="guest-hero__badge">
          <?= \Core\View::logo('branco', 'guest-hero__logo', '(max-width: 767.98px) 14rem, 20rem', 'Logo AlgoIA') ?>
        </div>
        <h1 class="guest-hero__title">Aprendizagem, algoritmos e Amazônia.</h1>
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
          <?= \Core\View::logo('transp', 'brand-logo brand-logo--guest-card', '13rem', 'Logo AlgoIA') ?>
          <div>
            <p class="brand-sub">Acesso ao ambiente institucional</p>
          </div>
        </div>
        <div class="guest-card__body">
          <?= $content ?>
        </div>
      </section>
    </div>
  </div>

  <script src="<?= \Core\asset_url('/assets/js/app.js') ?>" defer></script>
</body>

</html>
