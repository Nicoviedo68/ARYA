<?php /** @var string $content */ /** @var string $title */ ?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#05080f">
  <meta name="color-scheme" content="dark">
  <title><?= e($title ?? 'Login') ?> — Arya CRM</title>
  <?php /* Login siempre oscuro (no hereda arya.theme del CRM) */ ?>
  <script>document.documentElement.setAttribute('data-theme', 'dark');</script>
  <link rel="icon" href="<?= asset('img/favicon.ico') ?>" type="image/x-icon" sizes="any">
  <link rel="icon" href="<?= asset('img/arya-logo.png') ?>" type="image/png" sizes="32x32">
  <link rel="apple-touch-icon" href="<?= asset('img/arya-logo.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Sora:wght@600;700&display=swap" rel="stylesheet">
  <link rel="preload" as="image" href="<?= asset('img/arya-logo.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=theme5">
  <link rel="stylesheet" href="<?= asset('css/ocp-modal.css') ?>?v=ocp8">
</head>
<body class="auth-body" data-auth-fixed-theme="dark">
  <div class="auth-atmosphere" aria-hidden="true"></div>
  <div class="auth-page">
    <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success auth-toast" data-auto-dismiss><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
      <div class="alert alert-error auth-toast" data-auto-dismiss><?= e($msg) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </div>
  <script src="<?= asset('js/app.js') ?>?v=theme5" defer></script>
</body>
</html>
