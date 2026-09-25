-- Menú del sidebar filtrado por alias y app_scope (crm | ocp | all)
-- Producción Arya — módulos base + Agenda / citas
CREATE TABLE IF NOT EXISTS maestro_menu (
    id              BIGSERIAL PRIMARY KEY,
    codigo          VARCHAR(50)  NOT NULL UNIQUE,
    titulo          VARCHAR(100) NOT NULL,
    ruta            VARCHAR(120) NOT NULL,
    icono           VARCHAR(40)  NOT NULL DEFAULT 'home',
    grupo           VARCHAR(60)  NOT NULL DEFAULT 'principal',
    grupo_orden     INT          NOT NULL DEFAULT 0,
    orden           INT          NOT NULL DEFAULT 0,
    aliases         VARCHAR(120) NOT NULL DEFAULT 'ADMIN,AGENT',
    app_scope       VARCHAR(20)  NOT NULL DEFAULT 'crm',
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

ALTER TABLE maestro_menu ADD COLUMN IF NOT EXISTS app_scope VARCHAR(20) NOT NULL DEFAULT 'crm';

CREATE INDEX IF NOT EXISTS idx_maestro_menu_orden ON maestro_menu (grupo_orden, orden);

-- CRM (producción + Agenda / citas)
INSERT INTO maestro_menu (codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope) VALUES
    ('dashboard',     'Dashboard',          'dashboard',     'home',     'Operación',      1, 10, 'ADMIN,AGENT', 'crm'),
    ('omnichannel',   'Omnicanalidad',      'omnicanalidad', 'chat',     'Operación',      1, 20, 'ADMIN,AGENT', 'crm'),
    ('clients',       'Gestión clientes',   'clientes',      'users',    'Operación',      1, 30, 'ADMIN,AGENT', 'crm'),
    ('appointments',  'Agenda / citas',     'agenda',        'calendar', 'Operación',      1, 40, 'ADMIN,AGENT', 'crm'),
    ('tasks',         'Tareas programadas', 'tareas',        'clock',    'Automatización', 2, 10, 'ADMIN,AGENT', 'crm'),
    ('chatgpt',       'Chat GPT',           'chat-gpt',      'bot',      'Automatización', 2, 15, 'ADMIN,AGENT', 'crm'),
    ('generator',     'Generador',          'generador',     'spark',    'Automatización', 2, 20, 'ADMIN',       'crm'),
    ('reports',       'Informes',           'informes',      'chart',    'Administración', 3, 10, 'ADMIN',       'crm'),
    ('settings',      'Configuración',      'configuracion', 'gear',     'Administración', 3, 20, 'ADMIN',       'crm')
ON CONFLICT (codigo) DO UPDATE SET
    titulo      = EXCLUDED.titulo,
    ruta        = EXCLUDED.ruta,
    icono       = EXCLUDED.icono,
    grupo       = EXCLUDED.grupo,
    grupo_orden = EXCLUDED.grupo_orden,
    orden       = EXCLUDED.orden,
    aliases     = EXCLUDED.aliases,
    app_scope   = EXCLUDED.app_scope,
    activo      = TRUE;

-- Desactivar módulos Comerciales de Arya 2.0 que no aplican a producción
UPDATE maestro_menu SET activo = FALSE
WHERE codigo IN ('pipeline', 'quotes', 'campaigns', 'productivity');

-- OCP
INSERT INTO maestro_menu (codigo, titulo, ruta, icono, grupo, grupo_orden, orden, aliases, app_scope) VALUES
    ('ocp_dashboard', 'Dashboard gerencial',  'dashboard',  'home',   'Gerencia',        1, 10, 'ADMIN', 'ocp'),
    ('ocp_users',     'Usuarios',              'usuarios',   'users',  'Administración',  2, 10, 'ADMIN', 'ocp'),
    ('ocp_menu',      'Gestión menú',         'menu',       'gear',   'Administración',  2, 20, 'ADMIN', 'ocp'),
    ('ocp_roles',     'Roles',                'roles',      'shield', 'Administración',  2, 30, 'ADMIN', 'ocp'),
    ('ocp_reports',   'Informes gerenciales', 'informes',   'chart',  'Gerencia',        1, 20, 'ADMIN', 'ocp'),
    ('ocp_ai',        'Agente IA',            'agente-ia',  'spark',  'Automatización',  3, 10, 'ADMIN', 'ocp')
ON CONFLICT (codigo) DO UPDATE SET
    titulo      = EXCLUDED.titulo,
    ruta        = EXCLUDED.ruta,
    icono       = EXCLUDED.icono,
    grupo       = EXCLUDED.grupo,
    grupo_orden = EXCLUDED.grupo_orden,
    orden       = EXCLUDED.orden,
    aliases     = EXCLUDED.aliases,
    app_scope   = 'ocp',
    activo      = TRUE;
