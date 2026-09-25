<?php

declare(strict_types=1);

namespace Arya\Middleware;

use Arya\Models\User;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!is_auth()) {
            $this->denyUnauthenticated();
        }

        $user = auth();
        $id = (int) ($user['id'] ?? 0);
        if ($id < 1) {
            return;
        }

        // Revisar cada ~12s si el admin deshabilitó la cuenta (habilitado=FALSE)
        $last = (int) ($_SESSION['habilitado_checked_at'] ?? 0);
        if ((time() - $last) < 12) {
            return;
        }
        $_SESSION['habilitado_checked_at'] = time();

        if (!User::tableExists()) {
            return;
        }

        if (User::isHabilitado($id)) {
            return;
        }

        // Cuenta desactivada en OCP → cerrar sesión CRM de inmediato
        User::logoutSession($id);
        unset(
            $_SESSION['user'],
            $_SESSION['online_touched_at'],
            $_SESSION['profile_synced_at'],
            $_SESSION['habilitado_checked_at']
        );
        session_regenerate_id(true);

        $partial = strtolower((string) ($_SERVER['HTTP_X_ARYA_PARTIAL'] ?? ''));
        $wantsPartial = $partial === '1' || $partial === 'true'
            || (isset($_GET['partial']) && (string) $_GET['partial'] === '1');

        if ($wantsPartial) {
            json_response(['ok' => false, 'message' => 'Tu cuenta fue desactivada. Sesión cerrada.'], 401);
        }

        flash('error', 'Tu cuenta fue desactivada. Contacta al administrador.');
        redirect('login');
    }

    private function denyUnauthenticated(): void
    {
        $partial = strtolower((string) ($_SERVER['HTTP_X_ARYA_PARTIAL'] ?? ''));
        $wantsPartial = $partial === '1' || $partial === 'true'
            || (isset($_GET['partial']) && (string) $_GET['partial'] === '1');

        if ($wantsPartial) {
            json_response(['ok' => false, 'message' => 'Sesión expirada.'], 401);
        }

        flash('error', 'Debes iniciar sesión para continuar.');
        redirect('login');
    }
}
