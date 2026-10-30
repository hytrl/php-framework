<?php
declare(strict_types=1);

if (!function_exists('base_url')) {
    function base_url(string $uri = ''): string
    {
        return Framework::core('config')->base_url($uri);
    }
}

if (!function_exists('site_url')) {
    function site_url(string $uri = ''): string
    {
        return Framework::core('config')->site_url($uri);
    }
}

if (!function_exists('current_url')) {
    function current_url(): string
    {
        return site_url(Framework::core('router')->uri_string());
    }
}

if (!function_exists('uri_segment')) {
    function uri_segment(int $n, mixed $default = null): mixed
    {
        return Framework::core('router')->segment($n, $default);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $uri = '', int $code = 302): never
    {
        $url = preg_match('#^https?://#i', $uri) ? $uri : site_url($uri);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . str_replace(["\r", "\n"], '', $url), true, $code);
        exit;
    }
}
