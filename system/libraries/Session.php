<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

class Session
{
    private const FLASH = '__flash';
    private const INTERNAL_KEYS = [self::FLASH => true, '__last_activity' => true, '__last_regenerate' => true];

    public function __construct(array $params = [])
    {
        $config   = Framework::core('config');
        $lifetime = (int) ($params['expiration'] ?? $config->item('session_expiration', 7200));

        if (!is_cli() && session_status() !== PHP_SESSION_ACTIVE) {
            session_name((string) ($params['name'] ?? $config->item('session_name', 'fw_session')));
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start([
                'use_strict_mode' => 1,
                'use_only_cookies' => 1,
                'gc_maxlifetime'  => $lifetime,
            ]);
        }

        $_SESSION ??= [];

        $now = time();
        if (isset($_SESSION['__last_activity']) && $lifetime > 0 && ($now - (int) $_SESSION['__last_activity']) > $lifetime) {
            $this->destroy();
            $_SESSION = [];
        }
        $_SESSION['__last_activity'] = $now;

        $timeToUpdate = (int) $config->item('session_time_to_update', 300);
        if ($timeToUpdate > 0 && session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['__last_regenerate'])) {
                $_SESSION['__last_regenerate'] = $now;
            } elseif (($now - (int) $_SESSION['__last_regenerate']) > $timeToUpdate) {
                $this->regenerate(true);
                $_SESSION['__last_regenerate'] = $now;
            }
        }

        $flash = $_SESSION[self::FLASH] ?? ['new' => [], 'old' => []];
        $_SESSION[self::FLASH] = ['new' => [], 'old' => $flash['new'] ?? []];
    }

    public function set_userdata(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            $_SESSION[$k] = $v;
        }
    }

    public function userdata(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return array_diff_key($_SESSION, self::INTERNAL_KEYS);
        }
        return $_SESSION[$key] ?? $default;
    }

    public function has_userdata(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function unset_userdata(string|array $key): void
    {
        foreach ((array) $key as $k) {
            unset($_SESSION[$k]);
        }
    }

    public function set_flashdata(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH]['new'][$key] = $value;
    }

    public function flashdata(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH]['old'][$key] ?? $default;
    }

    public function regenerate(bool $destroyOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOld);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
                    'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    public function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
