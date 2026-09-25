<?php

declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require dirname(__DIR__) . '/app/bootstrap.php';

use Arya\Core\Database;
use Arya\Models\Omnichannel;

Omnichannel::bootstrap();
$result = Omnichannel::syncFromMetaInbox();

echo 'SYNC: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$pdo = Database::connection();
if ($pdo) {
    $c = (int) $pdo->query('SELECT COUNT(*) FROM omni_conversations')->fetchColumn();
    $m = (int) $pdo->query('SELECT COUNT(*) FROM omni_messages')->fetchColumn();
    echo "CONVERSATIONS={$c}\nMESSAGES={$m}\n";

    foreach (Omnichannel::conversations('all') as $conv) {
        echo sprintf(
            " - #%s %s | %s | %s | unread=%s\n",
            $conv['id'],
            $conv['channel'],
            $conv['client'],
            $conv['preview'],
            $conv['unread']
        );
    }
}
