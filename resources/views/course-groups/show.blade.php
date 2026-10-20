@extends('layouts.app')

@section('title', $group->name)

@section('content')
@php
    // Nivel carpeta: Carpeta → Año → Versión (pedido del usuario 2026-10-19). Los módulos, el link
    // y los reportes están en la página de cada versión (course-groups/versions/show).
    $yearVersions = $year ? $years[$year] : collect();
    $usedMarkets = $yearVersions->pluck('market_id')->all();
    $freeMarkets = $markets->reject(fn ($m) => in_array($m->id, $usedMarkets, true))->values();
    $nextYear = max((int) $versions->max('year'), (int) now(config('mera.display_timezone'))->year - 1) + 1;
@endphp

<x-folder-header :crumbs="[['Carpetas', route('course-groups.index')], [$group->name, null]]"
    :title="$group->name" subtitle="Elige el año y la versión para ver sus módulos, su link y sus reportes.">
    @include('course-groups.versions._new-year', ['open' => false, 'popover' => true])
    <a href="{{ route('course-groups.edit', $group) }}" class="btn btn-sm mera-btn-ghost">
        <i class="fas fa-pencil-alt" aria-hidden="true"></i> Editar carpeta
    </a>
</x-folder-header>

@if ($versions->isEmpty())
<div class="card mera-table-card">
    <div class="card-body text-center py-4">
        <h2 class="h5">Esta carpeta todavía no tiene años</h2>
        <p class="text-muted mb-3">Crea el primer año con sus versiones (México, Latam, EUA…). También se crean solos al mover módulos a la carpeta.</p>
        @include('course-groups.versions._new-year', ['open' => true, 'popover' => false])
    </div>
</div>
@else

<section aria-labelledby="anios">
    <h2 id="anios" class="mera-level-title">Año</h2>
    <nav class="mera-lang-tabs mb-4" aria-label="Año">
        @foreach ($years as $y => $list)
        <a href="{{ route('course-groups.show', [$group, 'anio' => $y]) }}"
            class="mera-lang-tab {{ $year === $y ? 'is-active' : '' }}"
            @if ($year === $y) aria-current="page" @endif>
            {{ $y }}
        </a>
        @endforeach
    </nav>
</section>

<section aria-labelledby="versiones">
    <h2 id="versiones" class="mera-level-title">Versiones de {{ $year }}</h2>
    <ul class="mera-version-grid list-unstyled">
        @foreach ($yearVersions as $v)
        <li>
            <a href="{{ route('course-groups.versions.show', [$group, $v]) }}" class="mera-version-card">
                <span class="mera-version-card-name">{{ $v->market->name }}</span>
                <span class="mera-version-card-lang">
                    {{ $v->language() === 'en' ? 'Inglés' : 'Español' }}
                    @if ($cc = $v->catalogCourse())· <span class="mera-scope-badge {{ $cc->isGeneral() ? '' : 'is-specific' }}">{{ $cc->scopeLabel() }}</span>@endif
                </span>
                <span class="mera-version-card-meta">
                    {{ $v->modules_count }} {{ $v->modules_count === 1 ? 'módulo' : 'módulos' }}
                    · {{ $v->reports_count }} {{ $v->reports_count === 1 ? 'reporte' : 'reportes' }}
                </span>
                @if ($v->is_published)
                <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Página publicada</span>
                @else
                <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Página apagada</span>
                @endif
                <i class="fas fa-chevron-right mera-version-card-go" aria-hidden="true"></i>
            </a>
        </li>
        @endforeach

        @if ($freeMarkets->isNotEmpty())
        <li>
            <div class="mera-version-card mera-version-card-add">
                <span class="mera-version-card-meta">Agregar versión a {{ $year }}</span>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($freeMarkets as $m)
                    <form method="POST" action="{{ route('course-groups.versions.store', $group) }}">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="market_ids[]" value="{{ $m->id }}">
                        <button type="submit" class="btn btn-sm mera-btn-ghost" aria-label="Agregar la versión {{ $m->name }} a {{ $year }}">
                            <i class="fas fa-plus" aria-hidden="true"></i> {{ $m->name }}
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
        </li>
        @endif
    </ul>
</section>
@endif

@if ($withoutVersion || $reportsWithoutVersion->isNotEmpty())
<div class="alert alert-warning mt-3" role="status">
    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
    @if ($withoutVersion)
    {{ $withoutVersion }} {{ $withoutVersion === 1 ? 'módulo de esta carpeta no tiene' : 'módulos de esta carpeta no tienen' }} año ni versión:
    selecciónalos en <a href="{{ route('courses.index', ['carpeta' => $group->id]) }}">Módulos</a> y usa «Mover a…».
    @endif
    @if ($reportsWithoutVersion->isNotEmpty())
    {{ $reportsWithoutVersion->count() }} {{ $reportsWithoutVersion->count() === 1 ? 'corte de reporte no tiene' : 'cortes de reporte no tienen' }} versión; asígnasela con «Actualizar corte»:
    @foreach ($reportsWithoutVersion as $r)
    <a href="{{ route('course-groups.reports.edit', [$group, $r]) }}">{{ $r->title }} ({{ $r->cut_date->format('d/m/Y') }})</a>@if (! $loop->last), @endif
    @endforeach
    @endif
</div>
@endif
@endsection
