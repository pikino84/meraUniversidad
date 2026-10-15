<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas de Laravel (CLAUDE.md §12, S23/S24).
 *
 * Solo aplica a lo que pasa por Laravel: los archivos de `storage/cursos` los sirve el
 * servidor web directamente, así que los paquetes de isEazy no se ven afectados.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // S24: no anunciar la versión de PHP (en producción, 8.1 fuera de soporte).
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        $headers = [
            // Anti clickjacking: el panel y el login no se pueden embeber en otro sitio.
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // CSP en modo "solo reporte": la plantilla usa scripts y estilos inline. No bloquea
            // nada; sirve para medir antes de pasarla a obligatoria.
            'Content-Security-Policy-Report-Only' => implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "frame-ancestors 'self'",
                "form-action 'self'",
                "object-src 'none'",
                "img-src 'self' data: https:",
                "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
                "script-src 'self' 'unsafe-inline'",
                "connect-src 'self'",
            ]),
        ];

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // Fuera de producción (QA en university.meracorporation.com, APP_ENV=staging; y local) nada se
        // indexa: QA es una copia del sitio real y sus links de módulos dan 404. Se usa la cabecera y
        // no robots.txt: con "Disallow" Google no leería el noindex y podría listar las URLs igual.
        // Si la respuesta ya trae un noindex propio (p. ej. un reporte compartido), se respeta.
        if (! app()->isProduction() && ! str_contains((string) $response->headers->get('X-Robots-Tag'), 'noindex')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
