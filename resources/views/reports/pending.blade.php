@extends('layouts.app')

@section('title', 'Revisar comentarios')

{{-- Revisar comentarios del corte (rediseño 2026-10-23). Variables: $group, $report, $catalog, $counts,
     $tab ('sin-tema' | 'con-tema'), $items (índice en pending => comentario + codes), $page, $pages --}}
@section('content')
@php
    $liked = array_values(array_filter($catalog, fn ($t) => $t[1] === 'valora'));
    $improve = array_values(array_filter($catalog, fn ($t) => $t[1] === 'mejora'));
    $tabs = [
        'sin-tema' => ['Sin tema', 'El sistema no les encontró tema: asígnale uno si lo tiene.'],
        'con-tema' => ['Con tema', 'El sistema ya les puso tema: corrígelo solo si está mal.'],
    ];
@endphp

@include('partials.page-header', [
    'title' => 'Revisar comentarios',
    'subtitle' => $report->title.' · Corte al '.$report->cut_date->format('d/m/Y'),
])

<p class="mb-3"><a href="{{ route('course-groups.reports.show', [$group, $report]) }}#vista=opiniones"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver al reporte</a></p>

<div class="mera-notice mb-3" role="note">
    <strong>¿Para qué sirve?</strong> Con lo que escribieron las personas se arman las gráficas
    «Lo que más valoran» y «Qué piden mejorar» del reporte. El sistema ya le puso tema a cada comentario;
    aquí puedes <strong>corregirlo marcando o quitando casillas</strong> y pulsar <strong>Guardar esta página</strong>.
    <strong>Es opcional:</strong> si no haces nada, el reporte ya usa lo que propuso el sistema, y no cambia la satisfacción
    ni ningún otro número. Las respuestas vacías («.», «N/A», «nada») no aparecen.
</div>

<nav class="mera-section-tabs" aria-label="Comentarios">
    @foreach ($tabs as $key => [$label])
    <a href="{{ route('course-groups.reports.pending', [$group, $report, 'ver' => $key]) }}" class="mera-section-tab {{ $tab === $key ? 'is-active' : '' }}" @if ($tab === $key) aria-current="page" @endif>
        <span>{{ $label }}</span> <span class="mera-section-count">{{ number_format($counts[$key]) }}</span>
    </a>
    @endforeach
</nav>

@if ($items === [])
<div class="card mera-table-card">
    <div class="card-body mera-empty-state">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <strong>{{ $tab === 'sin-tema' ? 'No hay comentarios sin tema' : 'No hay comentarios con tema por revisar' }}</strong>
        <span>Los temas ya se ven en la pestaña «Opiniones» del reporte.</span>
    </div>
</div>
@else
<p class="small text-muted mb-2">{{ $tabs[$tab][1] }}</p>

<form method="POST" action="{{ route('course-groups.reports.classify', [$group, $report]) }}" data-warn-unsaved>
    @csrf @method('PATCH')
    <input type="hidden" name="ver" value="{{ $tab }}">

    @foreach ($items as $index => $item)
    <fieldset class="card mera-table-card mb-3 mera-pending-item">
        <div class="card-body">
            <legend class="visually-hidden">Comentario {{ $loop->iteration }}</legend>
            <input type="hidden" name="shown[]" value="{{ $index }}">
            <div class="row g-3">
                @foreach ([['liked', '¿Qué fue lo que más te gustó?', $liked], ['improve', '¿Qué mejorarías?', $improve]] as [$field, $question, $options])
                <div class="col-lg-6">
                    <p class="mera-pending-q">{{ $question }}</p>
                    <blockquote class="mera-pending-a">{{ $item[$field] !== '' ? $item[$field] : '(sin respuesta)' }}</blockquote>
                    @if (\App\Services\Reports\SurveyAnswerClassifier::meaningful($item[$field], $field === 'improve'))
                    <p class="mera-pending-hint">Temas <span>(marca los que apliquen)</span></p>
                    <div class="mera-pending-tags">
                        @foreach ($options as [$code, , $label])
                        <label class="mera-tag-check">
                            <input type="checkbox" name="codes[{{ $index }}][]" value="{{ $code }}" @checked(in_array($code, $item['codes'], true))>
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                    @else
                    {{-- Sin texto útil: conserva lo que tenga (normalmente nada), sin casillas que confundan. --}}
                    @foreach (array_intersect($item['codes'], array_column($options, 0)) as $code)
                    <input type="hidden" name="codes[{{ $index }}][]" value="{{ $code }}">
                    @endforeach
                    <p class="mera-pending-hint">No hay nada que clasificar.</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </fieldset>
    @endforeach

    <div class="mera-pending-save">
        <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar esta página</button>
        <span class="small text-muted">Se guardan los {{ count($items) }} comentarios de esta página tal como están las casillas y salen de la lista.</span>
    </div>
</form>

@if ($pages > 1)
<nav class="mt-3 d-flex justify-content-between align-items-center" aria-label="Páginas de comentarios">
    @if ($page > 1)
    <a class="btn btn-sm mera-btn-cancel" href="{{ route('course-groups.reports.pending', [$group, $report, 'ver' => $tab, 'pagina' => $page - 1]) }}">« Anteriores</a>
    @else <span></span> @endif
    <span class="small text-muted">Página {{ $page }} de {{ $pages }}</span>
    @if ($page < $pages)
    <a class="btn btn-sm mera-btn-cancel" href="{{ route('course-groups.reports.pending', [$group, $report, 'ver' => $tab, 'pagina' => $page + 1]) }}">Siguientes »</a>
    @else <span></span> @endif
</nav>
@endif
@endif
@endsection
