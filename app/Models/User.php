<?php

declare(strict_types=1);

namespace Arya\Models;

use Arya\Core\Database;

final class User
{
    /**
     * Autentica contra maestrousuario.
     *
     * @return array{ok:true,user:array}|array{ok:false,error:string}
     */
    public static function login(string $login, string $password): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'error' => 'db'];
        }

        self::ensureHabilitadoColumn($pdo);

        try {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, usuario, contrasena, activo, correo, telefono, alias, imagen,
                        updated_at, fecha_ingreso
                 FROM maestrousuario
                 WHERE lower(usuario) = lower(:login)
                    OR lower(correo) = lower(:login)
                 LIMIT 1'
            );
            $stmt->execute(['login' => $login]);
            $row = $stmt->fetch();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'db'];
        }

        if (!$row || !password_verify($password, (string) $row['contrasena'])) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        // habilitado = cuenta habilitada por admin (OCP). Independiente de presencia online.
        if (!self::isHabilitado((int) $row['id'])) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        // activo = true: sesión abierta en otro equipo (salvo presencia stale > 8h)
        if (!empty($row['activo']) && !self::isPresenceStale($row)) {
            return ['ok' => false, 'error' => 'busy'];
        }

        try {
            $upd = $pdo->prepare(
                'UPDATE maestrousuario
                 SET activo = TRUE,
                     fecha_ingreso = NOW(),
                     fecha_salida = NULL,
                     updated_at = NOW()
                 WHERE id = :id
                   AND COALESCE(habilitado, TRUE) = TRUE'
            );
            $upd->execute(['id' => $row['id']]);
            if ($upd->rowCount() < 1) {
                return ['ok' => false, 'error' => 'disabled'];
            }
        } catch (\Throwable) {
            // Columna habilitado puede no existir aún en el WHERE
            try {
                self::ensureHabilitadoColumn($pdo);
                $upd = $pdo->prepare(
                    'UPDATE maestrousuario
                     SET activo = TRUE,
                         fecha_ingreso = NOW(),
                         fecha_salida = NULL,
                         updated_at = NOW()
                     WHERE id = :id
                       AND COALESCE(habilitado, TRUE) = TRUE'
                );
                $upd->execute(['id' => $row['id']]);
                if ($upd->rowCount() < 1) {
                    return ['ok' => false, 'error' => 'disabled'];
                }
            } catch (\Throwable) {
                return ['ok' => false, 'error' => 'db'];
            }
        }

        return [
            'ok'   => true,
            'user' => self::toSessionUser($row),
        ];
    }

    public static function logoutSession(int|string $id): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE maestrousuario
                 SET activo = FALSE,
                     fecha_salida = NOW(),
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
        } catch (\Throwable) {
            // silencioso: la sesión local se cierra igual
        }
    }

    /**
     * Mantiene activo=TRUE (presencia online) mientras hay sesión CRM abierta.
     * No reactiva cuentas deshabilitadas (habilitado=FALSE).
     */
    public static function ensureActive(int|string $id): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            self::ensureHabilitadoColumn($pdo);
            $stmt = $pdo->prepare(
                'UPDATE maestrousuario
                 SET activo = TRUE,
                     fecha_salida = NULL,
                     updated_at = NOW()
                 WHERE id = :id
                   AND COALESCE(habilitado, TRUE) = TRUE'
            );
            $stmt->execute(['id' => $id]);
        } catch (\Throwable) {
            // no bloquear la UI si falla el heartbeat
        }
    }

    /** Cuenta habilitada por administración (OCP). */
    public static function isHabilitado(int|string $id): bool
    {
        $pdo = Database::connection();
        if (!$pdo || (int) $id < 1) {
            return true;
        }
        try {
            self::ensureHabilitadoColumn($pdo);
            $stmt = $pdo->prepare(
                'SELECT COALESCE(habilitado, TRUE) AS habilitado
                 FROM maestrousuario WHERE id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }
            return !empty($row['habilitado']);
        } catch (\Throwable) {
            return true;
        }
    }

    public static function ensureHabilitadoColumn(?\PDO $pdo = null): void
    {
        $pdo = $pdo ?? Database::connection();
        if (!$pdo) {
            return;
        }
        try {
            $pdo->exec(
                'ALTER TABLE maestrousuario
                 ADD COLUMN IF NOT EXISTS habilitado BOOLEAN NOT NULL DEFAULT TRUE'
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function isPresenceStale(array $row): bool
    {
        $raw = (string) ($row['updated_at'] ?? $row['fecha_ingreso'] ?? '');
        if ($raw === '') {
            return true;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return true;
        }
        return (time() - $ts) > 8 * 3600;
    }

    /**
     * Compatibilidad con AuthController / data_mode anteriores.
     *
     * @return array{id:int|string,name:string,email:string,role:string,avatar:?string}|null
     */
    public static function attempt(string $login, string $password): ?array
    {
        $result = self::login($login, $password);
        return $result['ok'] ? $result['user'] : null;
    }

    /**
     * Valida credenciales OCP (ADMIN) sin marcar sesión CRM (activo).
     *
     * @return array{ok:true,user:array{id:int|string,nombre:string,usuario:string,correo:string,telefono:?string,alias:string}}|array{ok:false,error:string}
     */
    public static function verifyForOcp(string $login, string $password): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'error' => 'db'];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, usuario, contrasena, correo, telefono, alias
                 FROM maestrousuario
                 WHERE lower(usuario) = lower(:login)
                    OR lower(correo) = lower(:login)
                 LIMIT 1'
            );
            $stmt->execute(['login' => $login]);
            $row = $stmt->fetch();
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'db'];
        }

        if (!$row || !password_verify($password, (string) $row['contrasena'])) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        if (!self::isHabilitado((int) $row['id'])) {
            return ['ok' => false, 'error' => 'disabled'];
        }

        $alias = strtoupper((string) ($row['alias'] ?? ''));
        if ($alias !== 'ADMIN') {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        return [
            'ok'   => true,
            'user' => [
                'id'       => $row['id'],
                'nombre'   => (string) $row['nombre'],
                'usuario'  => (string) $row['usuario'],
                'correo'   => (string) $row['correo'],
                'telefono' => isset($row['telefono']) && $row['telefono'] !== '' ? (string) $row['telefono'] : null,
                'alias'    => $alias,
            ],
        ];
    }

    public static function tableExists(): bool
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }

        try {
            $pdo->query('SELECT 1 FROM maestrousuario LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(int|string $id): ?array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, usuario, activo, correo, telefono, alias, imagen,
                        fecha_ingreso, fecha_salida, created_at, updated_at
                 FROM maestrousuario
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Actualiza perfil editable (no cambia alias ni usuario).
     *
     * @param array{nombre?:string,correo?:string,telefono?:?string,imagen?:?string,password?:string} $data
     * @return array{ok:bool,message:string,user?:array}
     */
    public static function updateProfile(int|string $id, array $data): array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return ['ok' => false, 'message' => 'Sin conexión a la base de datos.'];
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $correo = trim((string) ($data['correo'] ?? ''));
        $telefono = trim((string) ($data['telefono'] ?? ''));
        $imagen = trim((string) ($data['imagen'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($nombre === '' || $correo === '') {
            return ['ok' => false, 'message' => 'Nombre y correo son obligatorios.'];
        }

        try {
            if ($password !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE maestrousuario
                     SET nombre = :nombre,
                         correo = :correo,
                         telefono = :telefono,
                         imagen = :imagen,
                         contrasena = :contrasena,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'telefono'   => $telefono !== '' ? $telefono : null,
                    'imagen'     => $imagen !== '' ? $imagen : null,
                    'contrasena' => password_hash($password, PASSWORD_DEFAULT),
                    'id'         => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE maestrousuario
                     SET nombre = :nombre,
                         correo = :correo,
                         telefono = :telefono,
                         imagen = :imagen,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'nombre'   => $nombre,
                    'correo'   => $correo,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'imagen'   => $imagen !== '' ? $imagen : null,
                    'id'       => $id,
                ]);
            }

            $row = self::find($id);
            if (!$row) {
                return ['ok' => false, 'message' => 'Usuario no encontrado tras guardar.'];
            }

            return [
                'ok'      => true,
                'message' => 'Perfil actualizado.',
                'user'    => self::toSessionUser($row),
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'maestrousuario_correo') || str_contains(strtolower($msg), 'unique')) {
                return ['ok' => false, 'message' => 'Ese correo ya está en uso.'];
            }
            return ['ok' => false, 'message' => 'No se pudo guardar el perfil.'];
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array{id:int|string,name:string,email:string,role:string,usuario:string,telefono:?string,avatar:?string}
     */
    private static function toSessionUser(array $row): array
    {
        return [
            'id'       => $row['id'],
            'name'     => (string) $row['nombre'],
            'email'    => (string) $row['correo'],
            'usuario'  => (string) ($row['usuario'] ?? ''),
            'telefono' => isset($row['telefono']) && $row['telefono'] !== '' ? (string) $row['telefono'] : null,
            'role'     => strtoupper((string) ($row['alias'] ?? 'AGENT')),
            'avatar'   => isset($row['imagen']) && $row['imagen'] !== '' ? (string) $row['imagen'] : null,
        ];
    }
}
