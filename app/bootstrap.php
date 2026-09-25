<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require_once APP_PATH . '/Helpers/functions.php';
require_once APP_PATH . '/Core/Env.php';
require_once APP_PATH . '/Core/Router.php';
require_once APP_PATH . '/Core/View.php';
require_once APP_PATH . '/Core/Controller.php';
require_once APP_PATH . '/Core/Database.php';

// Autoload PSR-4 simple (app/)
spl_autoload_register(function (string $class): void {
    $prefix = 'Arya\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    // Módulo gen_redes (carpeta raíz /gen_redes)
    $genPrefix = 'Arya\\GenRedes\\';
    if (str_starts_with($class, $genPrefix)) {
        $relative = substr($class, strlen($genPrefix));
        $file = BASE_PATH . '/gen_redes/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

\Arya\Core\Env::load(BASE_PATH . '/.env');

if (filter_var(env('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

date_default_timezone_set(config('app.timezone', 'America/Bogota'));

if (session_status() === PHP_SESSION_NONE) {
    session_name(config('session.name', 'arya_session'));

    $cookiePath = '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim($scriptDir, '/');
    if ($scriptDir !== '' && $scriptDir !== '/') {
        $cookiePath = $scriptDir;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start([
        'use_strict_mode' => true,
    ]);
}

$router = new \Arya\Core\Router();
require BASE_PATH . '/routes/web.php';

return $router;
