<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

final class Loader
{
    private const RESERVED = ['load', 'input', 'output', 'config', 'db', 'security'];

    private array $vars = [];
    private array $libraries = [];
    private array $models = [];
    private array $helpers = [];
    private array $databases = [];
    private ?array $dbConfig = null;

    public function autoload(): void
    {
        $auto = include_config(APPPATH . 'config/autoload.php')['autoload'] ?? [];

        foreach ($auto['config'] ?? [] as $file) {
            Framework::core('config')->load($file);
        }
        if (!empty($auto['helpers'])) {
            $this->helper($auto['helpers']);
        }
        if (!empty($auto['libraries'])) {
            $this->library($auto['libraries']);
        }
        if (!empty($auto['models'])) {
            $this->model($auto['models']);
        }
    }

    public function vars(array|string $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->vars = $key + $this->vars;
        } else {
            $this->vars[$key] = $value;
        }
        return $this;
    }

    public function view(string $view, array $data = [], bool $return = false): string|static
    {
        $this->assertPath($view);
        $name = ltrim(str_replace('\\', '/', $view), '/');
        $file = APPPATH . 'views/' . $name . (str_ends_with($name, '.php') ? '' : '.php');

        if (!is_file($file)) {
            throw new RuntimeException("Unable to load the requested view: {$view}");
        }

        $html = $this->render($file, $data + $this->vars);

        if ($return) {
            return $html;
        }
        echo $html;
        return $this;
    }

    private function render(string $file, array $data): string
    {
        $renderer = function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                include $__file;
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        };

        $ci = get_instance();
        if ($ci !== null) {
            $renderer = Closure::bind($renderer, $ci, BaseController::class);
        }
        return $renderer($file, $data);
    }

    public function model(string|array $model, ?string $alias = null): static
    {
        if (is_array($model)) {
            foreach ($model as $m) {
                $this->model($m);
            }
            return $this;
        }

        $this->assertName($model);
        $class = basename($model);
        $prop  = $alias ?? strtolower($class);

        if (isset($this->models[$prop])) {
            return $this;
        }

        $path = APPPATH . 'models/' . $model . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("Unable to locate the model: {$model}");
        }
        require_once $path;

        if (!class_exists($class, false) || !is_subclass_of($class, BaseModel::class)) {
            throw new RuntimeException("Model class {$class} must exist and extend BaseModel.");
        }

        $this->assign($prop, new $class());
        $this->models[$prop] = true;
        return $this;
    }

    public function library(string|array $library, array $params = [], ?string $alias = null): static
    {
        if (is_array($library)) {
            foreach ($library as $l) {
                $this->library($l, $params);
            }
            return $this;
        }

        if ($library === 'database') {
            $this->database();
            return $this;
        }

        $this->assertName($library);
        $class = basename($library);
        $prop  = $alias ?? strtolower($class);

        if (isset($this->libraries[$prop])) {
            return $this;
        }

        $path = null;
        foreach ([APPPATH, BASEPATH] as $base) {
            if (is_file($base . 'libraries/' . $library . '.php')) {
                $path = $base . 'libraries/' . $library . '.php';
                break;
            }
        }
        if ($path === null) {
            throw new RuntimeException("Unable to locate the library: {$library}");
        }
        require_once $path;

        if (!class_exists($class, false)) {
            throw new RuntimeException("Library file found but class {$class} is not defined.");
        }

        $this->assign($prop, $params === [] ? new $class() : new $class($params));
        $this->libraries[$prop] = true;
        return $this;
    }

    public function helper(string|array $helpers): static
    {
        foreach ((array) $helpers as $helper) {
            $helper = strtolower(preg_replace('/_helper$/i', '', (string) $helper));
            $this->assertName($helper);

            if (isset($this->helpers[$helper])) {
                continue;
            }

            $found = false;
            foreach ([APPPATH, BASEPATH] as $base) {
                $path = $base . 'helpers/' . $helper . '_helper.php';
                if (is_file($path)) {
                    require_once $path;
                    $found = true;
                }
            }
            if (!$found) {
                throw new RuntimeException("Unable to load the helper: {$helper}");
            }
            $this->helpers[$helper] = true;
        }
        return $this;
    }

    public function database(string $group = '', bool $return = false): DB
    {
        if ($this->dbConfig === null) {
            $this->dbConfig = include_config(APPPATH . 'config/database.php')['db'] ?? [];
        }

        $group = $group !== '' ? $group : (string) ($this->dbConfig['active_group'] ?? 'default');
        if (!isset($this->dbConfig[$group]) || !is_array($this->dbConfig[$group])) {
            throw new RuntimeException("Database group '{$group}' is not defined in config/database.php.");
        }

        $db = $this->databases[$group] ??= new DB($this->dbConfig[$group]);

        if (!$return) {
            get_instance()->db = $db;
        }
        return $db;
    }

    private function assign(string $prop, object $object): void
    {
        if (in_array($prop, self::RESERVED, true)) {
            throw new RuntimeException("'{$prop}' is a reserved name; use an alias.");
        }
        get_instance()->$prop = $object;
    }

    private function assertName(string $name): void
    {
        if (!preg_match('#^[A-Za-z0-9_]+(/[A-Za-z0-9_]+)*$#', $name)) {
            throw new InvalidArgumentException("Invalid name: {$name}");
        }
    }

    private function assertPath(string $path): void
    {
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, "\0") || preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException('Invalid path.');
        }
    }
}
