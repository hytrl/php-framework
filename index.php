<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 or newer is required.');
}

define('ENVIRONMENT', getenv('APP_ENV') ?: 'development');

define('FRAMEWORK_VERSION', '1.0.0');
define('START_TIME', microtime(true));
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('APPPATH', FCPATH . 'app' . DIRECTORY_SEPARATOR);
define('BASEPATH', FCPATH . 'system' . DIRECTORY_SEPARATOR);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require BASEPATH . 'core/Framework.php';

Framework::run();
