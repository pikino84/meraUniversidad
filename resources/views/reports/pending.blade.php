@extends('layouts.app')

@section('title', 'Pendientes de clasificar')

@section('content')
@php
    $perPage = 25;
    $items = $report->pending ?? [];
    $total = count($items);
    $page = max(1, min((int) request('pagina', 1), max(1, (int) ceil($total / $perPage))));
    $offset = ($page - 1) * $perPage;
    $slice = array_slice($items, $offset, $perPage, true);
    $liked = array_values(array_filter($catalog, fn ($t) => $t[1] === 'valora'));
    $improve = array_values(array_filter($catalog, fn ($t) => $t[1] === 'mejora'));
@endphp

@include('partials.page-header', [
    'title' => 'Pendientes de clasificar',
    'subtitle' => $report->title.' · '.$total.' '.($total === 1 ? 'comentario' : 'comentarios'),
])

<p class="mb-3"><a href="{{ route('course-groups.reports.show', [$group, $report]) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Volver al reporte</a></p>

@if ($total === 0)
<div class="card mera-table-card">
    <div class="card-body mera-empty-state">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <strong>No hay comentarios pendientes</strong>
        <span>Todos los comentarios de este corte ya están clasificados.</span>
    </div>
</div>
@else
<div class="mera-notice mb-3" role="note">
    El sistema propuso los temas por palabras clave. Revisa cada comentario, corrige los temas y guarda.
    Solo se guardan los marcados como <strong>Revisado</strong>. Mientras tanto, estos comentarios ya cuentan en la satisfacción.
</div>

<form method="POST" action="{{ route('course-groups.reports.classify', [$group, $report]) }}" data-warn-unsaved>
    @csrf @method('PATCH')

    @foreach ($slice as $index => $item)
    @php $marked = $proposed[$index] ?? []; @endphp
    <fieldset class="card mera-table-card mb-3 mera-pending-item">
        <div class="card-body">
            <legend class="visually-hidden">Comentario {{ $index + 1 }} de {{ $total }}</legend>
            <div class="row g-3">
                <div class="col-lg-6">
                    <p class="mera-pending-q">¿Qué fue lo que más te gustó?</p>
                    <blockquote class="mera-pending-a">{{ $item['liked'] !== '' ? $item['liked'] : '(sin respuesta)' }}</blockquote>
                    <div class="mera-pending-tags">
                        @foreach ($liked as [$code, , $label])
                        <label class="mera-tag-check">
                            <input type="checkbox" name="codes[{{ $index }}][]" value="{{ $code }}" @checked(in_array($code, $marked, true))>
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-6">
                    <p class="mera-pending-q">¿Qué mejorarías?</p>
                    <blockquote class="mera-pending-a">{{ $item['improve'] !== '' ? $item['improve'] : '(sin respuesta)' }}</blockquote>
                    <div class="mera-pending-tags">
                        @foreach ($improve as [$code, , $label])
                        <label class="mera-tag-check">
                            <input type="checkbox" name="codes[{{ $index }}][]" value="{{ $code }}" @checked(in_array($code, $marked, true))>
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <label class="mera-show-passwords mt-2">
                <input type="checkbox" class="form-check-input" name="reviewed[]" value="{{ $index }}" checked>
                Revisado
            </label>
        </div>
    </fieldset>
    @endforeach

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar clasificación</button>
    </div>
</form>

@if ($total > $perPage)
<nav class="mt-3 d-flex justify-content-between align-items-center" aria-label="Páginas de comentarios">
    @if ($page > 1)
    <a class="btn btn-sm mera-btn-cancel" href="?pagina={{ $page - 1 }}">« Anteriores</a>
    @else <span></span> @endif
    <span class="small text-muted">Página {{ $page }} de {{ (int) ceil($total / $perPage) }}</span>
    @if ($offset + $perPage < $total)
    <a class="btn btn-sm mera-btn-cancel" href="?pagina={{ $page + 1 }}">Siguientes »</a>
    @else <span></span> @endif
</nav>
@endif
@endif
@endsection
