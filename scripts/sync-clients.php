<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Client;

$n = Client::syncFromOmni();
echo "synced={$n}\n";
foreach (Client::all() as $c) {
    echo $c['id'] . '|' . $c['name'] . '|' . $c['channel'] . '|' . $c['phone'] . "\n";
}
