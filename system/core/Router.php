<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

final class Router
{
    private const RESERVED = ['default_controller'];

    private array $routes;
    private string $uri = '';
    /** @var string[] */
    private array $segments = [];

    public function __construct()
    {
        $this->routes = include_config(APPPATH . 'config/routes.php')['route'] ?? [];
    }

    public function uri_string(): string
    {
        return $this->uri;
    }

    public function segment(int $n, mixed $default = null): mixed
    {
        return $this->segments[$n - 1] ?? $default;
    }

    /**
     * @return array{file:string,class:string,method:string,args:string[]}|null
     */
    public function resolve(string $httpMethod): ?array
    {
        $uri = $this->detectUri();
        if ($uri === null) {
            return null;
        }
        $this->uri      = $uri;
        $this->segments = $uri === '' ? [] : explode('/', $uri);

        $default = (string) ($this->routes['default_controller'] ?? 'welcome');
        $target  = $uri === '' ? $default : $this->translate($uri, $httpMethod);

        $seg = array_values(array_filter(explode('/', trim($target, '/')), static fn ($s) => $s !== ''));
        $dir = APPPATH . 'controllers/';

        while (
            $seg !== [] && preg_match('/^[A-Za-z0-9_]+$/', $seg[0])
            && is_dir($dir . $seg[0]) && !is_file($dir . ucfirst($seg[0]) . '.php')
        ) {
            $dir .= array_shift($seg) . '/';
        }

        if ($seg === []) {
            $seg = [basename(explode('/', $default)[0])];
        }

        $class = ucfirst((string) array_shift($seg));
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $class) || !is_file($dir . $class . '.php')) {
            return null;
        }

        $method = (string) (array_shift($seg) ?? 'index');
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $method)) {   // "_" ile başlayanlar dışarıya kapalı
            return null;
        }

        return ['file' => $dir . $class . '.php', 'class' => $class, 'method' => $method, 'args' => $seg];
    }

    private function translate(string $uri, string $httpMethod): string
    {
        foreach ($this->routes as $pattern => $target) {
            if (in_array($pattern, self::RESERVED, true)) {
                continue;
            }
            if (is_array($target)) {
                $target = $target[$httpMethod] ?? $target['*'] ?? null;
                if ($target === null) {
                    continue;
                }
            }

            $regex = '#^' . str_replace(['(:num)', '(:any)'], ['([0-9]+)', '(.+)'], (string) $pattern) . '$#';
            if (preg_match($regex, $uri)) {
                return str_contains((string) $target, '$') ? (string) preg_replace($regex, (string) $target, $uri) : (string) $target;
            }
        }
        return $uri;
    }

    private function detectUri(): ?string
    {
        if (is_cli()) {
            $path = (string) ($_SERVER['argv'][1] ?? '');
        } else {
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

            $base = base_path();
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
            if ($path === '/index.php' || str_starts_with($path, '/index.php/')) {
                $path = substr($path, 10);
            }
        }

        $path = rawurldecode($path);
        if (str_contains($path, "\0")) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        $parts = array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
        foreach ($parts as $p) {
            if ($p === '.' || $p === '..') {
                return null;
            }
        }
        return implode('/', $parts);
    }
}
