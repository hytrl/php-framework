<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

final class Input
{
    private ?string $rawBody = null;
    private ?array $json = null;
    private bool $jsonParsed = false;

    public function __construct(private Config $config)
    {
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $this->fetch($this->postData(), $key, $default);
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $this->fetch($_GET, $key, $default);
    }

    public function get_post(string $key, mixed $default = null): mixed
    {
        return $this->post($key) ?? $this->get($key, $default);
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $this->fetch($_COOKIE, $key, $default);
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $_SERVER[strtoupper($key)] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function is_ajax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? $default;
    }

    public function raw_body(): string
    {
        return $this->rawBody ??= (string) file_get_contents('php://input');
    }

    public function json(): ?array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $body = $this->raw_body();
            if ($body !== '') {
                try {
                    $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    $this->json = is_array($data) ? $data : null;
                } catch (JsonException) {
                    $this->json = null;
                }
            }
        }
        return $this->json;
    }

    public function ip_address(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $proxies = (array) $this->config->item('proxy_ips', []);
        if ($proxies !== [] && in_array($ip, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $candidate = $parts[$i];
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    if (!in_array($candidate, $proxies, true)) {
                        return $candidate;
                    }
                }
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    private function postData(): array
    {
        if ($_POST !== [] || !str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'json')) {
            return $_POST;
        }
        return $this->json() ?? [];
    }

    private function fetch(array $source, ?string $key, mixed $default): mixed
    {
        if ($key === null) {
            return $this->clean($source);
        }
        return isset($source[$key]) ? $this->clean($source[$key]) : $default;
    }

    private function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            $cleaned = [];
            foreach ($value as $k => $v) {
                $cleanKey = is_string($k) ? str_replace("\0", '', $k) : $k;
                $cleaned[$cleanKey] = $this->clean($v);
            }
            return $cleaned;
        }
        return is_string($value) ? str_replace("\0", '', $value) : $value;
    }
}
