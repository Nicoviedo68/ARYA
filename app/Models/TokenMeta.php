<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

/**
 * Tokens Meta (WhatsApp / Messenger / Instagram) → tabla token_meta.
 */
final class TokenMeta
{
    private static bool $bootstrapped = false;

    /** @return list<string> */
    public static function canalOptions(): array
    {
        return ['whatsapp', 'messenger', 'instagram'];
    }

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS token_meta (
                    id              BIGSERIAL PRIMARY KEY,
                    canal           VARCHAR(40)  NOT NULL,
                    page_id         VARCHAR(120) NOT NULL,
                    identificador   VARCHAR(255),
                    token           TEXT         NOT NULL,
                    estado          VARCHAR(30)  NOT NULL DEFAULT \'conectado\',
                    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
                    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                    CONSTRAINT token_meta_canal_chk CHECK (canal IN (\'whatsapp\', \'messenger\', \'instagram\'))
                )'
            );
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_token_meta_canal ON token_meta (canal)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_token_meta_activo ON token_meta (activo)');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_token_meta_canal_page ON token_meta (canal, page_id) WHERE activo = TRUE');
        } catch (\Throwable) {
            // sin DB usable
        }
    }

    public static function tableExists(): bool
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }
        try {
            $pdo->query('SELECT 1 FROM token_meta LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function allActive(): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->query(
                'SELECT id, canal, page_id, identificador, estado,
                        CASE WHEN token IS NOT NULL AND token <> \'\' THEN TRUE ELSE FALSE END AS tiene_token,
                        created_at, updated_at
                 FROM token_meta
                 WHERE activo = TRUE
                 ORDER BY canal ASC, page_id ASC'
            );
            return $stmt ? $stmt->fetchAll() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array{canal:string,page_id:string,identificador?:string,token?:string,estado?:string} $data
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function create(array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a base de datos.'];
        }

        $parsed = self::normalize($data, true);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => $parsed['message']];
        }
        $row = $parsed['row'];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO token_meta (canal, page_id, identificador, token, estado, updated_at)
                 VALUES (:canal, :page_id, :identificador, :token, :estado, NOW())
                 RETURNING id'
            );
            $stmt->execute($row);
            $id = (int) $stmt->fetchColumn();

            return ['ok' => true, 'message' => 'Token Meta guardado.', 'id' => $id];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'uq_token_meta_canal_page') || str_contains($msg, 'duplicate')) {
                return ['ok' => false, 'message' => 'Ya existe un registro activo para ese canal + page_id.'];
            }
            return ['ok' => false, 'message' => 'No se pudo guardar: ' . $msg];
        }
    }

    /**
     * @param array{canal:string,page_id:string,identificador?:string,token?:string,estado?:string} $data
     * @return array{ok:bool,message:string}
     */
    public static function update(int $id, array $data): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return ['ok' => false, 'message' => 'Datos inválidos.'];
        }

        $parsed = self::normalize($data, false);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => $parsed['message']];
        }
        $row = $parsed['row'];

        try {
            if ($row['token'] !== null && $row['token'] !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE token_meta SET
                        canal = :canal,
                        page_id = :page_id,
                        identificador = :identificador,
                        token = :token,
                        estado = :estado,
                        updated_at = NOW()
                     WHERE id = :id AND activo = TRUE'
                );
                $stmt->execute([
                    'canal'         => $row['canal'],
                    'page_id'       => $row['page_id'],
                    'identificador' => $row['identificador'],
                    'token'         => $row['token'],
                    'estado'        => $row['estado'],
                    'id'            => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE token_meta SET
                        canal = :canal,
                        page_id = :page_id,
                        identificador = :identificador,
                        estado = :estado,
                        updated_at = NOW()
                     WHERE id = :id AND activo = TRUE'
                );
                $stmt->execute([
                    'canal'         => $row['canal'],
                    'page_id'       => $row['page_id'],
                    'identificador' => $row['identificador'],
                    'estado'        => $row['estado'],
                    'id'            => $id,
                ]);
            }

            return ['ok' => true, 'message' => 'Token Meta actualizado.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo actualizar: ' . $e->getMessage()];
        }
    }

    /** Soft-delete */
    public static function deactivate(int $id): array
    {
        self::bootstrap();
        $pdo = Database::connection();
        if (!$pdo || $id < 1) {
            return ['ok' => false, 'message' => 'Datos inválidos.'];
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE token_meta
                 SET activo = FALSE, estado = \'desconectado\', updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            return ['ok' => true, 'message' => 'Cuenta Meta desconectada.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo desconectar: ' . $e->getMessage()];
        }
    }

    /**
     * Credenciales WhatsApp SOLO desde token_meta (fuente de verdad).
     *
     * Mapeo Meta API Setup:
     *  - page_id       = WhatsApp Business Account ID (WABA)
     *  - identificador = Phone number ID  ← se usa en Graph /{id}/messages
     *  - token         = Permanent access token (EAA…)
     *
     * @return array{token:string,phone_number_id:string,page_id:string,identificador:string}|null
     */
    public static function whatsappCredentials(?string $phoneNumberId = null, ?string $pageId = null): ?array
    {
        $base = self::channelCredentials('whatsapp');
        if (!$base) {
            return null;
        }
        $phoneId = trim((string) ($base['identificador'] ?? ''));
        if ($phoneId === '' || !preg_match('/^\d{6,20}$/', $phoneId)) {
            return null;
        }
        return [
            'token'           => $base['token'],
            'phone_number_id' => $phoneId,
            'page_id'         => $base['page_id'],
            'identificador'   => $phoneId,
        ];
    }

    /**
     * Credenciales por canal (whatsapp | messenger | instagram).
     * Si se pasa $pageId, prioriza esa página (útil cuando hay varias cuentas).
     *
     * @return array{token:string,page_id:string,identificador:string}|null
     */
    public static function channelCredentials(string $canal, ?string $pageId = null): ?array
    {
        self::bootstrap();
        $canal = strtolower(trim($canal));
        if (!in_array($canal, self::canalOptions(), true)) {
            return null;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        $pageId = $pageId !== null ? trim($pageId) : '';

        try {
            if ($pageId !== '') {
                $stmt = $pdo->prepare(
                    "SELECT page_id, identificador, token
                     FROM token_meta
                     WHERE activo = TRUE AND canal = :canal AND page_id = :page_id
                       AND token IS NOT NULL AND token <> ''
                     ORDER BY updated_at DESC
                     LIMIT 1"
                );
                $stmt->execute(['canal' => $canal, 'page_id' => $pageId]);
                $pick = $stmt->fetch();
                if ($pick) {
                    $token = trim((string) ($pick['token'] ?? ''));
                    if ($token !== '' && strlen($token) >= 40) {
                        return [
                            'token'         => $token,
                            'page_id'       => trim((string) ($pick['page_id'] ?? '')),
                            'identificador' => trim((string) ($pick['identificador'] ?? '')),
                        ];
                    }
                }
            }

            $stmt = $pdo->prepare(
                "SELECT page_id, identificador, token
                 FROM token_meta
                 WHERE activo = TRUE AND canal = :canal AND token IS NOT NULL AND token <> ''
                 ORDER BY updated_at DESC
                 LIMIT 1"
            );
            $stmt->execute(['canal' => $canal]);
            $pick = $stmt->fetch();
            if (!$pick) {
                return null;
            }

            $token = trim((string) ($pick['token'] ?? ''));
            if ($token === '' || strlen($token) < 40) {
                return null;
            }

            return [
                'token'         => $token,
                'page_id'       => trim((string) ($pick['page_id'] ?? '')),
                'identificador' => trim((string) ($pick['identificador'] ?? '')),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array{canal:string,page_id:string,identificador?:string,token?:string,estado?:string} $data
     * @return array{ok:true,row:array}|array{ok:false,message:string}
     */
    private static function normalize(array $data, bool $requireToken): array
    {
        $canal = strtolower(trim((string) ($data['canal'] ?? '')));
        $pageId = trim((string) ($data['page_id'] ?? ''));
        $identificador = trim((string) ($data['identificador'] ?? ''));
        $token = trim((string) ($data['token'] ?? ''));
        $estado = trim((string) ($data['estado'] ?? 'conectado')) ?: 'conectado';

        if (!in_array($canal, self::canalOptions(), true)) {
            return ['ok' => false, 'message' => 'Canal inválido. Usa whatsapp, messenger o instagram.'];
        }
        if ($pageId === '') {
            return ['ok' => false, 'message' => 'page_id es obligatorio.'];
        }
        if ($canal === 'whatsapp') {
            if ($identificador === '' || !preg_match('/^\d{6,20}$/', $identificador)) {
                return ['ok' => false, 'message' => 'identificador = Phone number ID (solo dígitos). Ej: 585313634670751'];
            }
            if ($pageId === '' || !preg_match('/^\d{6,20}$/', $pageId)) {
                return ['ok' => false, 'message' => 'page_id = WhatsApp Business Account ID (WABA). Ej: 578881904471475'];
            }
            if (str_starts_with($identificador, 'EAA') || str_starts_with($pageId, 'EAA')) {
                return ['ok' => false, 'message' => 'El access token va solo en el campo token (EAA…).'];
            }
            if (($requireToken || $token !== '') && $token !== '' && strlen($token) < 40) {
                return ['ok' => false, 'message' => 'El access token parece inválido (debe ser el token largo EAA…).'];
            }
        }
        if ($canal !== 'whatsapp') {
            // messenger/instagram: identificador opcional
        }
        if ($requireToken && $token === '') {
            return ['ok' => false, 'message' => 'El token es obligatorio.'];
        }

        return [
            'ok'  => true,
            'row' => [
                'canal'         => $canal,
                'page_id'       => $pageId,
                'identificador' => $identificador !== '' ? $identificador : null,
                'token'         => $token,
                'estado'        => $estado,
            ],
        ];
    }
}
