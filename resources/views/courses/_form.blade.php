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

@if ($editing && ! empty($missing) && ($missing['content'] || $missing['cover']))
{{-- F1: archivos que no están en el servidor --}}
<div class="alert alert-warning" role="alert">
    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
    <strong>Archivos no encontrados en el servidor:</strong>
    {{ collect([$missing['content'] ? 'el contenido del módulo (index.html)' : null, $missing['cover'] ? 'la portada' : null])->filter()->implode(' y ') }}.
    Vuelve a subir {{ $missing['content'] ? 'el ZIP' : 'la imagen' }} en este formulario para corregirlo.
</div>
@endif

<form action="{{ $action }}" method="POST" enctype="multipart/form-data"
    data-upload-form data-warn-unsaved data-max-bytes="{{ $maxUploadBytes }}">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-3">
                <label for="course-name">
                    Nombre del módulo <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="course-name" type="text" name="name" maxlength="255"
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    placeholder="Nombre del módulo"
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

        {{-- Carpeta de curso → idioma → posición (CLAUDE.md §10). Todo opcional. --}}
        @php
            $selectedGroup = old('course_group_id', $course->course_group_id ?? request('carpeta'));
            $selectedLanguage = old('language', $course->language ?? request('idioma', 'es'));
        @endphp
        <div class="col-12">
            <fieldset class="mera-fieldset mb-3">
                <legend>Carpeta del curso <small class="text-muted fw-normal">(opcional)</small></legend>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="course-group" class="form-label">Carpeta</label>
                        <select id="course-group" name="course_group_id"
                            class="form-control form-select mera-input @error('course_group_id') is-invalid @enderror"
                            data-group-select>
                            <option value="">— Sin carpeta —</option>
                            @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((string) $selectedGroup === (string) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                        @if ($groups->isEmpty())
                        <small class="form-text text-muted">
                            Aún no hay carpetas. <a href="{{ route('course-groups.create') }}">Crea la primera</a>.
                        </small>
                        @endif
                    </div>
                    <div class="col-6 col-md-3" data-group-dependent>
                        <label for="course-language" class="form-label">Idioma</label>
                        <select id="course-language" name="language"
                            class="form-control form-select mera-input @error('language') is-invalid @enderror">
                            @foreach (\App\Models\CourseGroup::LANGUAGES as $code => $label)
                            <option value="{{ $code }}" @selected($selectedLanguage === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3" data-group-dependent>
                        <label for="course-position" class="form-label">N.º de módulo</label>
                        <input id="course-position" type="number" name="position" min="1" max="999" inputmode="numeric"
                            class="form-control mera-input @error('position') is-invalid @enderror"
                            value="{{ old('position', $course->position ?? '') }}" placeholder="Al final">
                    </div>
                </div>
                <small class="form-text text-muted">
                    El link del módulo no cambia al moverlo de carpeta.
                </small>
            </fieldset>
        </div>

        <div class="col-md-12">
            <div class="form-group mera-form-group mb-3">
                <label for="course-description">
                    Descripción <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <textarea id="course-description" name="description" rows="4" maxlength="5000"
                    class="form-control mera-input @error('description') is-invalid @enderror"
                    placeholder="Descripción del módulo" required>{{ old('description', $course->description ?? '') }}</textarea>
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
                    @include('partials.cover', ['class' => 'img-thumbnail mera-cover-preview', 'width' => 200, 'height' => 112])
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
                    Paquete del módulo (ZIP)
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
        <div id="upload-title" class="h5 text-white mb-0">Procesando módulo</div>
        <div class="mera-upload-track">
            <div class="mera-upload-bar" data-upload-bar role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
        </div>
        <div class="mera-upload-percent" data-upload-percent>0%</div>
        <div class="mera-upload-status" data-upload-status aria-live="polite">Preparando…</div>
        <button type="button" class="mera-upload-cancel" data-upload-cancel>Cancelar subida</button>
    </div>
</div>
