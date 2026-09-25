<?php

declare(strict_types=1);

namespace Arya\Helpers;

/**
 * Catálogo único de iconos SVG del menú lateral (CRM / OCP).
 * Normaliza alias y typos de maestro_menu.icono para que el ícono siempre se pinte.
 */
final class Icons
{
    /** @return array<string, string> */
    public static function catalog(): array
    {
        static $icons = null;
        if ($icons !== null) {
            return $icons;
        }

        $icons = [
            'home' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5z"/></svg>',
            'chat' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H5l-2 2V12A8.5 8.5 0 1 1 21 12z"/><path d="M8 10h8M8 14h5"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 19c0-3 3-5 6.5-5s6.5 2 6.5 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.5c2.5.3 4.5 1.8 4.5 4.5"/></svg>',
            'clock' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>',
            'spark' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l1.5 6.5L20 10l-6.5 1.5L12 18l-1.5-6.5L4 10l6.5-1.5L12 2z"/><path d="M19 15l.7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15z"/></svg>',
            'bot' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="7" width="16" height="12" rx="3"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/><circle cx="9" cy="13" r="1.2" fill="currentColor"/><circle cx="15" cy="13" r="1.2" fill="currentColor"/><path d="M10 16.5h4"/></svg>',
            'chart' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5M4 19h16"/><path d="M8 16v-5M12 16V8M16 16v-8"/></svg>',
            'gear' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2.5M12 19.5V22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2 12h2.5M19.5 12H22M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>',
            'shield' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/></svg>',
            'calendar' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg>',
        ];

        return $icons;
    }

    /** @return array<string, string> alias → clave canónica */
    public static function aliases(): array
    {
        return [
            'dashboard' => 'home',
            'house' => 'home',
            'inicio' => 'home',
            'message' => 'chat',
            'messages' => 'chat',
            'omni' => 'chat',
            'omnicanalidad' => 'chat',
            'comment' => 'chat',
            'user' => 'users',
            'people' => 'users',
            'clients' => 'users',
            'clientes' => 'users',
            'time' => 'clock',
            'tasks' => 'clock',
            'tareas' => 'clock',
            'schedule' => 'clock',
            'star' => 'spark',
            'magic' => 'spark',
            'generator' => 'spark',
            'generador' => 'spark',
            'ai' => 'spark',
            'robot' => 'bot',
            'chatgpt' => 'bot',
            'gpt' => 'bot',
            'openai' => 'bot',
            'reports' => 'chart',
            'informes' => 'chart',
            'bars' => 'chart',
            'bar-chart' => 'chart',
            'settings' => 'gear',
            'config' => 'gear',
            'configuracion' => 'gear',
            'cog' => 'gear',
            'roles' => 'shield',
            'security' => 'shield',
            'lock' => 'shield',
            'appointments' => 'calendar',
            'agenda' => 'calendar',
            'citas' => 'calendar',
        ];
    }

    /** Icono por defecto según código de módulo (si el de DB falla). */
    public static function forModule(?string $moduleKey): string
    {
        $key = strtolower(trim((string) $moduleKey));
        return match ($key) {
            'dashboard', 'ocp_dashboard' => 'home',
            'omnichannel' => 'chat',
            'clients', 'ocp_users' => 'users',
            'appointments' => 'calendar',
            'tasks' => 'clock',
            'chatgpt' => 'bot',
            'generator', 'ocp_ai' => 'spark',
            'reports', 'ocp_reports' => 'chart',
            'settings', 'ocp_menu' => 'gear',
            'ocp_roles' => 'shield',
            default => 'home',
        };
    }

    public static function normalize(?string $name, ?string $moduleKey = null): string
    {
        $raw = strtolower(trim((string) $name));
        $raw = str_replace(['_', ' '], '-', $raw);
        $catalog = self::catalog();

        if ($raw !== '' && isset($catalog[$raw])) {
            return $raw;
        }

        $aliases = self::aliases();
        if ($raw !== '' && isset($aliases[$raw])) {
            return $aliases[$raw];
        }

        return self::forModule($moduleKey);
    }

    public static function svg(?string $name, ?string $moduleKey = null): string
    {
        $key = self::normalize($name, $moduleKey);
        $catalog = self::catalog();
        return $catalog[$key] ?? $catalog['home'];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }
}
