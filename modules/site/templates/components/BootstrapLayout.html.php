<?php /** @var \App\Site\Component\BootstrapLayout $bootstrapLayout */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $bootstrapLayout->name ?></title>
    <meta name="tessera-layout" content="lotline-bootstrap">
    <meta name="tessera-csrf-token" content="<?= $bootstrapLayout->csrfToken ?>">
    <meta name="tessera-csrf-header" content="X-AIC-CSRF">
    <?= $bootstrapLayout->trackedAssets ?>
</head>
<body>
    <main class="page-shell">
        <?= $bootstrapLayout->content ?>
    </main>
</body>
</html>
