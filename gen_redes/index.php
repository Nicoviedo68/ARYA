<?php

declare(strict_types=1);

/**
 * Módulo gen_redes — generación/publicación de contenido para redes.
 *
 * Endpoints públicos:
 * - /public/gen_redes/media.php?id={id}
 * - /gen-redes/media/{id}
 * - /gen-redes/media/{id}/url  (JSON con image_url)
 */

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'      => true,
    'module'  => 'gen_redes',
    'endpoints' => [
        'media'     => 'public/gen_redes/media.php?id={id}',
        'router'    => '/gen-redes/media/{id}',
        'media_url' => '/gen-redes/media/{id}/url',
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
