@extends('layouts.app')

@section('title', 'Ajustar curso del catálogo')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fas fa-list-alt" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">{{ $course->short_title }}</h1>
            <span>{{ $course->code }} · {{ $course->title }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('partials.form-errors')

        <form action="{{ route('course-catalog.update', $course) }}" method="POST" data-warn-unsaved>
            @csrf @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <div class="form-group mera-form-group mb-0">
                        <label for="c-short">Nombre corto <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="c-short" type="text" name="short_title" maxlength="150" required
                            class="form-control mera-input @error('short_title') is-invalid @enderror"
                            value="{{ old('short_title', $course->short_title) }}">
                        <small class="form-text text-muted">Es el que se ve en los reportes. Si vuelves a cargar el Excel, se toma el «Título corto» del Excel.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mera-form-group mb-0">
                        <label for="c-grade">Calificación mínima para aprobar <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="c-grade" type="number" name="passing_grade" min="1" max="100" required
                            class="form-control mera-input @error('passing_grade') is-invalid @enderror"
                            value="{{ old('passing_grade', $course->passing_grade) }}">
                    </div>
                </div>

                <div class="col-12">
                    <input type="hidden" name="has_evaluation" value="0">
                    <label class="mera-tag-check">
                        <input type="checkbox" name="has_evaluation" value="1" @checked(old('has_evaluation', $course->has_evaluation))>
                        <span>Tiene evaluación</span>
                    </label>
                    <small class="form-text text-muted d-block">Sin evaluación, se aprueba con ver el curso al 100 %.</small>
                </div>

                <div class="col-12">
                    <input type="hidden" name="in_tracking" value="0">
                    <label class="mera-tag-check">
                        <input type="checkbox" name="in_tracking" value="1" @checked(old('in_tracking', $course->in_tracking))>
                        <span>Cuenta para el seguimiento anual</span>
                    </label>
                </div>

                <div class="col-12">
                    <div class="form-group mera-form-group mb-0">
                        <label for="c-aliases">Códigos con los que aparece en la plataforma <small class="text-muted fw-normal">(opcional)</small></label>
                        <textarea id="c-aliases" name="aliases" rows="3"
                            class="form-control mera-input @error('aliases.*') is-invalid @enderror"
                            placeholder="NEG-LID-003-BL-26">{{ old('aliases', implode("\n", $course->aliases ?? [])) }}</textarea>
                        <small class="form-text text-muted">Uno por línea. Úsalo si en la plataforma el curso tiene otro código que en el catálogo (p. ej. si el código cambia).</small>
                    </div>
                </div>
            </div>

            <dl class="mera-facts mt-4 mb-0">
                <dt>Ruta</dt><dd>{{ $course->route }} · {{ $course->sub }} · {{ $course->modality }}</dd>
                <dt>Año · País</dt><dd>{{ $course->year }} · {{ $course->countryLabel() }}</dd>
                <dt>Horas</dt><dd>{{ $course->hours ?? '—' }}</dd>
                <dt>Dirigido a</dt><dd>{{ $course->level ?? '—' }}</dd>
                <dt>Instructor</dt><dd>{{ $course->instructor ?? '—' }}</dd>
            </dl>

            <div class="mera-form-actions">
                <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar</button>
                <a href="{{ route('course-catalog.index', ['anio' => $course->year]) }}" class="btn mera-btn-cancel">
                    <i class="fas fa-times" aria-hidden="true"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
