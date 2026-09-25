<?php

declare(strict_types=1);

/**
 * Aplica menú producción: base + Agenda / citas. Desactiva extras de Arya 2.0.
 *
 * Uso: php scripts/bootstrap-menu.php
 */

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Helpers\Menu;

$ping = Database::ping();
echo 'db_ping=' . ($ping['ok'] ? 'OK' : 'FAIL') . PHP_EOL;
if (!$ping['ok']) {
    echo 'message=' . ($ping['message'] ?? '') . PHP_EOL;
    exit(1);
}

Menu::bootstrap();
Menu::clearCache();

$pdo = Database::connection();
if (!$pdo) {
    echo "db=FAIL\n";
    exit(1);
}

$pdo->exec("UPDATE maestro_menu SET activo = FALSE
            WHERE codigo IN ('pipeline','quotes','campaigns','productivity')");

$stmt = $pdo->query(
    "SELECT codigo, titulo, ruta, grupo, activo
     FROM maestro_menu
     WHERE LOWER(TRIM(COALESCE(app_scope,'crm'))) IN ('crm','all')
       AND codigo NOT LIKE 'ocp_%'
     ORDER BY activo DESC, grupo_orden, orden"
);
$rows = $stmt->fetchAll() ?: [];

$active = array_values(array_filter($rows, static fn ($r) => !empty($r['activo'])));
echo 'crm_active=' . count($active) . PHP_EOL;
foreach ($active as $r) {
    echo sprintf(
        "  [%s] %s → %s | %s\n",
        (string) $r['codigo'],
        (string) $r['titulo'],
        (string) $r['ruta'],
        (string) $r['grupo']
    );
}

$hasAgenda = false;
foreach ($active as $r) {
    if (($r['codigo'] ?? '') === 'appointments') {
        $hasAgenda = true;
        break;
    }
}
echo 'appointments=' . ($hasAgenda ? 'OK' : 'MISSING') . PHP_EOL;

$extras = [];
foreach ($active as $r) {
    if (in_array((string) ($r['codigo'] ?? ''), ['pipeline', 'quotes', 'campaigns', 'productivity'], true)) {
        $extras[] = (string) $r['codigo'];
    }
}
echo 'extras_hidden=' . ($extras === [] ? 'YES' : 'NO:' . implode(',', $extras)) . PHP_EOL;
echo "done\n";
exit(($hasAgenda && $extras === []) ? 0 : 1);
