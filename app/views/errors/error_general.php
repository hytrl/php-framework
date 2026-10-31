<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?= html_escape($heading) ?></title>
<style>
body{font:16px/1.6 system-ui,sans-serif;max-width:900px;margin:8vh auto;padding:0 1rem;color:#334155}
h1{color:#b91c1c}pre{background:#f1f5f9;padding:1rem;overflow:auto;border-radius:6px;font-size:13px}
</style></head>
<body>
<h1><?= html_escape($heading) ?></h1>
<p><?= html_escape($message) ?></p>
<?php if (ENVIRONMENT === 'development' && $exception instanceof Throwable): ?>
    <p><strong><?= html_escape($exception->getFile()) ?></strong> : <?= (int) $exception->getLine() ?></p>
    <pre><?= html_escape($exception->getTraceAsString()) ?></pre>
<?php endif; ?>
</body></html>
