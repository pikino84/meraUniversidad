{{-- Variables: $action, $method, $group, $report (nullable), $surveys (solo al crear), $submitLabel --}}
@php
    $editing = isset($report);
    $maxMb = round(config('mera.reports.max_upload_kb') / 1024);
    $files = \App\Http\Requests\Reports\CourseReportRequest::FILES;
    $hints = [
        'personal' => 'Reporte de personal <fecha>.xlsx',
        'inscripciones' => 'Reporte de inscripciones <clave> _ <curso> <fecha>.xlsx',
        'encuesta' => '<fecha> Encuesta de satisfacción - <curso> responses.xlsx',
    ];
@endphp
@include('partials.form-errors')

{{-- Procesar los 3 Excel tarda varios segundos: submit-guard.js muestra el loader y bloquea el botón. --}}
<form action="{{ $action }}" method="POST" enctype="multipart/form-data" data-warn-unsaved data-report-form data-loader-message="Procesando los Excel… puede tardar un poco">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-8">
            <div class="form-group mera-form-group mb-0">
                <label for="report-title">Título <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="report-title" type="text" name="title" maxlength="150" required
                    class="form-control mera-input @error('title') is-invalid @enderror"
                    value="{{ old('title', $report->title ?? 'Curso: '.$group->name) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group mera-form-group mb-0">
                <label for="report-cut-date">Fecha de corte <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="report-cut-date" type="date" name="cut_date" required max="{{ now(config('mera.display_timezone'))->toDateString() }}"
                    class="form-control mera-input @error('cut_date') is-invalid @enderror"
                    value="{{ old('cut_date', isset($report) ? $report->cut_date->toDateString() : '') }}" data-cut-date>
                <small class="form-text text-muted">Se sugiere sola con la fecha del nombre del archivo.</small>
            </div>
        </div>

        @unless ($editing)
        @if (count($surveys) > 1)
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="report-survey">Encuesta</label>
                <select id="report-survey" name="survey" class="form-control form-select mera-input">
                    @foreach ($surveys as $key => $name)
                    <option value="{{ $key }}" @selected(old('survey', config('mera.reports.default_survey')) === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @else
        <input type="hidden" name="survey" value="{{ array_key_first($surveys) }}">
        @endif
        @endunless
    </div>

    <fieldset class="mera-fieldset mt-4">
        <legend>
            Archivos de Excel
            @if ($editing)
            <small class="text-muted fw-normal">(opcional: sube los 3 para recalcular este corte)</small>
            @endif
        </legend>
        <p class="small text-muted mb-3">
            Los exporta la plataforma de capacitación. Máximo {{ $maxMb }} MB cada uno.
            <strong>No se guardan:</strong> se procesan y se borran al terminar; el reporte solo guarda totales por localidad, área y puesto.
        </p>

        <div class="row g-3">
            @foreach ($files as $key => $label)
            <div class="col-lg-4">
                <div class="form-group mera-form-group mb-0">
                    <label for="report-{{ $key }}">{{ ucfirst($label) }} @unless ($editing)<span class="text-danger" aria-hidden="true">*</span>@endunless</label>
                    <input id="report-{{ $key }}" type="file" name="{{ $key }}" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        @unless ($editing) required @endunless
                        class="form-control @error($key) is-invalid @enderror" aria-describedby="report-{{ $key }}-hint" data-report-file>
                    <small id="report-{{ $key }}-hint" class="form-text text-muted">Ej.: {{ $hints[$key] }}</small>
                </div>
            </div>
            @endforeach
        </div>
    </fieldset>

    @if ($editing)
    <div class="alert alert-warning d-flex gap-2 align-items-start mt-3" role="note">
        <i class="fas fa-exclamation-triangle mt-1" aria-hidden="true"></i>
        <div>Si subes archivos nuevos, el corte se <strong>recalcula completo</strong> y se pierden los comentarios que ya se habían clasificado a mano en él.</div>
    </div>
    @endif

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ $editing ? route('course-groups.reports.show', [$group, $report]) : route('course-groups.show', $group) }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
    <p class="small text-muted mt-2 mb-0">Procesar los archivos puede tardar hasta un minuto. No cierres la página.</p>
</form>
