@extends('layouts.app')

@section('title', 'Editar versión')

@section('content')
@php $en = $version->language() === 'en'; @endphp
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-folder-open" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar versión · {{ $version->label() }}</h1>
            <span>{{ $group->name }}</span>
        </div>
    </div>
    <div class="card-block">
        @include('partials.form-errors')

        <form action="{{ route('course-groups.versions.update', [$group, $version]) }}" method="POST" data-warn-unsaved>
            @csrf @method('PUT')

            <div class="row g-3">
                <div class="col-md-5">
                    <div class="form-group mera-form-group mb-0">
                        <label for="v-code">Código del catálogo <small class="text-muted fw-normal">(opcional)</small></label>
                        <input id="v-code" type="text" name="catalog_code" maxlength="40" autocomplete="off"
                            class="form-control mera-input @error('catalog_code') is-invalid @enderror"
                            value="{{ old('catalog_code', $version->catalog_code) }}" placeholder="NRM-HIG-002-EL-26">
                        <small class="form-text text-muted">Une la versión con los reportes. Cambia cada año (el número y el año del código).</small>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="form-group mera-form-group mb-0">
                        <label for="v-title">Título de la página <small class="text-muted fw-normal">(opcional)</small></label>
                        <input id="v-title" type="text" name="title" maxlength="150"
                            class="form-control mera-input @error('title') is-invalid @enderror"
                            value="{{ old('title', $version->title) }}" placeholder="{{ $en ? 'E.g. Introduction to Hygiene' : $group->name }}">
                        <small class="form-text text-muted">Si se deja vacío, usa el nombre de la carpeta.{{ $en ? ' Esta página es en inglés.' : '' }}</small>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group mera-form-group mb-0">
                        <label for="v-description">Descripción</label>
                        <textarea id="v-description" name="description" rows="3" maxlength="600"
                            class="form-control mera-input @error('description') is-invalid @enderror"
                            placeholder="{{ $en ? 'E.g. Learn step by step how to take care of the food we serve.' : 'Ej. Aprende paso a paso cómo cuidar los alimentos que servimos.' }}">{{ old('description', $version->description) }}</textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mera-form-group mb-0">
                        <label for="v-evaluation">Link de la evaluación</label>
                        <input id="v-evaluation" type="url" name="evaluation_url" maxlength="500"
                            class="form-control mera-input @error('evaluation_url') is-invalid @enderror"
                            value="{{ old('evaluation_url', $version->evaluation_url) }}" placeholder="https://forms.cloud.microsoft/…">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mera-form-group mb-0">
                        <label for="v-survey">Link de la encuesta</label>
                        <input id="v-survey" type="url" name="survey_url" maxlength="500"
                            class="form-control mera-input @error('survey_url') is-invalid @enderror"
                            value="{{ old('survey_url', $version->survey_url) }}" placeholder="https://forms.cloud.microsoft/…">
                    </div>
                </div>
            </div>

            <div class="mera-form-actions">
                <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> Guardar</button>
                <a href="{{ route('course-groups.versions.show', [$group, $version, 'seccion' => 'link']) }}" class="btn mera-btn-cancel">
                    <i class="fas fa-times" aria-hidden="true"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
