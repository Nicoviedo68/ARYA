<?php

declare(strict_types=1);

namespace Arya\N8N;

use Arya\Core\Database;

/**
 * 2FA OCP: guarda y valida cod_ingreso en maestrousuario.
 */
final class TwoFactorService
{
    /**
     * Busca usuario por id.
     *
     * @return array{
     *   id:int|string,
     *   nombre:string,
     *   usuario:string,
     *   correo:string,
     *   telefono:?string,
     *   alias:string,
     *   cod_ingreso:?string
     * }|null
     */
    public static function findById(int|string $id): ?array
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, nombre, usuario, correo, telefono, alias, cod_ingreso
                 FROM maestrousuario
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }

            return [
                'id'          => $row['id'],
                'nombre'      => (string) $row['nombre'],
                'usuario'     => (string) $row['usuario'],
                'correo'      => (string) $row['correo'],
                'telefono'    => isset($row['telefono']) && $row['telefono'] !== '' ? (string) $row['telefono'] : null,
                'alias'       => strtoupper((string) ($row['alias'] ?? 'AGENT')),
                'cod_ingreso' => isset($row['cod_ingreso']) && $row['cod_ingreso'] !== ''
                    ? (string) $row['cod_ingreso']
                    : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Asigna el código 2FA en cod_ingreso para el usuario.
     */
    public static function setCodigoIngreso(int|string $id, string $codigo): bool
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return false;
        }

        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if ($codigo === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE maestrousuario
                 SET cod_ingreso = :codigo,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'codigo' => $codigo,
                'id'     => $id,
            ]);

            return $stmt->rowCount() > 0 || self::findById($id) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Verifica que el código ingresado coincida con maestrousuario.cod_ingreso.
     *
     * @return array{ok:true,user:array}|array{ok:false,message:string}
     */
    public static function verifyCodigoIngreso(int|string $id, string $codigo): array
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';
        if ($codigo === '') {
            return ['ok' => false, 'message' => 'Ingresa el código de verificación.'];
        }

        $user = self::findById($id);
        if (!$user) {
            return ['ok' => false, 'message' => 'Usuario no encontrado.'];
        }

        $stored = (string) ($user['cod_ingreso'] ?? '');
        if ($stored === '') {
            return ['ok' => false, 'message' => 'No hay código activo. Solicita uno nuevo.'];
        }

        if (!hash_equals($stored, $codigo)) {
            return ['ok' => false, 'message' => 'Código incorrecto.'];
        }

        self::clearCodigoIngreso($id);

        unset($user['cod_ingreso']);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Limpia cod_ingreso tras login exitoso o cancelación.
     */
    public static function clearCodigoIngreso(int|string $id): void
    {
        $pdo = Database::connection();
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE maestrousuario
                 SET cod_ingreso = NULL,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
        } catch (\Throwable) {
            // silencioso
        }
    }
}
