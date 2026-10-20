@extends('layouts.app')

@section('title', 'Clasificar comentarios · '.$label)

@section('content')
@php
    $liked = $catalog->filter(fn ($t) => $t[1] === 'valora');
    $improve = $catalog->filter(fn ($t) => $t[1] === 'mejora');
    $tabs = ['por-revisar' => ['Por revisar', $summary['unreviewed']], 'sin-tema' => ['Sin tema', $summary['unclassified']], 'todos' => ['Todos', count($survey->data['topics']['answers'])]];
@endphp

<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), route('progress.show', [$report, 'vista' => 'opiniones', 'curso' => $survey->course_code])], ['Clasificar comentarios', null]]"
    title="Clasificar comentarios" :subtitle="$label.' · '.$survey->title">
</x-folder-header>

<div class="mera-notice mb-3" role="note">
    El sistema propuso los temas por palabras clave. Revisa cada comentario, marca o quita temas y pulsa <strong>Guardar</strong>:
    los comentarios de esta página quedan como revisados. Los temas se ven en «Opiniones» del tablero (solo como totales en los
    links compartidos y en la descarga).
</div>

<nav class="mera-section-tabs" aria-label="Comentarios">
    @foreach ($tabs as $key => [$text, $count])
    <a href="{{ route('progress.surveys.topics', [$report, $survey, 'ver' => $key]) }}" class="mera-section-tab {{ $filter === $key ? 'is-active' : '' }}" @if ($filter === $key) aria-current="page" @endif>
        <span>{{ $text }}</span> <span class="mera-section-count">{{ number_format($count) }}</span>
    </a>
    @endforeach
</nav>

@if ($rows->isEmpty())
<div class="card mera-table-card"><div class="card-body mera-empty-state">
    <i class="fas fa-check-circle" aria-hidden="true"></i>
    <strong>{{ $filter === 'por-revisar' ? 'Todo revisado' : 'Nada en esta lista' }}</strong>
    <span>Los temas ya se ven en «Opiniones» del tablero.</span>
</div></div>
@else
<form method="POST" action="{{ route('progress.surveys.classify', [$report, $survey]) }}" data-warn-unsaved>
    @csrf @method('PATCH')
    <ol class="list-unstyled mera-topic-list">
        @foreach ($rows as $index => $answer)
        <li class="card mera-table-card">
            <div class="card-body">
                <input type="hidden" name="shown[]" value="{{ $index }}">
                @if ($answer['r'])<span class="mera-status mera-status-on mb-2"><i class="fas fa-circle" aria-hidden="true"></i> Revisado</span>@endif
                @foreach ([['l', 'Lo que más le gustó', $liked], ['i', 'Qué mejoraría', $improve]] as [$field, $heading, $options])
                @if ($answer[$field] !== '')
                <div class="mera-topic-answer">
                    <p class="mb-1"><span class="small text-muted">{{ $heading }}:</span> «{{ $answer[$field] }}»</p>
                    <div class="mera-topic-options">
                        @foreach ($options as [$code, , $text])
                        <label class="mera-tag-check">
                            <input type="checkbox" name="topics[{{ $index }}][]" value="{{ $code }}" @checked(in_array($code, $answer['c'], true))>
                            <span>{{ $text }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach
            </div>
        </li>
        @endforeach
    </ol>
    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar ({{ $rows->count() }} revisados)</button>
        <a href="{{ route('progress.show', [$report, 'vista' => 'opiniones', 'curso' => $survey->course_code]) }}" class="btn mera-btn-cancel">Regresar</a>
    </div>
</form>

@if ($rows->hasPages())
<div class="mt-3">{{ $rows->links() }}</div>
@endif
@endif
@endsection
