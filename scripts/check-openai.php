<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;

$pdo = Database::connection();
if (!$pdo) {
    echo "ERROR: sin conexión DB\n";
    exit(1);
}

echo "Tabla: maestro_integraciones\n";
echo "Host DB: " . (string) config('database.host') . " / " . (string) config('database.name') . "\n\n";

$stmt = $pdo->query(
    "SELECT id, codigo, nombre, tipo, estado, endpoint_url,
            CASE WHEN api_key IS NOT NULL AND api_key <> '' THEN 'SI (***' || right(api_key, 4) || ')' ELSE 'NO' END AS api_key_mask,
            notas, activo, created_at, updated_at
     FROM maestro_integraciones
     WHERE lower(codigo) LIKE '%openai%'
        OR lower(nombre) LIKE '%openai%'
        OR lower(codigo) LIKE '%openia%'
        OR lower(nombre) LIKE '%openia%'
        OR lower(codigo) LIKE '%open%'
     ORDER BY id DESC"
);
$rows = $stmt->fetchAll();

if (!$rows) {
    echo "No encontré OpenAI por nombre/código. Listando TODAS las integraciones activas:\n\n";
    $all = $pdo->query(
        "SELECT id, codigo, nombre, tipo, estado, endpoint_url,
                CASE WHEN api_key IS NOT NULL AND api_key <> '' THEN 'SI' ELSE 'NO' END AS tiene_key,
                activo, created_at
         FROM maestro_integraciones
         ORDER BY id DESC"
    )->fetchAll();
    foreach ($all as $r) {
        echo sprintf(
            "#%s | %s | %s | %s | key=%s | activo=%s | %s\n",
            $r['id'],
            $r['codigo'],
            $r['nombre'],
            $r['estado'],
            $r['tiene_key'],
            $r['activo'] ? 'true' : 'false',
            $r['created_at']
        );
    }
    exit(0);
}

foreach ($rows as $r) {
    echo "✔ Encontrada:\n";
    foreach ($r as $k => $v) {
        echo "  {$k}: " . (is_bool($v) ? ($v ? 'true' : 'false') : (string) $v) . "\n";
    }
    echo "\n";
}
