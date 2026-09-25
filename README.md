# Arya CRM

CRM omnicanal en PHP 8.2+ con arquitectura MVC ligera, listo para conectar a Supabase y N8N.

## Módulos

| Módulo | Ruta | Descripción |
|--------|------|-------------|
| Dashboard | `/dashboard` | KPIs y resumen operativo |
| Omnicanalidad | `/omnicanalidad` | Inbox WhatsApp, Facebook, Instagram, Web, Email |
| Clientes | `/clientes` | Ficha, pedidos y datos clave |
| Tareas programadas | `/tareas` | Workflows N8N |
| Generador | `/generador` | Contenido para redes |
| Informes | `/informes` | Gráficos, KPIs y export CSV/Excel |
| Configuración | `/configuracion` | Admin, cuentas e integraciones |

## Requisitos

- PHP 8.2+
- Extensiones: `pdo`, `pdo_pgsql` (cuando conectes Supabase), `mbstring`, `json`
- Apache con `mod_rewrite` **o** servidor embebido de PHP

## Arranque rápido (local)

```powershell
cd C:\Users\Desarrollo\Documents\Asellerator\Arya\Arya
# Si no existe .env:
copy .env.example .env
# Arranque:
.\start-local.ps1
# o:
php -S localhost:8080 -t public
```

Abre: http://localhost:8080

En `.env` local usa:
- `APP_ENV=local`
- `APP_URL=http://localhost:8080`
- `APP_FORCE_INDEX_PHP=false`
- `APP_DEBUG=true`

### Credenciales demo (sin DB)

- Email: `admin@arya.crm`
- Password: `arya2026`

Con PostgreSQL accesible, inicia sesión con un usuario de `maestrousuario`.

## Apache (XAMPP / Laragon)

Document root → carpeta `public/`, o virtual host apuntando a `public`.

Ajusta `APP_URL` en `.env` si usas subcarpeta, por ejemplo:

```
APP_URL=http://localhost/Arya/public
```

Si dejas `APP_URL` vacío, la app detecta la URL base automáticamente.

## Estructura

```
Arya/
├── app/
│   ├── Config/          # Configuración
│   ├── Controllers/     # Controladores por módulo
│   ├── Core/            # Router, View, Database, Controller
│   ├── Helpers/         # helpers + MockData
│   ├── Middleware/      # Auth / Guest
│   └── Views/           # Plantillas PHP
├── public/              # Front controller + assets
├── routes/web.php
├── storage/
├── imagenes/            # Logo original
└── .env
```

## Branding

Paleta tomada del logo Arya:

- Midnight `#0A192F`
- Gold `#C5A059`
- Cyan `#00F0FF`
- Fondo `#05080f`

## Próximos pasos

1. Conectar Supabase (PostgreSQL + Auth) vía `.env`
2. Integrar webhooks N8N para tareas programadas
3. Conectar APIs de Meta / WhatsApp Business para omnicanalidad real
4. Sustituir el generador demo por IA / workflow N8N

## Seguridad incluida

- CSRF en formularios POST
- Sesiones con `httponly` + `SameSite=Lax`
- Escape XSS con helper `e()`
- Middleware de autenticación en rutas privadas
- Stub PDO listo para PostgreSQL/Supabase
