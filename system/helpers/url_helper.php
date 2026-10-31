<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

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
    function redirect(string $uri = '', int $code = 302, bool $allowExternal = false): never
    {
        $uri = str_replace(["\r", "\n", "\0"], '', trim($uri));

        // Disallow dangerous protocol-relative URLs
        if (str_starts_with($uri, '//') || str_starts_with($uri, '\\\\') || str_starts_with($uri, '/\\')) {
            $uri = '/' . ltrim($uri, '/\\');
        }

        if (preg_match('#^https?://#i', $uri)) {
            if (!$allowExternal) {
                $baseHost   = parse_url(base_url(), PHP_URL_HOST);
                $targetHost = parse_url($uri, PHP_URL_HOST);
                if (strcasecmp((string) $baseHost, (string) $targetHost) !== 0) {
                    $uri = base_url();
                }
            }
            $url = $uri;
        } else {
            $url = site_url($uri);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $url, true, $code);
        exit;
    }
}
