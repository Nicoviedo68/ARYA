<?php

declare(strict_types=1);

namespace Arya\Core;

final class View
{
    public static function render(string $name, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$name}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = APP_PATH . '/Views/' . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }

    /**
     * Renderiza solo el contenido de la vista (sin layout) y extrae scripts/CSS.
     *
     * @return array{html:string,scripts:list<array{type:string,src?:string,code?:string}>,styles:list<string>,needsChart:bool}
     */
    public static function renderPartial(string $name, array $data = []): array
    {
        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$name}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $html = (string) ob_get_clean();

        $styles = [];
        $html = (string) preg_replace_callback(
            '/<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*>/i',
            static function (array $m) use (&$styles): string {
                if (preg_match('/\bhref\s*=\s*["\']([^"\']+)["\']/i', $m[0], $hm)) {
                    $href = trim($hm[1]);
                    if ($href !== '') {
                        $styles[] = $href;
                    }
                }
                return '';
            },
            $html
        );

        $scripts = [];
        $html = (string) preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            static function (array $m) use (&$scripts): string {
                $attrs = $m[1];
                $body = trim($m[2]);
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $sm)) {
                    $scripts[] = ['type' => 'src', 'src' => $sm[1]];
                } elseif ($body !== '') {
                    $scripts[] = ['type' => 'inline', 'code' => $body];
                }
                return '';
            },
            $html
        );

        if (!empty($chartsScript) && is_string($chartsScript)) {
            $scripts[] = ['type' => 'inline', 'code' => $chartsScript];
        }

        $active = (string) ($data['active'] ?? '');
        $needsChart = in_array($active, ['dashboard', 'reports'], true);

        return [
            'html'       => $html,
            'scripts'    => $scripts,
            'styles'     => array_values(array_unique($styles)),
            'needsChart' => $needsChart,
        ];
    }

    public static function partial(string $name, array $data = []): void
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($file)) {
            return;
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}
