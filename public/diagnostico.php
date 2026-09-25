<?php

declare(strict_types=1);

/**
 * Diagnóstico autónomo — no depende del router.
 * URL: https://app.aisscol.com/Arya/public/diagnostico.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: text/html; charset=utf-8');

$basePath = dirname(__DIR__);
$errors = [];
$ok = [];
$installMsg = null;

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadEnvFile(string $path): array
{
    $out = [];
    if (!is_file($path)) {
        return $out;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#') || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $out[trim($k)] = trim(trim($v), "\"'");
    }
    return $out;
}

try {
    $env = loadEnvFile($basePath . '/.env');

    $host = $env['DB_HOST'] ?? $env['SUPABASE_DB_HOST'] ?? '';
    $port = $env['DB_PORT'] ?? $env['SUPABASE_DB_PORT'] ?? '5432';
    $name = $env['DB_NAME'] ?? $env['SUPABASE_DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? $env['SUPABASE_DB_USER'] ?? '';
    $pass = $env['DB_PASSWORD'] ?? $env['SUPABASE_DB_PASSWORD'] ?? '';
    $ssl  = $env['DB_SSLMODE'] ?? 'disable';

    $ok[] = 'PHP ' . PHP_VERSION;
    $ok[] = 'Archivo .env: ' . (is_file($basePath . '/.env') ? 'encontrado' : 'NO encontrado');

    if (!extension_loaded('pdo_pgsql')) {
        $errors[] = 'Falta extensión pdo_pgsql en PHP. Sin esto Arya no puede hablar con PostgreSQL.';
    } else {
        $ok[] = 'Extensión pdo_pgsql: OK';
    }

    // Mostrar qué leyó del .env (sin revelar password completo)
    $ok[] = 'DB_HOST leído: ' . ($host !== '' ? $host : '(vacío)');
    $ok[] = 'DB_NAME leído: ' . ($name !== '' ? $name : '(vacío)');
    $ok[] = 'DB_USER leído: ' . ($user !== '' ? $user : '(vacío)');
    $ok[] = 'DB_PASSWORD leído: ' . ($pass !== '' ? '(definida, ' . strlen($pass) . ' chars)' : '(vacía)');

    $pdo = null;
    $dbOk = false;
    $credsOk = ($host !== '' && $user !== '' && $pass !== '' && $name !== '');

    if (!$credsOk) {
        $errors[] = 'Credenciales incompletas en .env (faltan DB_HOST / DB_USER / DB_PASSWORD / DB_NAME). Sube el .env actualizado.';
    }

    if ($credsOk && extension_loaded('pdo_pgsql')) {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=%s', $host, $port, $name, $ssl);
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $ver = (string) $pdo->query('SELECT version()')->fetchColumn();
            $dbOk = true;
            $ok[] = 'Conexión PostgreSQL: OK';
            $ok[] = 'Host: ' . $host . ' | DB: ' . $name . ' | User: ' . $user;
            $ok[] = $ver;
        } catch (Throwable $e) {
            $errors[] = 'Error de conexión DB: ' . $e->getMessage();
            $errors[] = "DSN usado: host={$host}; port={$port}; dbname={$name}";
            $errors[] = 'Arya está en aisscol y Postgres en EasyPanel: usa la IP pública del VPS EasyPanel (ej. 168.231.68.157) y el puerto expuesto en Remote Access. No uses cortech_arya.';
        }
    } elseif ($credsOk && !extension_loaded('pdo_pgsql')) {
        $errors[] = 'Las credenciales se leyeron, pero no se puede conectar hasta activar pdo_pgsql.';
    }

    $tableExists = false;
    if ($pdo) {
        try {
            $pdo->query('SELECT 1 FROM arya_users LIMIT 1');
            $tableExists = true;
            $ok[] = 'Tabla arya_users: existe';
        } catch (Throwable $e) {
            $ok[] = 'Tabla arya_users: aún no existe (normal si la DB está vacía)';
        }
    }

    // Instalar esquema
    if ($pdo && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['install'])) {
        $schemaFile = $basePath . '/database/schema.sql';
        if (!is_file($schemaFile)) {
            $installMsg = 'No se encontró database/schema.sql';
        } else {
            try {
                $sql = (string) file_get_contents($schemaFile);
                $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
                $pdo->exec($sql);

                $hash = password_hash('arya2026', PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO arya_users (name, email, password_hash, role)
                     VALUES (:name, :email, :hash, :role)
                     ON CONFLICT (email) DO UPDATE SET
                       password_hash = EXCLUDED.password_hash,
                       name = EXCLUDED.name,
                       updated_at = NOW()'
                );
                $stmt->execute([
                    'name'  => 'Administrador Arya',
                    'email' => 'admin@arya.crm',
                    'hash'  => $hash,
                    'role'  => 'admin',
                ]);
                $installMsg = 'OK: tablas creadas y admin listo (admin@arya.crm / arya2026)';
                $tableExists = true;
            } catch (Throwable $e) {
                $installMsg = 'Error al instalar: ' . $e->getMessage();
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Error fatal: ' . $e->getMessage();
    $errors[] = $e->getFile() . ':' . $e->getLine();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Arya — Diagnóstico</title>
  <style>
    body{font-family:system-ui,sans-serif;background:#0a192f;color:#e6f1ff;margin:0;padding:24px}
    .box{max-width:720px;margin:0 auto;background:#112240;border:1px solid rgba(0,240,255,.2);border-radius:14px;padding:24px}
    h1{margin:0 0 8px;color:#00f0ff}
    .muted{color:#8892b0;margin-bottom:20px}
    li{margin:6px 0}
    .ok{color:#3ddc97}.err{color:#ff6b6b}.info{color:#c5a059}
    button,.btn{display:inline-block;background:#00f0ff;color:#041018;border:0;padding:12px 18px;border-radius:8px;font-weight:700;cursor:pointer;text-decoration:none}
    button:disabled{opacity:.4;cursor:not-allowed}
    .msg{margin:16px 0;padding:12px;border-radius:8px;background:rgba(0,0,0,.25)}
  </style>
</head>
<body>
  <div class="box">
    <h1>Arya · Diagnóstico</h1>
    <p class="muted">Prueba de PHP + PostgreSQL EasyPanel</p>

    <?php if ($installMsg): ?>
      <div class="msg <?= strpos($installMsg, 'OK') === 0 ? 'ok' : 'err' ?>"><?= h($installMsg) ?></div>
    <?php endif; ?>

    <h3 class="ok">Estado</h3>
    <ul>
      <?php foreach ($ok as $line): ?>
        <li class="ok"><?= h($line) ?></li>
      <?php endforeach; ?>
    </ul>

    <?php if ($errors): ?>
      <h3 class="err">Errores</h3>
      <ul>
        <?php foreach ($errors as $line): ?>
          <li class="err"><?= h($line) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" style="margin-top:20px">
      <button type="submit" name="install" value="1" <?= empty($dbOk) ? 'disabled' : '' ?>>
        Instalar tablas + crear admin
      </button>
    </form>

    <p style="margin-top:20px">
      <a class="btn" href="login.php">Ir al login</a>
      <a class="btn" style="background:#c5a059;margin-left:8px" href="ping.php">ping.php</a>
    </p>
  </div>
</body>
</html>
