<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\Core\Database;
use Arya\Models\Integration;
use Arya\Models\TokenMeta;
use Arya\Models\TokenRedes;
use Arya\Models\User;
use Arya\Services\MetaWebhook;
use Arya\Services\SupabaseClient;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireMenuAccess('settings');
        $auth = auth();

        TokenMeta::bootstrap();
        TokenRedes::bootstrap();
        Integration::bootstrap();
        MetaWebhook::bootstrap();

        $api = null;
        $client = SupabaseClient::fromConfig();
        if ($client) {
            $api = $client->health();
        }

        $db = Database::ping();
        Integration::syncRuntimeStatus($db, $api);

        $tab = (string) ($_GET['tab'] ?? 'accounts');
        if (!in_array($tab, ['accounts', 'redes', 'integrations'], true)) {
            $tab = 'accounts';
        }

        $this->view('settings/index', [
            'title'              => 'Configuración',
            'active'             => 'settings',
            'user'               => $auth,
            'tab'                => $tab,
            'accounts'           => TokenMeta::allActive(),
            'canales'            => TokenMeta::canalOptions(),
            'otrasRedes'         => TokenRedes::allActive(),
            'redesOptions'       => TokenRedes::redOptions(),
            'integrations'       => Integration::allActive(),
            'db'                 => $db,
            'api'                => $api,
            'tableExists'        => User::tableExists(),
            'accountsTableOk'    => TokenMeta::tableExists(),
            'redesTableOk'       => TokenRedes::tableExists(),
            'integrationsTableOk'=> Integration::tableExists(),
            'metaWebhookUrl'     => MetaWebhook::callbackUrl(),
            'metaVerifyToken'    => MetaWebhook::verifyToken(),
            'metaInbox'          => MetaWebhook::recentInbox(6),
            'metaInboxCount'     => MetaWebhook::inboxCount(),
        ]);
    }

    public function rotateMetaVerify(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $token = MetaWebhook::rotateVerifyToken();
        flash('success', 'Verify token regenerado. Actualízalo también en Meta Developers.');
        // No exponer en flash el token completo por si se loguea; ya se ve en pantalla
        unset($token);
        redirect('configuracion?tab=accounts');
    }

    public function saveAccount(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            'canal'         => (string) ($_POST['canal'] ?? ''),
            'page_id'       => (string) ($_POST['page_id'] ?? ''),
            'identificador' => (string) ($_POST['identificador'] ?? ''),
            'token'         => (string) ($_POST['token'] ?? ''),
            'estado'        => (string) ($_POST['estado'] ?? 'conectado'),
        ];

        $result = $id > 0
            ? TokenMeta::update($id, $payload)
            : TokenMeta::create($payload);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=accounts');
    }

    public function deleteAccount(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $result = TokenMeta::deactivate($id);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=accounts');
    }

    public function saveRed(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $payload = [
            'red'           => (string) ($_POST['red'] ?? ''),
            'nombre_cuenta' => (string) ($_POST['nombre_cuenta'] ?? ''),
            'identificador' => (string) ($_POST['identificador'] ?? ''),
            'token'         => (string) ($_POST['token'] ?? ''),
            'client_id'     => (string) ($_POST['client_id'] ?? ''),
            'client_secret' => (string) ($_POST['client_secret'] ?? ''),
            'estado'        => (string) ($_POST['estado'] ?? 'conectado'),
        ];

        $result = $id > 0
            ? TokenRedes::update($id, $payload)
            : TokenRedes::create($payload);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=redes');
    }

    public function deleteRed(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $result = TokenRedes::deactivate($id);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=redes');
    }

    public function saveIntegration(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $isNew = $id < 1;

        if ($isNew) {
            $result = Integration::create([
                'codigo'       => (string) ($_POST['codigo'] ?? ''),
                'nombre'       => (string) ($_POST['nombre'] ?? ''),
                'tipo'         => (string) ($_POST['tipo'] ?? 'servicio'),
                'estado'       => (string) ($_POST['estado'] ?? 'inactivo'),
                'endpoint_url' => (string) ($_POST['endpoint_url'] ?? ''),
                'api_key'      => (string) ($_POST['api_key'] ?? ''),
                'notas'        => (string) ($_POST['notas'] ?? ''),
            ]);
        } else {
            $result = Integration::update($id, [
                'nombre'       => (string) ($_POST['nombre'] ?? ''),
                'tipo'         => (string) ($_POST['tipo'] ?? 'servicio'),
                'estado'       => (string) ($_POST['estado'] ?? 'inactivo'),
                'endpoint_url' => (string) ($_POST['endpoint_url'] ?? ''),
                'api_key'      => (string) ($_POST['api_key'] ?? ''),
                'notas'        => (string) ($_POST['notas'] ?? ''),
            ]);
        }

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=integrations');
    }

    public function deleteIntegration(): void
    {
        $this->validateCsrf();
        $this->requireMenuAccess('settings');

        $id = (int) ($_POST['id'] ?? 0);
        $result = Integration::deactivate($id);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('configuracion?tab=integrations');
    }

    /** Perfil desde modal del sidebar. */
    public function saveProfile(): void
    {
        $this->validateCsrf();

        $auth = auth();
        if (!$auth || !isset($auth['id'])) {
            flash('error', 'Sesión no válida.');
            redirect('login');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? $_POST['name'] ?? ''));
        $correo = trim((string) ($_POST['correo'] ?? $_POST['email'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $imagen = trim((string) ($_POST['imagen'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (User::tableExists()) {
            $result = User::updateProfile($auth['id'], [
                'nombre'   => $nombre,
                'correo'   => $correo,
                'telefono' => $telefono,
                'imagen'   => $imagen,
                'password' => $password,
            ]);

            if (!$result['ok']) {
                flash('error', $result['message']);
                redirect('dashboard');
            }

            $_SESSION['user'] = $result['user'];
            flash('success', $result['message']);
            redirect('dashboard');
        }

        if ($nombre !== '') {
            $_SESSION['user']['name'] = $nombre;
            $_SESSION['user']['email'] = $correo !== '' ? $correo : ($_SESSION['user']['email'] ?? '');
            $_SESSION['user']['telefono'] = $telefono !== '' ? $telefono : null;
            $_SESSION['user']['avatar'] = $imagen !== '' ? $imagen : null;
        }

        flash('success', 'Perfil actualizado en sesión (modo demo).');
        redirect('dashboard');
    }
}
