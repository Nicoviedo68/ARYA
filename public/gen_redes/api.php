<?php
/**
 * gen_redes — UN SOLO endpoint
 *
 * POST JSON  → guarda imagen y devuelve image_url
 *   { "file_base64":"...", "mime_type":"image/png", "file_name":"x.png", "caption":"...", "title":"..." }
 *
 * GET ?id=12 → sirve la imagen (para Instagram)
 *
 * URL:
 *   https://app.aisscol.com/Arya/public/gen_redes/api.php
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function j($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$BASE = dirname(__DIR__, 2); // /Arya
$STORAGE = $BASE . '/storage/gen_redes';
$ENV = $BASE . '/.env';

if (!is_dir($STORAGE)) {
    @mkdir($STORAGE, 0775, true);
}

/**
 * URL pública REAL (nunca localhost del .env).
 * Resultado: https://app.aisscol.com/Arya/public/gen_redes/api.php?id=X
 */
function public_image_url($id) {
    $id = (int) $id;
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';

    // Si el request llega al dominio real, armar desde ahí
    if ($host !== '' && strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        $scheme = $https ? 'https' : 'http';
        $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '/Arya/public/gen_redes/api.php';
        // SCRIPT_NAME = /Arya/public/gen_redes/api.php
        return $scheme . '://' . $host . $script . '?id=' . $id;
    }

    // Fallback fijo producción
    return 'https://app.aisscol.com/Arya/public/gen_redes/api.php?id=' . $id;
}

function load_env_db($file) {
    $db = array(
        'host' => '127.0.0.1',
        'port' => '5432',
        'name' => 'Arya',
        'user' => '',
        'pass' => '',
        'sslmode' => 'disable',
    );
    if (!is_file($file)) return $db;
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return $db;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (strlen($v) >= 2) {
            $a = $v[0]; $b = substr($v, -1);
            if (($a === '"' && $b === '"') || ($a === "'" && $b === "'")) {
                $v = substr($v, 1, -1);
            }
        }
        if ($k === 'DB_HOST') $db['host'] = $v;
        if ($k === 'DB_PORT') $db['port'] = $v;
        if ($k === 'DB_NAME') $db['name'] = $v;
        if ($k === 'DB_USER') $db['user'] = $v;
        if ($k === 'DB_PASSWORD') $db['pass'] = $v;
        if ($k === 'DB_SSLMODE') $db['sslmode'] = $v;
        // APP_URL del .env se IGNORA a propósito (suele ser localhost)
        if ($k === 'DATABASE_URL' && $v !== '') {
            $p = parse_url($v);
            if (is_array($p)) {
                if (!empty($p['host'])) $db['host'] = $p['host'];
                if (!empty($p['port'])) $db['port'] = (string) $p['port'];
                if (!empty($p['user'])) $db['user'] = urldecode($p['user']);
                if (isset($p['pass'])) $db['pass'] = urldecode($p['pass']);
                if (!empty($p['path'])) $db['name'] = ltrim($p['path'], '/');
            }
        }
    }
    return $db;
}

function db_connect($ENV) {
    $db = load_env_db($ENV);
    if ($db['user'] === '') {
        j(500, array('ok' => false, 'message' => 'Falta DB_USER en .env'));
    }
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $db['host'], $db['port'], $db['name'], $db['sslmode']);
    return new PDO($dsn, $db['user'], $db['pass'], array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
}

// -------------------- GET: servir imagen --------------------
if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        j(200, array(
            'ok' => true,
            'service' => 'gen_redes',
            'usage' => array(
                'upload' => 'POST JSON {file_base64, mime_type, file_name, caption, title}',
                'image'  => 'GET ?id=123',
            ),
        ));
    }

    try {
        $pdo = db_connect($ENV);
        $stmt = $pdo->prepare('SELECT id, mime_type, file_name, file_path, file_extension FROM arya_media_assets WHERE id = :id LIMIT 1');
        $stmt->execute(array('id' => $id));
        $row = $stmt->fetch();
        if (!$row) {
            j(404, array('ok' => false, 'message' => 'No existe id ' . $id));
        }

        $path = '';
        if (!empty($row['file_path'])) {
            $rel = ltrim(str_replace('\\', '/', $row['file_path']), '/');
            $cand = array($BASE . '/' . $rel, $BASE . '/public/' . $rel, $STORAGE . '/' . basename($rel));
            foreach ($cand as $c) {
                if (is_file($c)) { $path = $c; break; }
            }
        }
        if ($path === '') {
            $ext = !empty($row['file_extension']) ? $row['file_extension'] : 'png';
            $guess = $STORAGE . '/' . $id . '.' . $ext;
            if (is_file($guess)) $path = $guess;
        }
        if ($path === '' || !is_file($path)) {
            j(404, array('ok' => false, 'message' => 'Archivo no encontrado en disco', 'id' => $id));
        }

        $mime = !empty($row['mime_type']) ? $row['mime_type'] : 'image/png';
        $size = filesize($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) $size);
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    } catch (Throwable $e) {
        j(500, array('ok' => false, 'message' => $e->getMessage(), 'where' => 'GET'));
    }
}

// -------------------- POST: subir imagen --------------------
if ($method !== 'POST') {
    j(405, array('ok' => false, 'message' => 'Usa POST o GET ?id='));
}

try {
    if (!is_dir($STORAGE) || !is_writable($STORAGE)) {
        j(500, array('ok' => false, 'message' => 'storage/gen_redes no escribible', 'path' => $STORAGE));
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        j(422, array('ok' => false, 'message' => 'Body vacío. Envía JSON con file_base64'));
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        j(422, array('ok' => false, 'message' => 'JSON inválido'));
    }

    $b64 = '';
    if (!empty($json['file_base64'])) $b64 = (string) $json['file_base64'];
    elseif (!empty($json['data_base64'])) $b64 = (string) $json['data_base64'];
    elseif (!empty($json['base64'])) $b64 = (string) $json['base64'];

    if (strpos($b64, 'base64,') !== false) {
        $p = explode('base64,', $b64, 2);
        $b64 = isset($p[1]) ? $p[1] : '';
    }
    $b64 = preg_replace('/\s+/', '', $b64);
    $binary = base64_decode($b64, true);
    if ($binary === false || $binary === '') {
        j(422, array('ok' => false, 'message' => 'file_base64 inválido'));
    }

    $mime = !empty($json['mime_type']) ? trim((string) $json['mime_type']) : 'image/png';
    $fileName = !empty($json['file_name']) ? trim((string) $json['file_name']) : 'instagram.png';
    $caption = isset($json['caption']) ? (string) $json['caption'] : null;
    $title = isset($json['title']) ? (string) $json['title'] : null;
    $platform = !empty($json['platform']) ? (string) $json['platform'] : 'instagram';
    $purpose = !empty($json['purpose']) ? (string) $json['purpose'] : 'post_image';

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = (strpos($mime, 'jpg') !== false || strpos($mime, 'jpeg') !== false) ? 'jpg'
            : ((strpos($mime, 'webp') !== false) ? 'webp' : 'png');
    }

    $pdo = db_connect($ENV);

    try { $pdo->exec('ALTER TABLE arya_media_assets ALTER COLUMN file_data DROP NOT NULL'); } catch (Exception $e) {}
    try { $pdo->exec('ALTER TABLE arya_media_assets ADD COLUMN IF NOT EXISTS file_path TEXT'); } catch (Exception $e) {}

    $hashtags = isset($json['hashtags']) && is_array($json['hashtags']) ? $json['hashtags'] : array();
    $stmt = $pdo->prepare(
        'INSERT INTO arya_media_assets (
            platform, purpose, source_node,
            file_name, mime_type, file_extension, file_size_bytes, file_data, file_path,
            public_url, caption, title, hashtags, metadata
         ) VALUES (
            :platform, :purpose, :source_node,
            :file_name, :mime_type, :ext, :size, NULL, NULL,
            NULL, :caption, :title, CAST(:hashtags AS JSONB), CAST(:metadata AS JSONB)
         ) RETURNING id'
    );
    $stmt->bindValue(':platform', $platform);
    $stmt->bindValue(':purpose', $purpose);
    $stmt->bindValue(':source_node', 'n8n');
    $stmt->bindValue(':file_name', $fileName);
    $stmt->bindValue(':mime_type', $mime);
    $stmt->bindValue(':ext', $ext);
    $stmt->bindValue(':size', strlen($binary), PDO::PARAM_INT);
    if ($caption === null) $stmt->bindValue(':caption', null, PDO::PARAM_NULL); else $stmt->bindValue(':caption', $caption);
    if ($title === null) $stmt->bindValue(':title', null, PDO::PARAM_NULL); else $stmt->bindValue(':title', $title);
    $stmt->bindValue(':hashtags', json_encode(array_values($hashtags)));
    $stmt->bindValue(':metadata', json_encode(array('via' => 'api.php')));
    $stmt->execute();
    $id = (int) $stmt->fetchColumn();
    if ($id <= 0) {
        j(500, array('ok' => false, 'message' => 'INSERT sin id'));
    }

    $final = $STORAGE . '/' . $id . '.' . $ext;
    if (file_put_contents($final, $binary) === false) {
        j(500, array('ok' => false, 'message' => 'No se pudo guardar archivo', 'path' => $final));
    }

    $imageUrl = public_image_url($id);
    $rel = 'storage/gen_redes/' . $id . '.' . $ext;
    $upd = $pdo->prepare('UPDATE arya_media_assets SET file_path = :fp, public_url = :url, updated_at = NOW() WHERE id = :id');
    $upd->execute(array('fp' => $rel, 'url' => $imageUrl, 'id' => $id));

    j(200, array(
        'ok' => true,
        'id' => $id,
        'image_url' => $imageUrl,
        'public_url' => $imageUrl,
        'caption' => $caption,
        'title' => $title,
        'bytes' => strlen($binary),
    ));
} catch (Throwable $e) {
    j(500, array(
        'ok' => false,
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ));
}
