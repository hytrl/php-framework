<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

final class Config
{
    private array $items;

    public function __construct()
    {
        $this->items = (include_config(APPPATH . 'config/config.php')['config'] ?? [])
            + ['base_url' => '', 'index_page' => '', 'charset' => 'UTF-8', 'timezone' => 'UTC'];

        if ((string) $this->items['base_url'] === '') {
            $this->items['base_url'] = $this->detectBaseUrl();
        }
        $this->items['base_url'] = rtrim((string) $this->items['base_url'], '/') . '/';
    }

    public function item(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    public function load(string $file): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $file)) {
            throw new InvalidArgumentException("Invalid config file name: {$file}");
        }
        $path = APPPATH . 'config/' . $file . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("Config file not found: {$file}");
        }
        $this->items = (include_config($path)['config'] ?? []) + $this->items;
    }

    public function base_url(string $uri = ''): string
    {
        return $this->items['base_url'] . ltrim($uri, '/');
    }

    public function site_url(string $uri = ''): string
    {
        $index = (string) $this->items['index_page'];
        return $this->items['base_url'] . ($index !== '' ? trim($index, '/') . '/' : '') . ltrim($uri, '/');
    }

    private function detectBaseUrl(): string
    {
        if (is_cli()) {
            return 'http://localhost/';
        }
        $rawHost = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $rawHost)) {
            $rawHost = 'localhost';
        }

        $allowed = (array) ($this->items['allowed_hosts'] ?? []);
        if ($allowed !== []) {
            $hostWithoutPort = strtolower((string) preg_replace('/:\d+$/', '', $rawHost));
            $isAllowed = false;
            foreach ($allowed as $pattern) {
                $pattern = strtolower((string) $pattern);
                if ($pattern === $hostWithoutPort) {
                    $isAllowed = true;
                    break;
                }
                if (str_starts_with($pattern, '*.') && str_ends_with($hostWithoutPort, substr($pattern, 1))) {
                    $isAllowed = true;
                    break;
                }
            }
            if (!$isAllowed) {
                $rawHost = (string) reset($allowed);
            }
        }

        return (is_https() ? 'https' : 'http') . '://' . $rawHost . base_path() . '/';
    }
}
