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
    echo "no_db\n";
    exit(1);
}

$queries = [
    'omni_conversations' => 'SELECT COUNT(*) FROM omni_conversations',
    'omni_messages' => 'SELECT COUNT(*) FROM omni_messages',
    'arya_clients' => 'SELECT COUNT(*) FROM arya_clients',
    'open_chats' => "SELECT COUNT(*) FROM omni_conversations WHERE status IN ('open','pending') OR COALESCE(unread_count,0) > 0",
    'today_msgs' => "SELECT COUNT(*) FROM omni_messages WHERE created_at >= date_trunc('day', NOW() AT TIME ZONE 'America/Bogota') AT TIME ZONE 'America/Bogota'",
    'today_convs' => "SELECT COUNT(*) FROM omni_conversations WHERE last_message_at >= date_trunc('day', NOW() AT TIME ZONE 'America/Bogota') AT TIME ZONE 'America/Bogota'",
];

foreach ($queries as $k => $sql) {
    try {
        echo $k . '=' . $pdo->query($sql)->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo $k . '=ERR ' . $e->getMessage() . "\n";
    }
}

try {
    $pdo->query('SELECT 1 FROM arya_tasks LIMIT 1');
    echo 'arya_tasks=' . $pdo->query('SELECT COUNT(*) FROM arya_tasks')->fetchColumn() . "\n";
    foreach ($pdo->query('SELECT id, name, status FROM arya_tasks LIMIT 5') as $r) {
        echo '  task: ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    echo 'arya_tasks=MISSING ' . $e->getMessage() . "\n";
}

echo "\nby channel last 7d messages:\n";
try {
    $sql = "SELECT c.channel, date_trunc('day', m.created_at AT TIME ZONE 'America/Bogota')::date AS d, COUNT(*) AS n
            FROM omni_messages m
            JOIN omni_conversations c ON c.id = m.conversation_id
            WHERE m.created_at >= NOW() - INTERVAL '7 days'
            GROUP BY 1, 2
            ORDER BY 2, 1";
    foreach ($pdo->query($sql) as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    echo $e->getMessage() . "\n";
}

echo "\nclients by status:\n";
try {
    foreach ($pdo->query('SELECT status, COUNT(*) n FROM arya_clients GROUP BY status') as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    echo $e->getMessage() . "\n";
}
