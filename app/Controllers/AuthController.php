<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Helpers\MockData;
use Arya\Models\User;
use Arya\Services\OcpTwoFactor;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (is_auth()) {
            redirect('dashboard');
        }

        $ocpStep = OcpTwoFactor::step();
        $ocpPending = OcpTwoFactor::pendingUser();
        $ocpChannels = OcpTwoFactor::channelOptions();
        $handoff = $_SESSION['ocp_handoff'] ?? null;
        unset($_SESSION['ocp_handoff']);

        if ($ocpStep === 'none') {
            $ocpStep = 'credentials';
        }

        $this->view('auth/login', [
            'title'        => 'Iniciar sesión',
            'ocpUrl'       => ocp_public_url(),
            'ocpLabel'     => (string) config('ocp.label', 'OCP Arya'),
            'ocpStep'      => $ocpStep,
            'ocpPending'   => $ocpPending,
            'ocpChannels'  => $ocpChannels,
            'ocpHandoff'   => is_array($handoff) ? $handoff : null,
            'openOcp'      => $ocpStep !== 'credentials' || is_array($handoff) || (($_GET['ocp'] ?? '') === '1'),
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->validateCsrf();

        $login    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (User::tableExists()) {
            $result = User::login($login, $password);

            if ($result['ok']) {
                session_regenerate_id(true);
                $_SESSION['user'] = $result['user'];
                $_SESSION['online_touched_at'] = time();
                $_SESSION['profile_synced_at'] = time();
                flash('success', 'Bienvenido a Arya.');
                redirect('dashboard');
            }

            $_SESSION['_old'] = ['email' => $login];

            $message = match ($result['error']) {
                'busy'     => 'Ya hay una sesión abierta con este usuario. Ciérrala en el otro equipo o espera.',
                'disabled' => 'Esta cuenta está desactivada. Contacta al administrador.',
                'db'       => 'No se pudo validar contra la base de datos. Intenta de nuevo.',
                default    => 'Credenciales incorrectas.',
            };

            flash('error', $message);
            redirect('login');
        }

        if (strcasecmp($login, 'nico') === 0 && $password === 'Bluerain1992') {
            session_regenerate_id(true);
            $_SESSION['user'] = MockData::user();
            flash('success', 'Bienvenido a Arya (modo demo).');
            redirect('dashboard');
        }

        $_SESSION['_old'] = ['email' => $login];
        flash('error', 'Credenciales incorrectas.');
        redirect('login');
    }

    /** Paso 1 OCP: validar ADMIN y mostrar elección de canal */
    public function ocpChallenge(): void
    {
        $this->validateCsrf();

        $login    = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $result = User::verifyForOcp($login, $password);
        if (!$result['ok']) {
            $message = match ($result['error']) {
                'forbidden' => 'Este usuario no tiene acceso gerencial (ADMIN).',
                'disabled'  => 'Esta cuenta está desactivada. Contacta al administrador.',
                'db'        => 'No se pudo validar contra la base de datos.',
                default     => 'Credenciales OCP incorrectas.',
            };
            flash('error', $message);
            redirect('login?ocp=1');
        }

        $started = OcpTwoFactor::beginChallenge($result['user']);
        if (!$started['ok']) {
            flash('error', $started['message']);
            redirect('login?ocp=1');
        }

        flash('success', $started['message']);
        redirect('login?ocp=1');
    }

    /** Paso 2 OCP: elegir correo/teléfono y enviar 2FA a n8n */
    public function ocpSendChannel(): void
    {
        $this->validateCsrf();

        $tipo = trim((string) ($_POST['tipo'] ?? ''));
        $sent = OcpTwoFactor::sendViaChannel($tipo);

        if (!$sent['ok']) {
            flash('error', $sent['message']);
            redirect('login?ocp=1');
        }

        flash('success', $sent['message']);
        redirect('login?ocp=1');
    }

    /** Paso 3 OCP: verificar código y pasar a ocp_Arya */
    public function ocpVerify(): void
    {
        $this->validateCsrf();

        $code = trim((string) ($_POST['codigo'] ?? ''));
        $result = OcpTwoFactor::verify($code);

        if (!$result['ok']) {
            flash('error', $result['message']);
            redirect('login?ocp=1');
        }

        $handoff = OcpTwoFactor::handoffPayload($result['user']);
        $ocpUrl = rtrim(ocp_public_url(), '/');
        $_SESSION['ocp_handoff'] = [
            'action'  => $ocpUrl . '/index.php',
            'payload' => $handoff,
        ];

        flash('success', 'Verificación correcta. Entrando a OCP…');
        redirect('login?ocp=1');
    }

    public function ocpCancel(): void
    {
        $this->validateCsrf();
        OcpTwoFactor::cancel();
        flash('success', 'Ingreso OCP cancelado.');
        redirect('login');
    }

    public function logout(): void
    {
        $this->validateCsrf();

        $user = auth();
        if ($user && isset($user['id'])) {
            User::logoutSession($user['id']);
        }

        unset($_SESSION['user'], $_SESSION['online_touched_at'], $_SESSION['profile_synced_at']);
        OcpTwoFactor::cancel();
        session_regenerate_id(true);
        flash('success', 'Sesión cerrada correctamente.');
        redirect('login');
    }
}
