{{--
    Formulario compartido de alta/edición de cursos.
    Variables: $course (nullable), $categories, $maxUploadBytes, $action, $method, $submitLabel
--}}
@php
    $editing = isset($course);
    $maxUploadMb = (int) floor($maxUploadBytes / 1024 / 1024);
@endphp

<div class="alert alert-danger" role="alert" data-upload-errors hidden></div>
@include('partials.form-errors')

<form action="{{ $action }}" method="POST" enctype="multipart/form-data"
    data-upload-form data-max-bytes="{{ $maxUploadBytes }}">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="course-name">
                    Nombre del curso <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="course-name" type="text" name="name" maxlength="255"
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    placeholder="Nombre del curso"
                    value="{{ old('name', $course->name ?? '') }}" required>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="course-category">Categoría</label>
                @include('categories.components.select', [
                    'name' => 'category_id',
                    'id' => 'course-category',
                    'categories' => $categories,
                    'selected' => old('category_id', $course->category_id ?? null),
                    'placeholder' => '-- Sin categoría --',
                ])
                <small class="form-text text-muted">Puedes cambiarla en cualquier momento.</small>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group mera-form-group mb-3">
                <label for="course-description">
                    Descripción <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <textarea id="course-description" name="description" rows="4" maxlength="5000"
                    class="form-control mera-input @error('description') is-invalid @enderror"
                    placeholder="Descripción del curso" required>{{ old('description', $course->description ?? '') }}</textarea>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="course-cover">
                    Imagen de portada
                    @unless ($editing)<span class="text-danger" aria-hidden="true">*</span>@endunless
                </label>

                @if ($editing && $course->cover_url)
                <div class="mb-2">
                    <img src="{{ $course->cover_url }}" alt="Portada actual de {{ $course->name }}" width="200" class="img-thumbnail" loading="lazy">
                </div>
                @endif

                <input id="course-cover" type="file" name="cover_image" class="form-control"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" @unless ($editing) required @endunless
                    aria-describedby="course-cover-help">
                <small id="course-cover-help" class="form-text text-muted">
                    JPG, PNG o WEBP, máximo {{ config('mera.courses.cover_max_kb') / 1024 }} MB.
                    @if ($editing) Déjalo vacío para conservar la actual. @endif
                </small>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="course-zip">
                    Paquete del curso (ZIP)
                    @unless ($editing)<span class="text-danger" aria-hidden="true">*</span>@endunless
                </label>
                <input id="course-zip" type="file" name="zip_file" class="form-control" accept=".zip,application/zip"
                    @unless ($editing) required @endunless aria-describedby="course-zip-help">
                <small id="course-zip-help" class="form-text text-muted">
                    Máximo {{ number_format($maxUploadMb) }} MB. Debe incluir un <code>index.html</code>.
                    Solo se aceptan archivos web, multimedia y documentos (no scripts de servidor).
                    @if ($editing) Déjalo vacío para conservar el contenido actual. @endif
                </small>
            </div>
        </div>
    </div>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fa fa-save" aria-hidden="true"></i>
            {{ $submitLabel }}
        </button>
        <a href="{{ route('courses.index') }}" class="btn mera-btn-cancel">
            <i class="fa fa-times" aria-hidden="true"></i>
            Cancelar
        </a>
    </div>
</form>

<div class="mera-upload-overlay" data-upload-overlay hidden role="dialog" aria-modal="true" aria-labelledby="upload-title">
    <div class="mera-upload-box">
        <div id="upload-title" class="h5 text-white mb-0">Procesando curso</div>
        <div class="mera-upload-track">
            <div class="mera-upload-bar" data-upload-bar role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
        </div>
        <div class="mera-upload-percent" data-upload-percent>0%</div>
        <div class="mera-upload-status" data-upload-status aria-live="polite">Preparando…</div>
        <button type="button" class="mera-upload-cancel" data-upload-cancel>Cancelar subida</button>
    </div>
</div>
