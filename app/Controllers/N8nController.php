<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\N8N\TwoFactorService;

/**
 * Endpoints HTTP para n8n.
 */
final class N8nController extends Controller
{
    /**
     * POST /api/n8n/2fa/codigo
     *
     * Guarda maestrousuario.cod_ingreso
     *
     * JSON:
     * {
     *   "id": 1,
     *   "cod_ingreso": "482913"
     * }
     */
    public function saveCodigo(): void
    {
        if (!$this->authorizeN8n()) {
            $this->json(['ok' => false, 'message' => 'No autorizado.'], 401);
        }

        $payload = $this->jsonBody();

        $id = $payload['id'] ?? $_POST['id'] ?? null;
        $codIngreso = trim((string) (
            $payload['cod_ingreso']
            ?? $_POST['cod_ingreso']
            ?? $payload['codigo']
            ?? $_POST['codigo']
            ?? ''
        ));

        if ($id === null || $id === '' || $codIngreso === '') {
            $this->json([
                'ok'      => false,
                'message' => 'Se requieren id y cod_ingreso.',
                'example' => [
                    'id'          => 1,
                    'cod_ingreso' => '482913',
                ],
            ], 422);
        }

        $user = TwoFactorService::findById($id);
        if (!$user) {
            $this->json(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        if (!TwoFactorService::setCodigoIngreso($id, $codIngreso)) {
            $this->json(['ok' => false, 'message' => 'No se pudo guardar cod_ingreso.'], 500);
        }

        $this->json([
            'ok'      => true,
            'message' => 'cod_ingreso guardado.',
            'data'    => [
                'id'          => $user['id'],
                'usuario'     => $user['usuario'],
                'cod_ingreso' => $codIngreso,
            ],
        ]);
    }

    private function authorizeN8n(): bool
    {
        $expected = trim((string) config('n8n.api_key', ''));
        if ($expected === '') {
            return true;
        }

        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return hash_equals($expected, trim($m[1]));
        }

        $fromBody = $this->jsonBody();
        $key = (string) ($fromBody['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '');
        return $key !== '' && hash_equals($expected, $key);
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonBody(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            $cached = [];
            return $cached;
        }

        $data = json_decode($raw, true);
        $cached = is_array($data) ? $data : [];
        return $cached;
    }
}
