<?php

declare(strict_types=1);

/**
 * Smoke test mínimo.
 * https://app.aisscol.com/Arya/public/ping.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "Arya ping OK\n";
echo 'PHP ' . PHP_VERSION . "\n";
echo 'pdo_pgsql: ' . (extension_loaded('pdo_pgsql') ? 'yes' : 'NO') . "\n";
echo 'cwd: ' . __DIR__ . "\n";
echo '.env: ' . (is_file(dirname(__DIR__) . '/.env') ? 'yes' : 'NO') . "\n";
echo "\nSi ves este texto, PHP funciona.\n";
echo "Siguiente: /Arya/public/diagnostico.php\n";
