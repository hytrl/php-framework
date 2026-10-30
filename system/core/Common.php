<?php
declare(strict_types=1);

function get_instance(): ?BaseController
{
    return BaseController::instance();
}

function config_item(string $key, mixed $default = null): mixed
{
    return Framework::core('config')->item($key, $default);
}

function include_config(string $__file): array
{
    if (!is_file($__file)) {
        return [];
    }
    include $__file;
    unset($__file);
    return get_defined_vars();
}

function is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (basename($script) !== 'index.php') {
        return '';
    }
    return rtrim(dirname($script), '/');
}

function html_escape(mixed $value): mixed
{
    if (is_array($value)) {
        return array_map('html_escape', $value);
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function show_404(string $message = 'The page you requested was not found.'): never
{
    Framework::fail(404, '404 Page Not Found', $message);
}

function show_error(string $message, int $status = 500, string $heading = 'An error was encountered'): never
{
    Framework::fail($status, $heading, $message);
}

function elapsed_time(): string
{
    return number_format(microtime(true) - START_TIME, 4);
}

function memory_usage_mb(): string
{
    return number_format(memory_get_peak_usage(true) / 1048576, 2);
}

function log_message(string $level, string $message): void
{
    static $levels = ['error' => 1, 'info' => 2, 'debug' => 3];

    $config    = Framework::peek('config');
    $threshold = $config !== null ? (int) $config->item('log_threshold', 1) : 1;
    if ($threshold < 1 || ($levels[$level] ?? 1) > $threshold) {
        return;
    }

    $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    @file_put_contents(APPPATH . 'logs/log-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
}
