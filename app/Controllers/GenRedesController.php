<?php

declare(strict_types=1);

namespace Arya\Controllers;

use Arya\Core\Controller;
use Arya\GenRedes\MediaRepository;
use Arya\GenRedes\MediaUrl;

/**
 * Rutas públicas del módulo gen_redes (contenido para Instagram / redes).
 * Sin AuthMiddleware: Instagram Graph API debe poder descargar la imagen.
 */
final class GenRedesController extends Controller
{
    /**
     * GET /gen-redes/media/{id}
     * Sirve el binario de arya_media_assets.
     */
    public function media(string $id): void
    {
        MediaRepository::stream((int) $id);
    }

    /**
     * GET /gen-redes/media/{id}/url
     * Devuelve JSON con la URL pública (útil para n8n después del Insert).
     */
    public function mediaUrl(string $id): void
    {
        $mediaId = (int) $id;
        $row = MediaRepository::find($mediaId);
        if (!$row) {
            $this->json(['ok' => false, 'message' => 'Media no encontrada'], 404);
        }

        $url = MediaRepository::ensurePublicUrl($mediaId) ?? MediaUrl::forId($mediaId);

        $this->json([
            'ok'         => true,
            'id'         => $mediaId,
            'image_url'  => $url,
            'public_url' => $url,
            'mime_type'  => $row['mime_type'],
            'file_name'  => $row['file_name'],
        ]);
    }

    /**
     * POST /gen-redes/media
     * Preferible desde n8n: public/gen_redes/upload.php
     */
    public function store(): void
    {
        $this->json([
            'ok'      => false,
            'message' => 'Usa el endpoint multipart: /public/gen_redes/upload.php',
            'upload'  => rtrim((string) config('app.url', ''), '/') . '/gen_redes/upload.php',
        ], 400);
    }
}
