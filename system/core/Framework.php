<?php
declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'core/Config.php';
require_once BASEPATH . 'core/Hooks.php';
require_once BASEPATH . 'core/Input.php';
require_once BASEPATH . 'core/Output.php';
require_once BASEPATH . 'core/Security.php';
require_once BASEPATH . 'core/Router.php';
require_once BASEPATH . 'core/Loader.php';
require_once BASEPATH . 'core/BaseController.php';
require_once BASEPATH . 'core/BaseModel.php';
require_once BASEPATH . 'database/DB.php';

final class Framework
{
    /** @var array<string, object> */
    private static array $core = [];

    public static function core(string $name): object
    {
        return self::$core[$name] ?? throw new LogicException("Core component '{$name}' is not available.");
    }

    public static function peek(string $name): ?object
    {
        return self::$core[$name] ?? null;
    }

    public static function run(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        ob_start();

        $config   = new Config();
        date_default_timezone_set((string) $config->item('timezone', 'UTC'));

        $hooks    = new Hooks();
        $input    = new Input($config);
        $output   = new Output($config);
        $security = new Security($config, $input);
        $router   = new Router();

        self::$core = compact('config', 'hooks', 'input', 'output', 'security', 'router');

        $hooks->call('pre_system');

        $target = $router->resolve($input->method());
        if ($target === null) {
            show_404();
        }

        if (!$security->verifyCsrf($router->uri_string())) {
            self::fail(403, '403 Forbidden', 'The action you requested is not allowed.');
        }

        $hooks->call('pre_controller');

        require_once $target['file'];

        $class  = $target['class'];
        $method = $target['method'];

        if (!class_exists($class, false) || !is_subclass_of($class, BaseController::class) || !method_exists($class, $method)) {
            show_404();
        }

        $ref = new ReflectionMethod($class, $method);
        $argc = count($target['args']);
        if (
            !$ref->isPublic() || $ref->isStatic() || $ref->isAbstract()
            || $ref->isConstructor() || $ref->isDestructor() || str_starts_with($method, '__')
            || $ref->getDeclaringClass()->getName() === BaseController::class
            || $argc < $ref->getNumberOfRequiredParameters()
            || ($argc > $ref->getNumberOfParameters() && !$ref->isVariadic())
        ) {
            show_404();
        }

        foreach ($ref->getParameters() as $i => $param) {
            $type = $param->getType();
            if (isset($target['args'][$i]) && $type instanceof ReflectionNamedType
                && in_array($type->getName(), ['int', 'float'], true) && !is_numeric($target['args'][$i])) {
                show_404();
            }
        }

        $controller = new $class();
        $hooks->call('post_controller_constructor');

        $controller->$method(...$target['args']);

        $hooks->call('post_controller');

        $output->send((string) ob_get_clean());

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $hooks->call('post_system');
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        if (ENVIRONMENT !== 'development') {
            log_message('error', "PHP [{$severity}] {$message} in {$file}:{$line}");
            return true;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        log_message('error', get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

        $dev = ENVIRONMENT === 'development';
        self::fail(
            500,
            $dev ? get_class($e) : 'An error was encountered',
            $dev ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
            $dev ? $e : null
        );
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            log_message('error', "Fatal: {$err['message']} in {$err['file']}:{$err['line']}");
            $dev = ENVIRONMENT === 'development';
            self::fail(500, 'Fatal error', $dev ? $err['message'] . ' in ' . $err['file'] . ':' . $err['line'] : 'An unexpected error occurred.');
        }
    }

    public static function fail(int $status, string $heading, string $message, ?Throwable $exception = null): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (is_cli()) {
            fwrite(STDERR, "[{$status}] {$heading}: {$message}\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $tpl = APPPATH . 'views/errors/' . ($status === 404 ? 'error_404.php' : 'error_general.php');
        if (is_file($tpl)) {
            (static function (string $__tpl, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                include $__tpl;
            })($tpl, ['status' => $status, 'heading' => $heading, 'message' => $message, 'exception' => $exception]);
        } else {
            echo '<h1>' . html_escape($heading) . '</h1><p>' . html_escape($message) . '</p>';
        }

        exit(1);
    }
}
