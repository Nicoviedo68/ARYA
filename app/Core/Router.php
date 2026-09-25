<?php

declare(strict_types=1);

namespace Arya\Core;

final class Router
{
    /** @var array<int, array{method:string, path:string, handler:mixed, name:?string, middleware:array}> */
    private array $routes = [];

    /** @var array<string, string> */
    private static array $named = [];

    public function get(string $path, mixed $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $name, $middleware);
    }

    public function post(string $path, mixed $handler, ?string $name = null, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $name, $middleware);
    }

    public function add(string $method, string $path, mixed $handler, ?string $name = null, array $middleware = []): self
    {
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $this->routes[] = compact('method', 'path', 'handler', 'name', 'middleware');
        if ($name) {
            self::$named[$name] = $path;
        }
        return $this;
    }

    /**
     * Resuelve la ruta de forma compatible con hosting compartido / subcarpetas.
     */
    public static function resolveRequestUri(): string
    {
        // 1) Query string (más compatible): index.php?r=/dashboard
        if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
            return self::normalizePath($_GET['r']);
        }

        // Alias: ?route= / ?path=
        foreach (['route', 'path', 'uri'] as $key) {
            if (isset($_GET[$key]) && is_string($_GET[$key]) && $_GET[$key] !== '') {
                return self::normalizePath($_GET[$key]);
            }
        }

        // 2) PATH_INFO: /index.php/dashboard
        foreach (['PATH_INFO', 'ORIG_PATH_INFO'] as $key) {
            $pathInfo = $_SERVER[$key] ?? '';
            if (is_string($pathInfo) && $pathInfo !== '' && $pathInfo !== '/') {
                // Algunos hosts meten basura; limpiar index.php
                $pathInfo = preg_replace('#^.*?index\.php#i', '', $pathInfo) ?? $pathInfo;
                return self::normalizePath($pathInfo);
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        // Quitar /index.php/... del path
        if (preg_match('#/index\.php(?:/(.*))?$#i', $uri, $m)) {
            return self::normalizePath($m[1] ?? '/');
        }
        if (preg_match('#index\.php(?:/(.*))?$#i', $uri, $m)) {
            return self::normalizePath($m[1] ?? '/');
        }

        // Quitar base path de APP_URL (ej: /Arya/public)
        $basePath = self::appUrlPath();
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        // Quitar directorio del script
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim($scriptDir, '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir)) ?: '/';
        }

        // Fallbacks conocidos
        foreach (['/Arya/public', '/arya/public'] as $prefix) {
            if (str_starts_with($uri, $prefix)) {
                $uri = substr($uri, strlen($prefix)) ?: '/';
                break;
            }
        }

        return self::normalizePath($uri);
    }

    private static function appUrlPath(): string
    {
        $configured = (string) config('app.url', '');
        if ($configured === '') {
            return '';
        }
        $path = parse_url($configured, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }
        return rtrim($path, '/');
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = self::normalizePath($uri);

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middleware'] as $mw) {
                $class = "Arya\\Middleware\\{$mw}";
                if (class_exists($class)) {
                    (new $class())->handle();
                }
            }

            $this->invoke($route['handler'], $params);
            return;
        }

        http_response_code(404);
        View::render('errors/404', [
            'title'        => 'Página no encontrada',
            'resolvedUri'  => $uri,
            'requestUri'   => $_SERVER['REQUEST_URI'] ?? '',
            'scriptName'   => $_SERVER['SCRIPT_NAME'] ?? '',
            'pathInfo'     => $_SERVER['PATH_INFO'] ?? '',
            'queryR'       => $_GET['r'] ?? '',
            'debug'        => (bool) config('app.debug', false),
        ]);
    }

    private function invoke(mixed $handler, array $params): void
    {
        if (is_callable($handler)) {
            $handler(...array_values($params));
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $method] = explode('@', $handler, 2);
            $class = "Arya\\Controllers\\{$controller}";
            if (!class_exists($class)) {
                throw new \RuntimeException("Controlador no encontrado: {$class}");
            }
            $instance = new $class();
            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("Método no encontrado: {$class}@{$method}");
            }
            $instance->{$method}(...array_values($params));
            return;
        }

        throw new \RuntimeException('Handler de ruta inválido');
    }

    public static function url(string $name, array $params = []): string
    {
        $path = self::$named[$name] ?? '/' . ltrim($name, '/');
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }
        return url(ltrim($path, '/'));
    }
}
