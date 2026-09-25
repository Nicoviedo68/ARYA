<?php

declare(strict_types=1);

namespace Arya\Core;

abstract class Controller
{
    protected function wantsPartial(): bool
    {
        $header = strtolower((string) ($_SERVER['HTTP_X_ARYA_PARTIAL'] ?? ''));
        if ($header === '1' || $header === 'true') {
            return true;
        }
        return isset($_GET['partial']) && (string) $_GET['partial'] === '1';
    }

    protected function view(string $name, array $data = [], ?string $layout = 'layouts/app'): void
    {
        if ($this->wantsPartial() && $layout === 'layouts/app') {
            $partial = View::renderPartial($name, $data);
            $this->json([
                'ok'         => true,
                'title'      => (string) ($data['title'] ?? ''),
                'active'     => (string) ($data['active'] ?? ''),
                'html'       => $partial['html'],
                'scripts'    => $partial['scripts'],
                'styles'     => $partial['styles'] ?? [],
                'needsChart' => $partial['needsChart'],
            ]);
        }

        View::render($name, $data, $layout);
    }

    /** @return never */
    protected function json(array $data, int $status = 200): void
    {
        json_response($data, $status);
    }

    protected function requireAuth(): void
    {
        if (!is_auth()) {
            if ($this->wantsPartial()) {
                $this->json(['ok' => false, 'message' => 'Sesión expirada.'], 401);
            }
            flash('error', 'Debes iniciar sesión para continuar.');
            redirect('login');
        }
    }

    protected function requireMenuAccess(string $codigo): void
    {
        $user = auth();
        $alias = strtoupper((string) ($user['role'] ?? 'AGENT'));
        if (!\Arya\Helpers\Menu::canAccess($alias, $codigo)) {
            if ($this->wantsJsonResponse()) {
                $this->json(['ok' => false, 'message' => 'No tienes permiso para este módulo.'], 403);
            }
            flash('error', 'No tienes permiso para este módulo.');
            redirect('dashboard');
        }
    }

    protected function wantsJsonResponse(): bool
    {
        if ($this->wantsPartial()) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xrw === 'xmlhttprequest' || $xrw === 'fetch';
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!verify_csrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            if ($this->wantsJsonResponse()) {
                $this->json(['ok' => false, 'message' => 'Token de seguridad inválido.'], 419);
            }
            flash('error', 'Token de seguridad inválido. Intenta de nuevo.');
            redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard');
        }
    }
}
