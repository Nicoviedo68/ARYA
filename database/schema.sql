-- Arya CRM — esquema inicial (Supabase / PostgreSQL)
-- Preferible: abrir /index.php/diagnostico y pulsar "Instalar esquema"
-- O ejecutar en Studio SQL Editor.

CREATE TABLE IF NOT EXISTS arya_users (
    id              BIGSERIAL PRIMARY KEY,
    name            TEXT NOT NULL,
    email           TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'admin',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS arya_clients (
    id                   BIGSERIAL PRIMARY KEY,
    name                 TEXT NOT NULL,
    email                TEXT,
    phone                TEXT,
    company              TEXT,
    status               TEXT NOT NULL DEFAULT 'prospecto',
    channel              TEXT DEFAULT 'whatsapp',
    lifetime_value       NUMERIC(14,2) NOT NULL DEFAULT 0,
    last_contact_at      TIMESTAMPTZ,
    tags                 TEXT[] DEFAULT '{}',
    external_contact     VARCHAR(120),
    omni_conversation_id BIGINT,
    source               VARCHAR(40) NOT NULL DEFAULT 'manual',
    document_type        VARCHAR(20),
    document_number      VARCHAR(60),
    address              TEXT,
    city                 VARCHAR(120),
    notes                TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_arya_clients_channel_external
    ON arya_clients (channel, external_contact)
    WHERE external_contact IS NOT NULL;

CREATE TABLE IF NOT EXISTS arya_conversations (
    id              BIGSERIAL PRIMARY KEY,
    client_id       BIGINT REFERENCES arya_clients(id) ON DELETE SET NULL,
    channel         TEXT NOT NULL,
    external_id     TEXT,
    status          TEXT NOT NULL DEFAULT 'open',
    preview         TEXT,
    unread_count    INT NOT NULL DEFAULT 0,
    last_message_at TIMESTAMPTZ DEFAULT NOW(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS arya_messages (
    id              BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES arya_conversations(id) ON DELETE CASCADE,
    sender          TEXT NOT NULL CHECK (sender IN ('client', 'agent', 'system')),
    body            TEXT NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS arya_tasks (
    id              BIGSERIAL PRIMARY KEY,
    name            TEXT NOT NULL,
    workflow        TEXT,
    schedule_cron   TEXT,
    status          TEXT NOT NULL DEFAULT 'activo',
    last_run_at     TIMESTAMPTZ,
    next_run_at     TIMESTAMPTZ,
    success_rate    NUMERIC(5,2) DEFAULT 100,
    n8n_payload     JSONB DEFAULT '{}',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Tokens Meta (WhatsApp / Messenger / Instagram)
CREATE TABLE IF NOT EXISTS token_meta (
    id              BIGSERIAL PRIMARY KEY,
    canal           VARCHAR(40)  NOT NULL,
    page_id         VARCHAR(120) NOT NULL,
    identificador   VARCHAR(255),
    token           TEXT         NOT NULL,
    estado          VARCHAR(30)  NOT NULL DEFAULT 'conectado',
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    CONSTRAINT token_meta_canal_chk CHECK (canal IN ('whatsapp', 'messenger', 'instagram'))
);

CREATE INDEX IF NOT EXISTS idx_token_meta_canal ON token_meta (canal);
CREATE INDEX IF NOT EXISTS idx_token_meta_activo ON token_meta (activo);
CREATE UNIQUE INDEX IF NOT EXISTS uq_token_meta_canal_page ON token_meta (canal, page_id) WHERE activo = TRUE;

-- Webhook Meta Business (callback + inbox de mensajes)
CREATE TABLE IF NOT EXISTS meta_webhook_config (
    id            SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    verify_token  TEXT NOT NULL,
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS meta_webhook_inbox (
    id            BIGSERIAL PRIMARY KEY,
    object_type   VARCHAR(60),
    page_id       VARCHAR(120),
    canal         VARCHAR(40),
    event_type    VARCHAR(80),
    payload       JSONB NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_meta_inbox_created ON meta_webhook_inbox (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_meta_inbox_page ON meta_webhook_inbox (page_id);
ALTER TABLE meta_webhook_inbox ADD COLUMN IF NOT EXISTS processed_at TIMESTAMPTZ;

-- Omnicanalidad (conversaciones / mensajes en producción)
CREATE TABLE IF NOT EXISTS omni_conversations (
    id                 BIGSERIAL PRIMARY KEY,
    channel            VARCHAR(40)  NOT NULL,
    external_contact   VARCHAR(120) NOT NULL,
    contact_name       VARCHAR(150),
    phone              VARCHAR(80),
    page_id            VARCHAR(120),
    phone_number_id    VARCHAR(120),
    preview            TEXT,
    status             VARCHAR(30)  NOT NULL DEFAULT 'open',
    unread_count       INT          NOT NULL DEFAULT 0,
    last_message_at    TIMESTAMPTZ,
    handling_mode      VARCHAR(20)  NOT NULL DEFAULT 'ai',
    assigned_user_id   BIGINT,
    assigned_at        TIMESTAMPTZ,
    assigned_name      VARCHAR(150),
    created_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_omni_conv_channel_contact
    ON omni_conversations (channel, external_contact);
CREATE INDEX IF NOT EXISTS idx_omni_conv_last ON omni_conversations (last_message_at DESC NULLS LAST);

CREATE TABLE IF NOT EXISTS omni_messages (
    id                 BIGSERIAL PRIMARY KEY,
    conversation_id    BIGINT NOT NULL REFERENCES omni_conversations(id) ON DELETE CASCADE,
    direction          VARCHAR(20) NOT NULL DEFAULT 'inbound',
    sender             VARCHAR(20) NOT NULL DEFAULT 'client',
    body               TEXT NOT NULL,
    message_type       VARCHAR(40) NOT NULL DEFAULT 'text',
    external_id        VARCHAR(200),
    raw                JSONB,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_omni_msg_external
    ON omni_messages (external_id) WHERE external_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_omni_msg_conv ON omni_messages (conversation_id, created_at);

-- Otras redes (LinkedIn, TikTok, X, YouTube, etc.)
CREATE TABLE IF NOT EXISTS token_redes (
    id              BIGSERIAL PRIMARY KEY,
    red             VARCHAR(40)  NOT NULL,
    nombre_cuenta   VARCHAR(150) NOT NULL,
    identificador   VARCHAR(255),
    token           TEXT         NOT NULL,
    client_id       VARCHAR(255),
    client_secret   TEXT,
    estado          VARCHAR(30)  NOT NULL DEFAULT 'conectado',
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_token_redes_red ON token_redes (red);
CREATE INDEX IF NOT EXISTS idx_token_redes_activo ON token_redes (activo);

-- Integraciones (n8n, supabase, APIs, etc.)
CREATE TABLE IF NOT EXISTS maestro_integraciones (
    id              BIGSERIAL PRIMARY KEY,
    codigo          VARCHAR(60)  NOT NULL UNIQUE,
    nombre          VARCHAR(120) NOT NULL,
    tipo            VARCHAR(60)  NOT NULL DEFAULT 'servicio',
    estado          VARCHAR(30)  NOT NULL DEFAULT 'inactivo',
    endpoint_url    TEXT,
    api_key         TEXT,
    config          JSONB        NOT NULL DEFAULT '{}',
    notas           TEXT,
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_integraciones_activo ON maestro_integraciones (activo);

INSERT INTO maestro_integraciones (codigo, nombre, tipo, estado, notas)
VALUES
    ('postgresql', 'PostgreSQL / EasyPanel', 'db', 'activo', 'Base de datos principal (maestrousuario).'),
    ('supabase', 'API Supabase', 'api', 'inactivo', 'REST PostgREST vía Kong (opcional).'),
    ('n8n', 'N8N Automatizaciones', 'webhook', 'inactivo', 'Webhooks 2FA y flujos omnicanal.')
ON CONFLICT (codigo) DO NOTHING;
