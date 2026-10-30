<?php
declare(strict_types=1);

final class Hooks
{
    /** @var array<string, mixed> */
    private array $hooks;

    public function __construct()
    {
        $this->hooks = include_config(APPPATH . 'config/hooks.php')['hook'] ?? [];
    }

    public function add(string $point, callable $fn): void
    {
        $current = $this->hooks[$point] ?? [];
        $this->hooks[$point] = is_callable($current) ? [$current, $fn] : [...(array) $current, $fn];
    }

    public function call(string $point): void
    {
        if (!isset($this->hooks[$point])) {
            return;
        }

        $hook = $this->hooks[$point];
        $list = is_callable($hook) ? [$hook] : (is_array($hook) ? $hook : []);

        foreach ($list as $fn) {
            if (is_callable($fn)) {
                $fn();
            }
        }
    }
}
