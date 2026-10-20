<?php

namespace App\Support;

/**
 * URLs anteriores en inglés → su equivalente en español (pedido del usuario 2026-10-20: todas las URLs
 * en español). routes/web.php registra un 301 por cada prefijo para no romper marcadores ni links de
 * correos ya enviados (p. ej. /reset-password/<token>). Los prefijos viejos siguen reservados para
 * carpetas (CourseGroup::reservedSlugs() lee las rutas).
 *
 * No incluye /api/courses: es el contrato con el WordPress del catálogo y no cambia.
 */
final class LegacyPaths
{
    /** Prefijo anterior => prefijo nuevo. */
    public const MAP = [
        'login' => 'iniciar-sesion',
        'forgot-password' => 'recuperar-contrasena',
        'reset-password' => 'restablecer-contrasena',
        'verify-email' => 'verificar-correo',
        'confirm-password' => 'confirmar-contrasena',
        'dashboard' => 'panel',
        'profile' => 'perfil',
        'users' => 'usuarios',
        'categories' => 'categorias',
        'courses' => 'modulos',
        'course-groups' => 'carpetas',
        'permissions' => 'permisos',
        'activity-logs' => 'historial',
    ];

    /** Segmentos sueltos que también cambiaron (resource y acciones). */
    private const SEGMENTS = [
        'create' => 'nuevo',
        'edit' => 'editar',
    ];

    /** Ruta nueva para lo que venía después del prefijo anterior (/courses/5/edit → /modulos/5/editar). */
    public static function translate(string $newPrefix, ?string $rest): string
    {
        $segments = array_map(fn ($s) => self::SEGMENTS[$s] ?? $s, array_filter(explode('/', (string) $rest), 'strlen'));

        return '/'.implode('/', [$newPrefix, ...$segments]);
    }
}
