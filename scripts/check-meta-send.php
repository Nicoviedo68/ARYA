<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTPS'] = 'off';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Models\Omnichannel;
use Arya\Models\TokenMeta;

Omnichannel::bootstrap();

foreach (['whatsapp', 'messenger', 'instagram'] as $canal) {
    $c = TokenMeta::channelCredentials($canal);
    echo $canal . '=' . ($c ? ('OK token_len=' . strlen($c['token']) . ' page=' . $c['page_id']) : 'MISSING') . PHP_EOL;
}

$convs = Omnichannel::conversations('all');
foreach (array_slice($convs, 0, 5) as $c) {
    echo 'conv#' . $c['id'] . ' ' . $c['channel'] . ' kind=' . ($c['thread_kind'] ?? '?')
        . ' client=' . $c['client'] . PHP_EOL;
}
