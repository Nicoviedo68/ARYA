<?php

declare(strict_types=1);

namespace Arya\GenRedes;

use Arya\Core\Database;

/**
 * Acceso a arya_media_assets (imágenes generadas para redes).
 */
final class MediaRepository
{
    private static ?string $lastCreateError = null;

    public static function lastCreateError(): ?string
    {
        return self::$lastCreateError;
    }

    /**
     * @return array{
     *   id:int,
     *   mime_type:string,
     *   file_name:string,
     *   file_extension:?string,
     *   file_size_bytes:?int,
     *   file_data:string,
     *   file_path:?string,
     *   public_url:?string,
     *   caption:?string,
     *   title:?string
     * }|null
     */
    public static function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        try {
            $row = null;
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, mime_type, file_name, file_extension, file_size_bytes,
                            file_data, file_path, public_url, caption, title
                     FROM arya_media_assets
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            } catch (\Throwable) {
                $stmt = $pdo->prepare(
                    'SELECT id, mime_type, file_name, file_extension, file_size_bytes,
                            file_data, public_url, caption, title
                     FROM arya_media_assets
                     WHERE id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                if (is_array($row)) {
                    $row['file_path'] = null;
                }
            }
            if (!$row) {
                return null;
            }

            $filePath = isset($row['file_path']) && $row['file_path'] !== null
                ? (string) $row['file_path']
                : null;

            $binary = '';
            if ($filePath) {
                $abs = self::resolvePublicPath($filePath);
                if ($abs !== null && is_file($abs)) {
                    $contents = file_get_contents($abs);
                    $binary = is_string($contents) ? $contents : '';
                }
            }
            if ($binary === '') {
                $binary = self::normalizeBytea($row['file_data'] ?? null);
            }
            if ($binary === '') {
                return null;
            }

            return [
                'id'              => (int) $row['id'],
                'mime_type'       => (string) ($row['mime_type'] ?: 'application/octet-stream'),
                'file_name'       => (string) ($row['file_name'] ?: 'media.bin'),
                'file_extension'  => $row['file_extension'] !== null ? (string) $row['file_extension'] : null,
                'file_size_bytes' => $row['file_size_bytes'] !== null ? (int) $row['file_size_bytes'] : strlen($binary),
                'file_data'       => $binary,
                'file_path'       => $filePath,
                'public_url'      => $row['public_url'] !== null ? (string) $row['public_url'] : null,
                'caption'         => $row['caption'] !== null ? (string) $row['caption'] : null,
                'title'           => $row['title'] !== null ? (string) $row['title'] : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Guarda archivo ya escrito en disco (sin BYTEA pesado).
     *
     * @param array{
     *   temp_path:string,
     *   files_dir:string,
     *   relative_dir?:string,
     *   public_via?:string,
     *   mime_type?:string,
     *   file_name?:string,
     *   file_extension?:string,
     *   file_size_bytes?:int,
     *   platform?:string,
     *   purpose?:string,
     *   source_node?:string,
     *   caption?:?string,
     *   title?:?string,
     *   hashtags?:array<int,string>|string
     * } $data
     * @return array{id:int,image_url:string,public_url:string,file_path:string}|null
     */
    public static function createFromFile(array $data): ?array
    {
        self::$lastCreateError = null;

        $tempPath = (string) ($data['temp_path'] ?? '');
        $filesDir = (string) ($data['files_dir'] ?? '');
        if ($tempPath === '' || !is_file($tempPath) || $filesDir === '') {
            self::$lastCreateError = 'temp_path inválido';
            return null;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            self::$lastCreateError = Database::lastError() ?? 'Sin PDO';
            return null;
        }

        self::ensureFilePathColumn($pdo);

        $mime = trim((string) ($data['mime_type'] ?? 'image/png')) ?: 'image/png';
        $fileName = trim((string) ($data['file_name'] ?? 'data.png')) ?: 'data.png';
        $ext = trim((string) ($data['file_extension'] ?? 'png')) ?: 'png';
        $size = (int) ($data['file_size_bytes'] ?? filesize($tempPath) ?: 0);
        $platform = trim((string) ($data['platform'] ?? 'instagram')) ?: 'instagram';
        $purpose = trim((string) ($data['purpose'] ?? 'post_image')) ?: 'post_image';
        $sourceNode = trim((string) ($data['source_node'] ?? 'n8n')) ?: null;
        $caption = array_key_exists('caption', $data) ? ($data['caption'] !== null ? (string) $data['caption'] : null) : null;
        $title = array_key_exists('title', $data) ? ($data['title'] !== null ? (string) $data['title'] : null) : null;

        $hashtags = $data['hashtags'] ?? [];
        if (is_string($hashtags)) {
            $decoded = json_decode($hashtags, true);
            $hashtags = is_array($decoded) ? $decoded : preg_split('/\s+/', trim($hashtags)) ?: [];
        }
        if (!is_array($hashtags)) {
            $hashtags = [];
        }
        $hashtagsJson = json_encode(array_values($hashtags), JSON_UNESCAPED_UNICODE) ?: '[]';
        $metadataJson = json_encode([
            'storage' => 'disk',
            'uploaded_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO arya_media_assets (
                    platform, purpose, source_node,
                    file_name, mime_type, file_extension, file_size_bytes, file_data, file_path,
                    public_url, caption, title, hashtags, metadata
                 ) VALUES (
                    :platform, :purpose, :source_node,
                    :file_name, :mime_type, :file_extension, :file_size_bytes, NULL, NULL,
                    NULL, :caption, :title, CAST(:hashtags AS JSONB), CAST(:metadata AS JSONB)
                 )
                 RETURNING id'
            );

            $stmt->bindValue('platform', $platform);
            $stmt->bindValue('purpose', $purpose);
            $stmt->bindValue('source_node', $sourceNode);
            $stmt->bindValue('file_name', $fileName);
            $stmt->bindValue('mime_type', $mime);
            $stmt->bindValue('file_extension', $ext);
            $stmt->bindValue('file_size_bytes', $size, \PDO::PARAM_INT);
            if ($caption === null) {
                $stmt->bindValue('caption', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue('caption', $caption);
            }
            if ($title === null) {
                $stmt->bindValue('title', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue('title', $title);
            }
            $stmt->bindValue('hashtags', $hashtagsJson);
            $stmt->bindValue('metadata', $metadataJson);
            $stmt->execute();

            $id = (int) $stmt->fetchColumn();
            if ($id <= 0) {
                self::$lastCreateError = 'INSERT sin id';
                return null;
            }

            $finalName = $id . '.' . $ext;
            $finalPath = rtrim($filesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $finalName;
            if (!@rename($tempPath, $finalPath)) {
                if (!@copy($tempPath, $finalPath)) {
                    self::$lastCreateError = 'No se pudo renombrar archivo final';
                    return null;
                }
                @unlink($tempPath);
            }

            $relativeDir = trim((string) ($data['relative_dir'] ?? 'gen_redes/files'), '/');
            $publicVia = (string) ($data['public_via'] ?? 'static');
            $relative = $relativeDir . '/' . $finalName;

            // Si está fuera de /public, Instagram usa media.php
            $publicUrl = $publicVia === 'media'
                ? MediaUrl::forId($id)
                : MediaUrl::forStaticFile($relative);

            $upd = $pdo->prepare(
                'UPDATE arya_media_assets
                 SET file_path = :file_path, public_url = :public_url, updated_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'file_path'  => $relative,
                'public_url' => $publicUrl,
                'id'         => $id,
            ]);

            return [
                'id'         => $id,
                'image_url'  => $publicUrl,
                'public_url' => $publicUrl,
                'file_path'  => $relative,
            ];
        } catch (\Throwable $e) {
            self::$lastCreateError = $e->getMessage();
            return null;
        }
    }

    private static function ensureFilePathColumn(\PDO $pdo): void
    {
        try {
            $pdo->exec('ALTER TABLE arya_media_assets ALTER COLUMN file_data DROP NOT NULL');
        } catch (\Throwable) {
        }
        try {
            $pdo->exec('ALTER TABLE arya_media_assets ADD COLUMN IF NOT EXISTS file_path TEXT');
        } catch (\Throwable) {
        }
    }

    private static function resolvePublicPath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

        $candidates = [
            $root . '/public/' . $relative,
            $root . '/' . $relative,
        ];
        foreach ($candidates as $abs) {
            if (is_file($abs)) {
                return $abs;
            }
        }
        return null;
    }

    public static function publicUrl(int $id): string
    {
        return MediaUrl::forId($id);
    }

    /**
     * Inserta un media y devuelve id + URL pública.
     *
     * @param array{
     *   file_data:string,
     *   mime_type?:string,
     *   file_name?:string,
     *   file_extension?:string,
     *   platform?:string,
     *   purpose?:string,
     *   source_node?:string,
     *   caption?:string,
     *   title?:string,
     *   hashtags?:array<int,string>|string,
     *   metadata?:array<string,mixed>
     * } $data
     * @return array{id:int,image_url:string,public_url:string}|null
     */
    public static function create(array $data): ?array
    {
        $binary = (string) ($data['file_data'] ?? '');
        if ($binary === '') {
            return null;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return null;
        }

        $mime = trim((string) ($data['mime_type'] ?? 'image/png')) ?: 'image/png';
        $fileName = trim((string) ($data['file_name'] ?? 'data.png')) ?: 'data.png';
        $ext = trim((string) ($data['file_extension'] ?? ''));
        if ($ext === '') {
            $ext = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'png';
        }
        $platform = trim((string) ($data['platform'] ?? 'instagram')) ?: 'instagram';
        $purpose = trim((string) ($data['purpose'] ?? 'post_image')) ?: 'post_image';
        $sourceNode = trim((string) ($data['source_node'] ?? 'n8n')) ?: null;
        $caption = isset($data['caption']) ? (string) $data['caption'] : null;
        $title = isset($data['title']) ? (string) $data['title'] : null;

        $hashtags = $data['hashtags'] ?? [];
        if (is_string($hashtags)) {
            $decoded = json_decode($hashtags, true);
            $hashtags = is_array($decoded) ? $decoded : preg_split('/\s+/', trim($hashtags)) ?: [];
        }
        if (!is_array($hashtags)) {
            $hashtags = [];
        }
        $hashtagsJson = json_encode(array_values($hashtags), JSON_UNESCAPED_UNICODE) ?: '[]';

        $metadata = $data['metadata'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: '{}';

        try {
            // BYTEA vía hex (más compatible que base64 largo en algunos hosts)
            $hex = bin2hex($binary);

            $stmt = $pdo->prepare(
                'INSERT INTO arya_media_assets (
                    platform, purpose, source_node,
                    file_name, mime_type, file_extension, file_size_bytes, file_data,
                    public_url, caption, title, hashtags, metadata
                 ) VALUES (
                    :platform, :purpose, :source_node,
                    :file_name, :mime_type, :file_extension, :file_size_bytes,
                    decode(:file_hex, \'hex\'),
                    NULL, :caption, :title, CAST(:hashtags AS JSONB), CAST(:metadata AS JSONB)
                 )
                 RETURNING id'
            );

            $stmt->bindValue('platform', $platform);
            $stmt->bindValue('purpose', $purpose);
            $stmt->bindValue('source_node', $sourceNode);
            $stmt->bindValue('file_name', $fileName);
            $stmt->bindValue('mime_type', $mime);
            $stmt->bindValue('file_extension', $ext);
            $stmt->bindValue('file_size_bytes', strlen($binary), \PDO::PARAM_INT);
            $stmt->bindValue('file_hex', $hex);
            if ($caption === null) {
                $stmt->bindValue('caption', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue('caption', $caption);
            }
            if ($title === null) {
                $stmt->bindValue('title', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue('title', $title);
            }
            $stmt->bindValue('hashtags', $hashtagsJson);
            $stmt->bindValue('metadata', $metadataJson);
            $stmt->execute();

            $id = (int) $stmt->fetchColumn();
            if ($id <= 0) {
                return null;
            }

            $url = self::ensurePublicUrl($id) ?? MediaUrl::forId($id);

            return [
                'id'         => $id,
                'image_url'  => $url,
                'public_url' => $url,
            ];
        } catch (\Throwable $e) {
            self::$lastCreateError = $e->getMessage();
            return null;
        }
    }

    /**
     * Guarda/actualiza public_url en la fila y la devuelve.
     */
    public static function ensurePublicUrl(int $id): ?string
    {
        $row = self::find($id);
        if (!$row) {
            return null;
        }

        $url = MediaUrl::forId($id);
        if (($row['public_url'] ?? '') === $url) {
            return $url;
        }

        $pdo = Database::connection();
        if (!$pdo) {
            return $url;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE arya_media_assets
                 SET public_url = :url, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'url' => $url,
                'id'  => $id,
            ]);
        } catch (\Throwable) {
            // devolver URL aunque no se pueda persistir
        }

        return $url;
    }

    /**
     * Sirve el binario con headers listos para Instagram/Graph API.
     * @return never
     */
    public static function stream(int $id): void
    {
        $row = self::find($id);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'Media no encontrada'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Persistir URL pública si falta
        self::ensurePublicUrl($id);

        $binary = $row['file_data'];
        $mime = $row['mime_type'] ?: 'application/octet-stream';
        $name = $row['file_name'] ?: 'media.bin';
        $size = strlen($binary);

        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) $size);
        header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header('Access-Control-Allow-Origin: *');

        echo $binary;
        exit;
    }

    private static function normalizeBytea(mixed $data): string
    {
        if ($data === null) {
            return '';
        }

        if (is_resource($data)) {
            $contents = stream_get_contents($data);
            return is_string($contents) ? $contents : '';
        }

        if (!is_string($data)) {
            return '';
        }

        // Algunos drivers devuelven hex \x....
        if (str_starts_with($data, '\\x') || str_starts_with($data, '\x')) {
            $hex = preg_replace('/^\\\\?x/i', '', $data) ?? '';
            $decoded = @hex2bin($hex);
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $data;
    }
}
