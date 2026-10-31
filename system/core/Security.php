<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

final class Security
{
    private string $csrfTokenName;
    private string $csrfCookieName;
    private int $csrfExpire;
    private bool $csrfRegenerate;
    private bool $csrfProtection;
    /** @var string[] */
    private array $csrfExcludeUris;
    private ?string $csrfHash = null;

    public function __construct(private Config $config, private Input $input)
    {
        $this->csrfProtection  = (bool) $this->config->item('csrf_protection', true);
        $this->csrfTokenName   = (string) $this->config->item('csrf_token_name', 'csrf_token');
        $this->csrfCookieName  = (string) $this->config->item('csrf_cookie_name', 'csrf_cookie');
        $this->csrfExpire      = (int) $this->config->item('csrf_expire', 7200);
        $this->csrfRegenerate  = (bool) $this->config->item('csrf_regenerate', false);
        $this->csrfExcludeUris = (array) $this->config->item('csrf_exclude_uris', []);

        if ($this->csrfProtection) {
            $this->initCsrf();
        }
    }

    public function isCsrfEnabled(): bool
    {
        return $this->csrfProtection;
    }

    public function getCsrfTokenName(): string
    {
        return $this->csrfTokenName;
    }

    public function getCsrfHash(): string
    {
        return $this->csrfHash ??= $this->generateCsrfHash();
    }

    public function getCsrfField(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            html_escape($this->getCsrfTokenName()),
            html_escape($this->getCsrfHash())
        );
    }

    public function verifyCsrf(string $uri): bool
    {
        if (!$this->csrfProtection) {
            return true;
        }

        $method = $this->input->method();
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return true;
        }

        if ($this->isUriExcluded($uri)) {
            return true;
        }

        $token = $this->input->post($this->csrfTokenName)
            ?? ($this->input->json()[$this->csrfTokenName] ?? null)
            ?? $this->input->header('X-CSRF-TOKEN')
            ?? $this->input->header('X-XSRF-TOKEN');

        if (!is_string($token) || $token === '' || $this->csrfHash === null) {
            return false;
        }

        if (!hash_equals($this->csrfHash, $token)) {
            return false;
        }

        if ($this->csrfRegenerate) {
            $this->regenerateCsrf();
        }

        return true;
    }

    private function initCsrf(): void
    {
        $cookie = $this->input->cookie($this->csrfCookieName);
        if (is_string($cookie) && preg_match('/^[0-9a-f]{64}$/', $cookie)) {
            $this->csrfHash = $cookie;
        } else {
            $this->regenerateCsrf();
        }
    }

    private function regenerateCsrf(): void
    {
        $this->csrfHash = $this->generateCsrfHash();
        $this->setCsrfCookie();
    }

    private function generateCsrfHash(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function setCsrfCookie(): void
    {
        if (is_cli() || headers_sent()) {
            return;
        }

        $expire = $this->csrfExpire > 0 ? time() + $this->csrfExpire : 0;
        setcookie($this->csrfCookieName, (string) $this->csrfHash, [
            'expires'  => $expire,
            'path'     => '/',
            'domain'   => '',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function isUriExcluded(string $uri): bool
    {
        $uri = trim($uri, '/');
        foreach ($this->csrfExcludeUris as $excluded) {
            $pattern = trim((string) $excluded, '/');
            if ($pattern === '') {
                continue;
            }
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
            if (preg_match($regex, $uri)) {
                return true;
            }
        }
        return false;
    }
}
