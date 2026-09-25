<?php

declare(strict_types=1);

namespace Arya\Helpers;

/**
 * Datos de demostración cuando PostgreSQL no está disponible o las tablas están vacías.
 */
final class MockData
{
    public static function user(): array
    {
        return [
            'id'     => 1,
            'name'   => 'Nico',
            'email'  => 'Nico',
            'role'   => 'admin',
            'avatar' => null,
        ];
    }

    public static function dashboardStats(): array
    {
        return [
            'conversations_today' => 47,
            'open_chats'          => 12,
            'clients_active'      => 328,
            'tasks_pending'       => 8,
            'conversion_rate'     => 24.6,
            'avg_response_min'    => 2.4,
        ];
    }

    public static function channels(): array
    {
        return [
            ['id' => 'whatsapp',  'name' => 'WhatsApp',  'icon' => 'whatsapp',  'color' => '#25D366', 'unread' => 5,  'online' => true],
            ['id' => 'facebook',  'name' => 'Facebook',  'icon' => 'facebook',  'color' => '#1877F2', 'unread' => 2,  'online' => true],
            ['id' => 'instagram', 'name' => 'Instagram', 'icon' => 'instagram', 'color' => '#E4405F', 'unread' => 3,  'online' => true],
            ['id' => 'web',       'name' => 'Web Chat',  'icon' => 'globe',     'color' => '#00F0FF', 'unread' => 1,  'online' => true],
            ['id' => 'email',     'name' => 'Email',     'icon' => 'mail',      'color' => '#C5A059', 'unread' => 0,  'online' => false],
        ];
    }

    public static function conversations(): array
    {
        return [
            [
                'id' => 1, 'channel' => 'whatsapp', 'client' => 'María Gómez', 'phone' => '+57 300 123 4567',
                'preview' => 'Hola, quiero información del plan premium…', 'time' => '10:42', 'unread' => 2, 'status' => 'open',
            ],
            [
                'id' => 2, 'channel' => 'instagram', 'client' => 'Carlos Ruiz', 'phone' => '@carlos.ruiz',
                'preview' => '¿Tienen envío a Medellín?', 'time' => '10:18', 'unread' => 1, 'status' => 'open',
            ],
            [
                'id' => 3, 'channel' => 'facebook', 'client' => 'Ana Torres', 'phone' => 'Ana Torres',
                'preview' => 'Gracias por la cotización, la reviso…', 'time' => '09:55', 'unread' => 0, 'status' => 'pending',
            ],
            [
                'id' => 4, 'channel' => 'web', 'client' => 'Visitante #4821', 'phone' => 'chat.web',
                'preview' => '¿Cuál es el horario de atención?', 'time' => '09:30', 'unread' => 1, 'status' => 'open',
            ],
            [
                'id' => 5, 'channel' => 'whatsapp', 'client' => 'Luis Mendoza', 'phone' => '+57 310 987 6543',
                'preview' => 'Perfecto, confirmo el pedido #ORD-8842', 'time' => 'Ayer', 'unread' => 0, 'status' => 'resolved',
            ],
            [
                'id' => 6, 'channel' => 'instagram', 'client' => 'Sofía Vargas', 'phone' => '@sofi.vargas',
                'preview' => 'Me encantó el contenido de ayer 🔥', 'time' => 'Ayer', 'unread' => 0, 'status' => 'resolved',
            ],
        ];
    }

    public static function messages(int $chatId): array
    {
        return [
            ['id' => 1, 'from' => 'client',  'text' => 'Hola, buenos días. Quiero información del plan premium.', 'time' => '10:40'],
            ['id' => 2, 'from' => 'agent',   'text' => '¡Hola María! Con gusto. El plan premium incluye omnicanalidad, informes avanzados y soporte prioritario.', 'time' => '10:41'],
            ['id' => 3, 'from' => 'client',  'text' => '¿Cuál es el valor mensual?', 'time' => '10:42'],
            ['id' => 4, 'from' => 'agent',   'text' => 'El plan premium es de $299.000 COP/mes. ¿Te envío la cotización formal?', 'time' => '10:42'],
        ];
    }

    public static function clients(): array
    {
        return [
            [
                'id' => 1, 'name' => 'María Gómez', 'email' => 'maria.gomez@email.com', 'phone' => '+57 300 123 4567',
                'company' => 'Gómez Retail', 'status' => 'activo', 'channel' => 'whatsapp', 'orders' => 12,
                'lifetime' => 4580000, 'last_contact' => '2026-07-15 10:42', 'tags' => ['VIP', 'Premium'],
            ],
            [
                'id' => 2, 'name' => 'Carlos Ruiz', 'email' => 'carlos.ruiz@email.com', 'phone' => '+57 301 555 8899',
                'company' => 'Ruiz & Co', 'status' => 'activo', 'channel' => 'instagram', 'orders' => 5,
                'lifetime' => 1250000, 'last_contact' => '2026-07-15 10:18', 'tags' => ['Lead'],
            ],
            [
                'id' => 3, 'name' => 'Ana Torres', 'email' => 'ana.torres@empresa.co', 'phone' => '+57 315 222 3344',
                'company' => 'Torres Digital', 'status' => 'prospecto', 'channel' => 'facebook', 'orders' => 0,
                'lifetime' => 0, 'last_contact' => '2026-07-15 09:55', 'tags' => ['Cotización'],
            ],
            [
                'id' => 4, 'name' => 'Luis Mendoza', 'email' => 'lmendoza@correo.com', 'phone' => '+57 310 987 6543',
                'company' => 'Mendoza SAS', 'status' => 'activo', 'channel' => 'whatsapp', 'orders' => 28,
                'lifetime' => 8920000, 'last_contact' => '2026-07-14 16:20', 'tags' => ['VIP', 'Recurrente'],
            ],
            [
                'id' => 5, 'name' => 'Sofía Vargas', 'email' => 'sofia.v@mail.com', 'phone' => '+57 320 111 2233',
                'company' => 'Vargas Studio', 'status' => 'inactivo', 'channel' => 'instagram', 'orders' => 3,
                'lifetime' => 890000, 'last_contact' => '2026-06-28 11:00', 'tags' => ['Reactivar'],
            ],
        ];
    }

    public static function clientOrders(int $clientId): array
    {
        return [
            ['id' => 'ORD-8842', 'date' => '2026-07-14', 'total' => 450000, 'status' => 'confirmado', 'items' => 3],
            ['id' => 'ORD-8710', 'date' => '2026-07-01', 'total' => 280000, 'status' => 'entregado', 'items' => 2],
            ['id' => 'ORD-8601', 'date' => '2026-06-15', 'total' => 620000, 'status' => 'entregado', 'items' => 5],
        ];
    }

    public static function tasks(): array
    {
        return [
            [
                'id' => 1, 'name' => 'Recordatorio follow-up WhatsApp', 'workflow' => 'WF-FollowUp-01',
                'schedule' => 'Cada día 09:00', 'status' => 'activo', 'last_run' => '2026-07-15 09:00',
                'next_run' => '2026-07-16 09:00', 'success_rate' => 98.2,
            ],
            [
                'id' => 2, 'name' => 'Sincronizar leads Instagram', 'workflow' => 'WF-IG-Sync',
                'schedule' => 'Cada 30 min', 'status' => 'activo', 'last_run' => '2026-07-15 15:30',
                'next_run' => '2026-07-15 16:00', 'success_rate' => 99.1,
            ],
            [
                'id' => 3, 'name' => 'Reporte semanal KPI', 'workflow' => 'WF-Report-Weekly',
                'schedule' => 'Lunes 08:00', 'status' => 'activo', 'last_run' => '2026-07-14 08:00',
                'next_run' => '2026-07-21 08:00', 'success_rate' => 100,
            ],
            [
                'id' => 4, 'name' => 'Limpieza conversaciones resueltas', 'workflow' => 'WF-Cleanup',
                'schedule' => 'Domingo 02:00', 'status' => 'pausado', 'last_run' => '2026-07-13 02:00',
                'next_run' => '—', 'success_rate' => 95.0,
            ],
            [
                'id' => 5, 'name' => 'Publicación automática redes', 'workflow' => 'WF-Social-Post',
                'schedule' => 'Lun–Vie 11:00', 'status' => 'activo', 'last_run' => '2026-07-15 11:00',
                'next_run' => '2026-07-16 11:00', 'success_rate' => 97.5,
            ],
            [
                'id' => 6, 'name' => 'Alerta chats sin respuesta >1h', 'workflow' => 'WF-SLA-Alert',
                'schedule' => 'Cada 15 min', 'status' => 'error', 'last_run' => '2026-07-15 15:45',
                'next_run' => '2026-07-15 16:00', 'success_rate' => 82.4,
            ],
        ];
    }

    public static function reportKpis(): array
    {
        return [
            'total_conversations' => 1248,
            'resolved'            => 1089,
            'avg_response'        => '2.4 min',
            'satisfaction'        => 94.2,
            'messages_sent'       => 8934,
            'new_clients'         => 86,
        ];
    }

    public static function chartConversations(): array
    {
        return [
            'labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'whatsapp'  => [42, 55, 48, 61, 58, 30, 22],
            'instagram' => [18, 22, 25, 20, 28, 35, 40],
            'facebook'  => [12, 15, 10, 18, 14, 8, 6],
            'web'       => [8, 10, 12, 9, 11, 5, 4],
        ];
    }

    public static function socialAccounts(): array
    {
        return [
            ['id' => 1, 'platform' => 'WhatsApp Business', 'account' => '+57 300 000 0000', 'status' => 'conectado'],
            ['id' => 2, 'platform' => 'Facebook Page', 'account' => 'Arya Oficial', 'status' => 'conectado'],
            ['id' => 3, 'platform' => 'Instagram', 'account' => '@arya.crm', 'status' => 'conectado'],
            ['id' => 4, 'platform' => 'Web Widget', 'account' => 'arya.crm/chat', 'status' => 'conectado'],
        ];
    }
}
