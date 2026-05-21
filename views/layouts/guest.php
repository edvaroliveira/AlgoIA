<!DOCTYPE html>
<html lang="pt-br">

<?php $content = $content ?? ''; ?>

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
  <div class="guest-shell container-xxl py-4 py-lg-5">
    <div class="guest-grid">
      <section class="guest-hero">
        <div class="guest-hero__badge">Ambiente acadêmico orientado por IA</div>
        <h1 class="guest-hero__title">Avaliação, prática e acompanhamento com uma linguagem visual mais madura e institucional.</h1>
        <p class="guest-hero__copy">O IAProg conecta turmas, exercícios e correção assistida com uma experiência inspirada em plataformas de inovação aplicada.</p>

        <div class="guest-feature-list">
          <div class="guest-feature">
            <strong>Correção orientada</strong>
            <span>Feedback técnico, nota e resposta esperada quando liberada.</span>
          </div>
          <div class="guest-feature">
            <strong>Fluxo docente seguro</strong>
            <span>Gestão de turmas, exclusões e registros com trilha de auditoria.</span>
          </div>
          <div class="guest-feature">
            <strong>Foco em aprendizagem</strong>
            <span>Prática de algoritmos com uma interface limpa e hierarquia visual forte.</span>
          </div>
        </div>
      </section>

      <section class="guest-card">
        <div class="guest-brand">
          <span class="brand-mark">IA</span>
          <div>
            <h2 class="brand-name">IAProg</h2>
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
