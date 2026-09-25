<?php

declare(strict_types=1);

namespace Arya\Services;

use Arya\N8N\TwoFactorService;

/**
 * 2FA gerencial (OCP):
 * 1) Credenciales → elegir canal (correo / teléfono)
 * 2) Guarda código en maestrousuario.cod_ingreso
 * 3) Envía a n8n: id + tipo (+ codigo)
 * 4) Valida código contra cod_ingreso
 */
final class OcpTwoFactor
{
    private const SESSION_KEY = 'ocp_2fa';
    private const TTL_SECONDS = 300;

    /**
     * Tras login OCP válido: prepara elección de canal (aún no envía código).
     *
     * @param array{id:int|string,nombre:string,usuario:string,correo:string,telefono:?string,alias:string} $user
     * @return array{ok:bool,message:string}
     */
    public static function beginChallenge(array $user): array
    {
        $dbUser = TwoFactorService::findById($user['id']);
        if (!$dbUser) {
            return ['ok' => false, 'message' => 'No se encontró el usuario en maestrousuario.'];
        }

        $hasEmail = trim((string) $dbUser['correo']) !== '';
        $hasPhone = trim((string) ($dbUser['telefono'] ?? '')) !== '';

        if (!$hasEmail && !$hasPhone) {
            return ['ok' => false, 'message' => 'El usuario no tiene correo ni teléfono para recibir el código.'];
        }

        $_SESSION[self::SESSION_KEY] = [
            'step'       => 'channel',
            'user_id'    => $dbUser['id'],
            'expires_at' => time() + self::TTL_SECONDS,
            'attempts'   => 0,
            'tipo'       => null,
        ];

        return ['ok' => true, 'message' => 'Elige cómo recibir el código de verificación.'];
    }

    /**
     * Envía 2FA por el canal elegido.
     * $tipo: correo | telefono
     *
     * @return array{ok:bool,message:string}
     */
    public static function sendViaChannel(string $tipo): array
    {
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['correo', 'telefono'], true)) {
            return ['ok' => false, 'message' => 'Selecciona correo o teléfono.'];
        }

        if (!self::pending() || self::step() !== 'channel') {
            return ['ok' => false, 'message' => 'La sesión 2FA no es válida. Vuelve a iniciar.'];
        }

        $webhook = (string) config('ocp.webhook_2fa', '');
        if ($webhook === '') {
            return ['ok' => false, 'message' => 'Webhook 2FA OCP no configurado.'];
        }

        $id = $_SESSION[self::SESSION_KEY]['user_id'];
        $dbUser = TwoFactorService::findById($id);
        if (!$dbUser) {
            self::cancel();
            return ['ok' => false, 'message' => 'No se encontró el usuario en maestrousuario.'];
        }

        if ($tipo === 'correo' && trim((string) $dbUser['correo']) === '') {
            return ['ok' => false, 'message' => 'Este usuario no tiene correo registrado.'];
        }
        if ($tipo === 'telefono' && trim((string) ($dbUser['telefono'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'Este usuario no tiene teléfono registrado.'];
        }

        $codigo = (string) random_int(100000, 999999);

        if (!TwoFactorService::setCodigoIngreso($id, $codigo)) {
            return ['ok' => false, 'message' => 'No se pudo guardar cod_ingreso en la base de datos.'];
        }

        // Webhook n8n: id + tipo + cod_ingreso (nombre real de columna)
        $payload = [
            'id'          => $dbUser['id'],
            'tipo'        => $tipo,
            'cod_ingreso' => $codigo,
            'usuario'     => $dbUser['usuario'],
            'nombre'      => $dbUser['nombre'],
            'correo'      => $dbUser['correo'],
            'telefono'    => $dbUser['telefono'],
            'destino'     => $tipo === 'correo' ? $dbUser['correo'] : $dbUser['telefono'],
        ];

        $sent = self::postWebhook($webhook, $payload);
        if (!$sent['ok']) {
            TwoFactorService::clearCodigoIngreso($id);
            return [
                'ok'      => false,
                'message' => $sent['message'] !== ''
                    ? $sent['message']
                    : 'No se pudo enviar el código 2FA. Intenta de nuevo.',
            ];
        }

        $_SESSION[self::SESSION_KEY]['step'] = 'code';
        $_SESSION[self::SESSION_KEY]['tipo'] = $tipo;
        $_SESSION[self::SESSION_KEY]['expires_at'] = time() + self::TTL_SECONDS;
        $_SESSION[self::SESSION_KEY]['attempts'] = 0;

        $label = $tipo === 'correo' ? 'correo' : 'teléfono';
        return ['ok' => true, 'message' => "Código enviado por {$label}."];
    }

    public static function step(): string
    {
        if (!self::pending()) {
            return 'none';
        }
        return (string) ($_SESSION[self::SESSION_KEY]['step'] ?? 'none');
    }

    /**
     * Opciones de canal enmascaradas para la UI.
     *
     * @return array{correo:?string,telefono:?string,nombre:string,id:int|string}|null
     */
    public static function channelOptions(): ?array
    {
        if (!self::pending()) {
            return null;
        }

        $user = TwoFactorService::findById($_SESSION[self::SESSION_KEY]['user_id']);
        if (!$user) {
            return null;
        }

        $correo = trim((string) $user['correo']);
        $telefono = trim((string) ($user['telefono'] ?? ''));

        return [
            'id'       => $user['id'],
            'nombre'   => $user['nombre'],
            'correo'   => $correo !== '' ? self::maskEmail($correo) : null,
            'telefono' => $telefono !== '' ? self::maskPhone($telefono) : null,
            'tipo'     => $_SESSION[self::SESSION_KEY]['tipo'] ?? null,
        ];
    }

    public static function pending(): bool
    {
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($data) || empty($data['user_id'])) {
            return false;
        }
        if ((int) ($data['expires_at'] ?? 0) < time()) {
            if (!empty($data['user_id']) && ($data['step'] ?? '') === 'code') {
                TwoFactorService::clearCodigoIngreso($data['user_id']);
            }
            self::clear();
            return false;
        }
        return true;
    }

    /**
     * @return array{id:int|string,nombre:string,usuario:string,correo:string,telefono:?string,alias:string,tipo:?string}|null
     */
    public static function pendingUser(): ?array
    {
        if (!self::pending()) {
            return null;
        }

        $user = TwoFactorService::findById($_SESSION[self::SESSION_KEY]['user_id']);
        if (!$user) {
            return null;
        }

        unset($user['cod_ingreso']);
        $user['tipo'] = $_SESSION[self::SESSION_KEY]['tipo'] ?? null;
        return $user;
    }

    /**
     * @return array{ok:true,user:array}|array{ok:false,message:string}
     */
    public static function verify(string $code): array
    {
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($data) || empty($data['user_id']) || (int) ($data['expires_at'] ?? 0) < time()) {
            if (is_array($data) && !empty($data['user_id']) && ($data['step'] ?? '') === 'code') {
                TwoFactorService::clearCodigoIngreso($data['user_id']);
            }
            self::clear();
            return ['ok' => false, 'message' => 'El código expiró. Solicita uno nuevo.'];
        }

        if (($data['step'] ?? '') !== 'code') {
            return ['ok' => false, 'message' => 'Primero elige el canal y solicita el código.'];
        }

        $attempts = (int) ($data['attempts'] ?? 0) + 1;
        $_SESSION[self::SESSION_KEY]['attempts'] = $attempts;

        if ($attempts > 5) {
            TwoFactorService::clearCodigoIngreso($data['user_id']);
            self::clear();
            return ['ok' => false, 'message' => 'Demasiados intentos. Vuelve a iniciar el ingreso OCP.'];
        }

        $result = TwoFactorService::verifyCodigoIngreso($data['user_id'], $code);
        if (!$result['ok']) {
            return $result;
        }

        self::clear();

        return ['ok' => true, 'user' => $result['user']];
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function cancel(): void
    {
        $data = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($data) && !empty($data['user_id']) && ($data['step'] ?? '') === 'code') {
            TwoFactorService::clearCodigoIngreso($data['user_id']);
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLen = mb_strlen($local);
        $localMask = $localLen <= 2
            ? str_repeat('*', max(1, $localLen))
            : mb_substr($local, 0, 2) . str_repeat('*', max(2, $localLen - 2));

        $parts = explode('.', $domain);
        $name = $parts[0] ?? '';
        $tld = count($parts) > 1 ? '.' . implode('.', array_slice($parts, 1)) : '';
        $nameLen = mb_strlen($name);
        $domainMask = $nameLen <= 1
            ? '*'
            : mb_substr($name, 0, 1) . str_repeat('*', max(2, $nameLen - 1));

        return $localMask . '@' . $domainMask . $tld;
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '****';
        }
        $len = strlen($digits);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return str_repeat('*', $len - 4) . substr($digits, -4);
    }

    /**
     * @param array{id:int|string,nombre:string,usuario:string,correo:string,alias:string} $user
     * @return array{id:string,usuario:string,exp:int,token:string,nombre:string,correo:string,alias:string}
     */
    public static function handoffPayload(array $user): array
    {
        $exp = time() + 120;
        $id = (string) $user['id'];
        $usuario = (string) $user['usuario'];
        $key = (string) config('app.key', 'arya-dev-key');
        $token = hash_hmac('sha256', $id . '|' . $usuario . '|' . $exp, $key);

        return [
            'id'      => $id,
            'usuario' => $usuario,
            'nombre'  => (string) $user['nombre'],
            'correo'  => (string) $user['correo'],
            'alias'   => (string) $user['alias'],
            'exp'     => $exp,
            'token'   => $token,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,message:string}
     */
    private static function postWebhook(string $url, array $payload): array
    {
        if (!extension_loaded('curl')) {
            return ['ok' => false, 'message' => 'Extensión cURL no disponible en PHP.'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'No se pudo iniciar cURL.'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $apiKey = trim((string) config('n8n.api_key', ''));
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $opts = [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        $cafile = (string) (ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: '');
        if ($cafile !== '' && is_file($cafile)) {
            $opts[CURLOPT_CAINFO] = $cafile;
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($errno === 60 && (string) config('app.env', 'local') === 'local') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
        }

        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'message' => 'Error al contactar n8n: ' . $error];
        }

        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'message' => 'n8n respondió HTTP ' . $status];
        }

        return ['ok' => true, 'message' => is_string($response) ? $response : ''];
    }
}
