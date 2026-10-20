@extends('layouts.app')

@section('title', 'Editar corte · Seguimiento anual')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar corte</h1>
            <span>{{ $report->title }} · {{ $report->cut_date->format('d/m/Y') }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('partials.form-errors')

        <form action="{{ route('progress.update', $report) }}" method="POST" data-warn-unsaved>
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-group mera-form-group mb-0">
                        <label for="e-title">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="e-title" type="text" name="title" maxlength="150" required
                            class="form-control mera-input @error('title') is-invalid @enderror" value="{{ old('title', $report->title) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mera-form-group mb-0">
                        <label for="e-date">Fecha de corte <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="e-date" type="date" name="cut_date" required max="{{ now(config('mera.display_timezone'))->toDateString() }}"
                            class="form-control mera-input @error('cut_date') is-invalid @enderror" value="{{ old('cut_date', $report->cut_date->toDateString()) }}"
                            aria-describedby="e-date-hint">
                        <small id="e-date-hint" class="form-text text-muted">El link del corte usa esta fecha (/seguimiento/{{ $report->slug }}): si la cambias, cambia el link.</small>
                    </div>
                </div>
            </div>
            <p class="small text-muted mt-3 mb-0">Las cifras no cambian. Para recalcularlas con Excel nuevos, crea otro corte.</p>

            <div class="mera-form-actions">
                <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar</button>
                <a href="{{ route('progress.show', $report) }}" class="btn mera-btn-cancel"><i class="fas fa-times" aria-hidden="true"></i> Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
