{{-- "Descargar HTML": el corte en un solo archivo autocontenido (INSTRUCTIVO_REPORTES.md §5.3).
     Estilos, d3 y datos van incrustados; solo Montserrat se pide a Google Fonts (sin internet usa
     Arial). Solo agregados por localidad, área y puesto. Variables: $group, $report, $css, $js --}}
@php
    $tz = config('mera.display_timezone');
    $logo = public_path('images/logo-mera-university-blanco.png'); // blanco, sobre el degradado verde
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $report->title }} · Corte al {{ $report->cut_date->format('d/m/Y') }}</title>
@include('partials.brand-fonts')
<style>
html, body { margin: 0; background: #F3F8F4; color: #1d2a22; font-family: 'Montserrat', Arial, sans-serif; }
.mera-standalone { max-width: 1240px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
.mera-standalone-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; padding: 1.4rem 1.6rem; border-radius: 18px; color: #fff; background: linear-gradient(90deg, #024D25, #55882B); }
.mera-standalone-head h1 { margin: 0 0 .25rem; font-size: 1.6rem; }
.mera-standalone-head p { margin: 0; opacity: .92; }
.mera-standalone-head img { height: 64px; width: auto; }
.mera-standalone .mera-report-kpi > i { display: none; }
.visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important; }
.mera-standalone-foot { margin-top: 1.5rem; font-size: .8rem; color: #5d6b63; }
{!! str_replace('</style', '<\/style', $css) !!}
</style>
</head>
<body>
<main class="mera-standalone">
    <header class="mera-standalone-head">
        <div>
            <h1>{{ $report->title }}</h1>
            <p>{{ $group->name }} · Corte al {{ $report->cut_date->translatedFormat('j \d\e F \d\e Y') }}</p>
        </div>
        @if (is_file($logo))
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" alt="MERA University">
        @endif
    </header>

    @include('reports._dashboard', ['report' => $report])

    <p class="mera-standalone-foot">
        Generado el {{ now($tz)->format('d/m/Y H:i') }} desde Mera Universidad.
        Solo incluye totales por localidad, área y puesto: ningún nombre ni número de empleado.
    </p>
</main>
<script type="module">{!! str_replace('</script', '<\/script', $js) !!}</script>
</body>
</html>
