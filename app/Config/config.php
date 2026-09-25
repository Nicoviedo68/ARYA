<?php

declare(strict_types=1);

return [
    'app' => [
        'name'            => env('APP_NAME', 'Arya'),
        'env'             => env('APP_ENV', 'local'),
        'debug'           => filter_var(env('APP_DEBUG', true), FILTER_VALIDATE_BOOLEAN),
        'url'             => rtrim((string) env('APP_URL', ''), '/'),
        'key'             => env('APP_KEY', 'arya-dev-key'),
        'timezone'        => 'America/Bogota',
        'locale'          => 'es',
        'force_index_php' => filter_var(env('APP_FORCE_INDEX_PHP', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'session' => [
        'name'     => 'arya_session',
        'lifetime' => (int) env('SESSION_LIFETIME', 480),
    ],

    'database' => [
        'url'      => env('DATABASE_URL', ''),
        'host'     => env('DB_HOST', 'cortech_arya'),
        'port'     => env('DB_PORT', '5432'),
        'name'     => env('DB_NAME', 'Arya'),
        'username' => env('DB_USER', 'nico'),
        'password' => env('DB_PASSWORD', ''),
        'sslmode'  => env('DB_SSLMODE', 'disable'),
    ],

    'supabase' => [
        'url'          => env('SUPABASE_URL', ''),
        'anon_key'     => env('SUPABASE_ANON_KEY', ''),
        'service_key'  => env('SUPABASE_SERVICE_KEY', ''),
        'database_url' => env('DATABASE_URL', ''),
        'db' => [
            'host'     => env('DB_HOST', 'cortech_arya'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_NAME', 'Arya'),
            'username' => env('DB_USER', 'nico'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL', ''),
        'api_key'     => env('N8N_API_KEY', ''),
    ],

    'openai' => [
        'api_key'  => env('OPENAI_API_KEY', ''),
        'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'model'    => env('OPENAI_MODEL', 'gpt-5.6'),
    ],

    'ocp' => [
        // OCP hermano: https://app.aisscol.com/ocp_Arya
        // En runtime usar ocp_public_url(): ignora localhost si el request es producción
        'url'         => rtrim((string) env('OCP_URL', 'https://app.aisscol.com/ocp_Arya'), '/'),
        'label'       => env('OCP_LABEL', 'OCP Arya'),
        'webhook_2fa' => env('OCP_2FA_WEBHOOK', 'https://cortech-n8n.ukd6ph.easypanel.host/webhook/2FA-Arya'),
    ],

    'meta' => [
        'webhook_url'  => env('META_WEBHOOK_URL', 'https://app.aisscol.com/Arya/public/api/meta-webhook.php'),
        'verify_token' => env('META_VERIFY_TOKEN', ''),
        // Handles IG propios a omitir en omnicanal (coma-separado). Ej: cortech_col
        'own_usernames'=> env('META_OWN_USERNAMES', 'cortech_col'),
    ],
];
