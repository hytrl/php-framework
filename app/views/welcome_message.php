<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0f172a;color:#e2e8f0;font:16px/1.6 system-ui,sans-serif}
        main{max-width:560px;padding:2rem}
        h1{margin:0 0 .5rem;font-size:2rem}
        code{background:#1e293b;padding:.15rem .4rem;border-radius:4px}
        .meta{margin-top:1.5rem;color:#94a3b8;font-size:.875rem}
    </style>
</head>
<body>
<main>
    <h1><?= e($title) ?></h1>
    <p>This page is rendered from <code>app/views/welcome_message.php</code><br>
       Controller: <code>app/controllers/Welcome.php</code></p>
    <div class="meta">
        Framework <?= e($version) ?> · PHP <?= e($php) ?> ·
        <?= elapsed_time() ?> sn · <?= memory_usage_mb() ?> MB
    </div>
</main>
</body>
</html>
