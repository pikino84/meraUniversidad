@extends('layouts.app')

@section('title', 'Compartir · '.$report->title)

@section('content')
<x-folder-header :crumbs="[['Seguimiento anual', route('progress.index')], [$report->cut_date->format('d/m/Y'), route('progress.show', $report)], ['Compartir', null]]"
    title="Compartir el seguimiento" :subtitle="$report->title.' · corte al '.$report->cut_date->format('d/m/Y')">
</x-folder-header>

<section class="card mera-table-card">
    <div class="card-body">
        <p class="small text-muted">
            Cada link abre el tablero <strong>sin iniciar sesión</strong> y <strong>sin nombres de personas</strong>: sin la consulta de
            colaboradores ni las respuestas abiertas de las encuestas. El de una localidad muestra solo esa localidad y no la compara con
            otras. Se puede desactivar cuando quieras: quien lo tenga verá «no encontrado».
        </p>

        <form method="POST" action="{{ route('progress.shares.store', $report) }}" class="mera-share-new">
            @csrf
            <label for="share-locality" class="visually-hidden">Localidad del link</label>
            <select id="share-locality" name="locality" class="form-select mera-input">
                <option value="">Toda la empresa</option>
                @foreach ($localities as $locality)
                <option value="{{ $locality }}">{{ $locality }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm mera-btn-save"><i class="fas fa-link" aria-hidden="true"></i> Crear link</button>
        </form>

        @if ($shares->isEmpty())
        <div class="mera-empty-state mt-3">
            <i class="fas fa-link" aria-hidden="true"></i>
            <strong>Todavía no hay links</strong>
            <span>Elige una localidad (o toda la empresa) y crea su link.</span>
        </div>
        @else
        <ul class="list-unstyled mb-0 mt-3 mera-share-list">
            @foreach ($shares as $share)
            <li class="mera-share-url">
                <div class="mera-share-who">
                    <strong>{{ $share->label() }}</strong>
                    <small class="text-muted d-block">Creado el {{ $share->created_at->timezone(config('mera.display_timezone'))->format('d/m/Y H:i') }}</small>
                </div>
                <code class="mera-public-url">{{ $share->url() }}</code>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm mera-btn-ghost" data-copy="{{ $share->url() }}" aria-label="Copiar el link de {{ $share->label() }}">
                        <i class="fas fa-copy" aria-hidden="true"></i> <span data-copy-label>Copiar link</span>
                    </button>
                    <a href="{{ $share->url() }}" target="_blank" rel="noopener" class="btn btn-sm mera-btn-ghost" aria-label="Abrir el link de {{ $share->label() }} (nueva pestaña)">
                        <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ver
                    </a>
                    <form method="POST" action="{{ route('progress.shares.destroy', [$report, $share]) }}"
                        data-confirm="¿Desactivar el link?" data-confirm-strong="{{ $share->label() }}" data-confirm-tone="danger"
                        data-confirm-detail="Quien lo tenga verá «no encontrado». Puedes crear otro cuando quieras." data-confirm-button="Desactivar">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm mera-btn-ghost mera-btn-ghost-danger"><i class="fas fa-ban" aria-hidden="true"></i> Desactivar</button>
                    </form>
                </div>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</section>

<p class="mt-3"><a href="{{ route('progress.show', $report) }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Regresar al tablero</a></p>
@endsection
