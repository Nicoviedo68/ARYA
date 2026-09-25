<?php

declare(strict_types=1);

namespace Arya\GenRedes;

/**
 * Construye la URL pública de un media asset para Instagram / n8n.
 */
final class MediaUrl
{
    /**
     * URL directa vía public/gen_redes/media.php (más estable para Graph API).
     */
    public static function forId(int $id): string
    {
        $id = max(0, $id);
        $base = rtrim(self::publicBaseUrl(), '/');

        return $base . '/gen_redes/media.php?id=' . $id;
    }

    /**
     * URL estática del archivo en public/ (ideal Instagram).
     * Ej: gen_redes/files/12.png
     */
    public static function forStaticFile(string $relativePath): string
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $base = rtrim(self::publicBaseUrl(), '/');

        return $base . '/' . $relativePath;
    }

    /**
     * URL alternativa por router Arya: /gen-redes/media/{id}
     */
    public static function forIdViaRouter(int $id): string
    {
        $id = max(0, $id);
        if (function_exists('url')) {
            return url('gen-redes/media/' . $id);
        }

        return self::forId($id);
    }

    private static function publicBaseUrl(): string
    {
        $configured = '';
        if (function_exists('config')) {
            $configured = rtrim((string) config('app.url', ''), '/');
        }

        if ($configured !== '') {
            // app.url suele apuntar a .../Arya/public
            return $configured;
        }

        if (function_exists('app_base_url')) {
            return rtrim(app_base_url(), '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

        // Si estamos en /public/gen_redes/media.php → subir un nivel
        if (str_ends_with($dir, '/gen_redes')) {
            $dir = substr($dir, 0, -strlen('/gen_redes')) ?: '';
        }

        return $scheme . '://' . $host . $dir;
    }
}
