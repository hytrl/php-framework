<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return html_escape($value);
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return base_url($path);
    }
}
