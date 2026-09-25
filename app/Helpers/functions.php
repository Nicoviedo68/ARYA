<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
        if ($value === false) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require APP_PATH . '/Config/config.php';
        }
        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return BASE_PATH . '/public' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('is_loopback_host')) {
    /** Host local (localhost / 127.0.0.1 / ::1). */
    function is_loopback_host(?string $host): bool
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return false;
        }
        // quitar puerto
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_starts_with($host, '127.');
    }
}

if (!function_exists('is_loopback_url')) {
    function is_loopback_url(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_loopback_host(is_string($host) ? $host : null);
    }
}

if (!function_exists('app_request_base_url')) {
    /**
     * Base URL real del request actual (scheme + host + carpeta del script).
     * Nunca usa APP_URL del .env.
     */
    function app_request_base_url(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        $script = rtrim($script, '/');
        if ($script === '.' || $script === '\\') {
            $script = '';
        }

        return $scheme . '://' . $host . ($script === '' ? '' : $script);
    }
}

if (!function_exists('app_base_url')) {
    /**
     * Base pública de la app.
     * Si APP_URL apunta a localhost pero el request es producción → se ignora el .env
     * (evita redirecciones a http://localhost:8080 en Hostinger).
     */
    function app_base_url(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $fromRequest = rtrim(app_request_base_url(), '/');
        $configured = rtrim((string) config('app.url', ''), '/');

        if ($configured === '') {
            $base = $fromRequest;

            return $base;
        }

        // .env local subido a producción: NUNCA usar localhost aquí
        if (is_loopback_url($configured) && !is_loopback_url($fromRequest)) {
            $base = $fromRequest;

            return $base;
        }

        // Local en otro puerto (8081 vs APP_URL 8080): no saltar de servidor
        if (is_loopback_url($configured) && is_loopback_url($fromRequest)) {
            $base = $fromRequest;

            return $base;
        }

        $base = $configured;

        return $base;
    }
}

if (!function_exists('ocp_public_url')) {
    /**
     * URL del panel OCP. En host de producción siempre apunta a Hostinger,
     * aunque OCP_URL del .env diga localhost.
     */
    function ocp_public_url(): string
    {
        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if (!is_loopback_host($requestHost)) {
            return 'https://app.aisscol.com/ocp_Arya';
        }

        $configured = rtrim((string) config('ocp.url', ''), '/');

        return $configured !== '' ? $configured : 'http://localhost:8081';
    }
}

if (!function_exists('asset')) {
    /**
     * URL de assets relativa al document root actual (public/),
     * independiente de APP_URL — evita CSS/JS rotos en local vs producción.
     */
    function asset(string $path): string
    {
        static $assetBase = null;
        if ($assetBase === null) {
            $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            $dir = str_replace('\\', '/', dirname($script));
            $dir = rtrim($dir, '/');
            // php -S / index.php → dirname puede ser "\" o "/"
            if ($dir === '.' || $dir === '\\') {
                $dir = '';
            }
            $assetBase = $dir;
        }

        $rel = ltrim($path, '/');
        $url = $assetBase . '/assets/' . $rel;

        // Cache-busting por mtime del archivo (CSS/JS)
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (in_array($ext, ['css', 'js'], true) && defined('BASE_PATH')) {
            $file = BASE_PATH . '/public/assets/' . $rel;
            if (is_file($file)) {
                $url .= '?v=' . (int) filemtime($file);
            }
        }

        return $url;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $extraQuery = [];
        if (str_contains($path, '?')) {
            [$path, $qs] = explode('?', $path, 2);
            parse_str($qs, $extraQuery);
        }

        $path = ltrim($path, '/');
        $base = rtrim(app_base_url(), '/');

        // Hosting (Hostinger) sin rewrite: siempre index.php?r=...
        // En local se respeta APP_FORCE_INDEX_PHP del .env
        $forceIndex = filter_var(config('app.force_index_php', true), FILTER_VALIDATE_BOOLEAN);
        $requestHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if (!is_loopback_host($requestHost)) {
            $forceIndex = true;
        }

        if ($forceIndex) {
            $params = array_merge(['r' => '/' . $path], $extraQuery);
            if ($path === '') {
                $params['r'] = '/';
            }
            return $base . '/index.php?' . http_build_query($params);
        }

        $url = $base . ($path !== '' ? '/' . $path : '');
        if ($extraQuery) {
            $url .= '?' . http_build_query($extraQuery);
        }
        return $url;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        return \Arya\Core\Router::url($name, $params);
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = []): void
    {
        \Arya\Core\View::render($name, $data);
    }
}

if (!function_exists('redirect')) {
    /** @return never */
    function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}

if (!function_exists('auth')) {
    function auth(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('is_auth')) {
    function is_auth(): bool
    {
        return isset($_SESSION['user']);
    }
}

if (!function_exists('json_response')) {
    /** @return never */
    function json_response(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('money_cop')) {
    function money_cop(int|float $n): string
    {
        return '$' . number_format((float) $n, 0, ',', '.');
    }
}

if (!function_exists('data_mode')) {
    /**
     * live = PostgreSQL con tablas Arya · demo = mock / sin DB
     */
    function data_mode(): string
    {
        static $mode = null;
        if ($mode !== null) {
            return $mode;
        }

        if (!\Arya\Core\Database::connected()) {
            $mode = 'demo';
            return $mode;
        }

        $mode = \Arya\Models\User::tableExists() ? 'live' : 'demo';
        return $mode;
    }
}

if (!function_exists('is_live_data')) {
    function is_live_data(): bool
    {
        return data_mode() === 'live';
    }
}
