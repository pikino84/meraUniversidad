@extends('layouts.app')

@section('title', 'Nuevo corte · Seguimiento anual')

@section('content')
@php
    $maxMb = round(config('mera.reports.max_upload_kb') / 1024);
    $hints = [
        'progreso' => 'Reporte de progreso de capacitaciones <fecha>.xlsx',
        'personal' => 'Reporte de personal <fecha>.xlsx',
    ];
@endphp
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Nuevo corte del seguimiento</h1>
            <span>Todos los cursos del año, persona por persona</span>
        </div>
    </div>
    <div class="card-block">
        @include('partials.form-errors')

        {{-- Procesar el Excel de progreso (~75 mil filas) tarda alrededor de un minuto: submit-guard.js
             muestra el loader y bloquea el botón. report-form.js llena la fecha con la del nombre. --}}
        <form action="{{ route('progress.store') }}" method="POST" enctype="multipart/form-data" data-warn-unsaved data-report-form
            data-loader-message="Procesando el seguimiento… puede tardar alrededor de un minuto">
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-group mera-form-group mb-0">
                        <label for="p-title">Título <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="p-title" type="text" name="title" maxlength="150" required
                            class="form-control mera-input @error('title') is-invalid @enderror"
                            value="{{ old('title', 'Capacitación '.now(config('mera.display_timezone'))->year) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mera-form-group mb-0">
                        <label for="p-cut-date">Fecha de corte</label>
                        <input id="p-cut-date" type="date" name="cut_date" max="{{ now(config('mera.display_timezone'))->toDateString() }}"
                            class="form-control mera-input @error('cut_date') is-invalid @enderror" value="{{ old('cut_date') }}" data-cut-date
                            aria-describedby="p-cut-date-hint">
                        <small id="p-cut-date-hint" class="form-text text-muted">Se llena sola con la fecha del nombre del archivo. Vacía = hoy.</small>
                    </div>
                </div>
            </div>

            <fieldset class="mera-fieldset mt-4">
                <legend>Archivos de Excel</legend>
                <p class="small text-muted mb-3">
                    Los exporta la plataforma de capacitación. Máximo {{ $maxMb }} MB cada uno. <strong>No se guardan:</strong>
                    se procesan y se borran al terminar. Del de progreso solo se toman número de empleado, nombre, centro de
                    trabajo, curso, avance, calificación y fechas (nunca RFC, CURP, nómina ni dirección). El de personal sirve
                    para la localidad, el área y el puesto, y para no contar a quien ya no está.
                </p>
                <div class="row g-3">
                    @foreach (\App\Services\Reports\ProgressReportManager::FILES as $key => $label)
                    <div class="col-lg-6">
                        <div class="form-group mera-form-group mb-0">
                            <label for="p-{{ $key }}">{{ ucfirst($label) }} <span class="text-danger" aria-hidden="true">*</span></label>
                            <input id="p-{{ $key }}" type="file" name="{{ $key }}" required accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                class="form-control @error($key) is-invalid @enderror" aria-describedby="p-{{ $key }}-hint" data-report-file>
                            <small id="p-{{ $key }}-hint" class="form-text text-muted">Ej.: {{ $hints[$key] }}</small>
                        </div>
                    </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="mera-form-actions">
                <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Crear corte</button>
                <a href="{{ route('progress.index') }}" class="btn mera-btn-cancel"><i class="fas fa-times" aria-hidden="true"></i> Cancelar</a>
            </div>
            <p class="small text-muted mt-2 mb-0">Procesar los archivos tarda alrededor de un minuto. No cierres la página.</p>
        </form>
    </div>
</div>
@endsection
