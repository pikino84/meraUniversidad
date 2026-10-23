{{-- Cintillo "Entorno de pruebas" (App\Support\TestEnvironment): en todas las páginas fuera de
     producción, para que nadie lo confunda con el sitio real. Estilos en línea: también va en el
     HTML descargado del reporte, que no carga app.css. Variables: $unshare (bool, botón para
     apagar lo compartido; solo en el panel) --}}
@if (\App\Support\TestEnvironment::active())
@php
    $productionUrl = config('mera.test_environment.production_url');
    $productionHost = parse_url($productionUrl, PHP_URL_HOST) ?: $productionUrl;
    $counts = ($unshare ?? false) ? \App\Support\TestEnvironment::sharedCounts() : null;
    $sharedTotal = $counts ? $counts['pages'] + $counts['reports'] : 0;
@endphp
<style>
/* Fijo abajo: arriba tapaba la barra superior fija del panel (sobre todo en móvil). */
body { padding-bottom: 64px; }
/* Alto del cintillo: lo usan las barras fijas abajo (p. ej. "Guardar esta página") para no quedar debajo. */
:root { --mera-env-banner-h: 64px; }
@media (max-width: 575.98px) { :root { --mera-env-banner-h: 104px; } }
.mera-env-banner { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1040; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 4px 14px; padding: 8px 16px calc(8px + env(safe-area-inset-bottom, 0px)); background: #FFE08A; color: #3D2E00; font: 500 13px/1.4 'Montserrat', Arial, sans-serif; text-align: center; border-top: 4px solid transparent; border-image: repeating-linear-gradient(-45deg, #3D2E00 0 10px, #FFC21A 10px 20px) 4; box-shadow: 0 -4px 14px rgba(0, 0, 0, .12); }
.mera-env-banner strong { font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
.mera-env-banner a { color: #3D2E00; font-weight: 700; text-decoration: underline; }
.mera-env-banner form { margin: 0; }
.mera-env-banner button { min-height: 32px; padding: 4px 14px; border: 1.5px solid #3D2E00; border-radius: 999px; background: #fff; color: #3D2E00; font: 700 12px/1.2 'Montserrat', Arial, sans-serif; cursor: pointer; }
.mera-env-banner button:hover, .mera-env-banner button:focus-visible { background: #3D2E00; color: #fff; outline: none; }
.mera-env-short { display: none; }
@media (max-width: 767.98px) {
    body { padding-bottom: 96px; }
    .mera-env-banner { font-size: 12px; }
    .mera-env-long { display: none; }
    .mera-env-short { display: inline; }
}
@media print { .mera-env-banner { position: static; } body { padding-bottom: 0; } }
</style>
<div class="mera-env-banner" role="note" aria-label="Entorno de pruebas">
    <strong>⚠ Entorno de pruebas</strong>
    <span class="mera-env-long">Nada de lo que se sube o se comparte aquí es oficial y puede borrarse. El sitio real es <a href="{{ $productionUrl }}">{{ $productionHost }}</a>.</span>
    <span class="mera-env-short">No es el sitio real: <a href="{{ $productionUrl }}">{{ $productionHost }}</a></span>
    @if ($counts)
    {{-- Siempre visible (con 0 también), para que se sepa dónde está. --}}
    <form method="POST" action="{{ route('test-environment.unshare') }}"
        data-confirm="¿Apagar todo lo compartido?" data-confirm-tone="info" data-confirm-button="Apagar todo"
        data-confirm-strong="{{ $counts['pages'] }} {{ $counts['pages'] === 1 ? 'página publicada' : 'páginas publicadas' }} · {{ $counts['reports'] }} {{ $counts['reports'] === 1 ? 'reporte compartido' : 'reportes compartidos' }}"
        data-confirm-detail="Se apagan en todas las carpetas. Quien tenga esos links verá «no encontrado». Se pueden volver a publicar o compartir una por una.">
        @csrf
        <button type="submit">
            <span aria-hidden="true">⏻</span> Apagar todo lo compartido
            ({{ $sharedTotal === 0 ? 'nada activo' : $sharedTotal.' '.($sharedTotal === 1 ? 'activo' : 'activos') }})
        </button>
    </form>
    @endif
</div>
@endif
