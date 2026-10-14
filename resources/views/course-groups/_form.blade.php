{{-- Variables: $action, $method, $group (nullable), $submitLabel --}}
@php
    $editing = isset($group);
    $base = rtrim(url('/'), '/').'/';
@endphp
@include('partials.form-errors')

<form action="{{ $action }}" method="POST" data-warn-unsaved>
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="group-name">Nombre del curso <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="group-name" type="text" name="name" maxlength="150" required @unless ($editing) autofocus @endunless
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    value="{{ old('name', $group->name ?? '') }}" placeholder="Ej. Manipulación de alimentos">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="group-name-en">Nombre en inglés <small class="text-muted fw-normal">(opcional)</small></label>
                <input id="group-name-en" type="text" name="name_en" maxlength="150"
                    class="form-control mera-input @error('name_en') is-invalid @enderror"
                    value="{{ old('name_en', $group->name_en ?? '') }}" placeholder="Ej. Food handling">
                <small class="form-text text-muted">Título de la página en inglés. Si se deja vacío, usa el nombre en español.</small>
            </div>
        </div>

        <div class="col-12">
            <div class="form-group mera-form-group mb-0">
                <label for="group-slug">Link de la página
                    @unless ($editing)<small class="text-muted fw-normal">(opcional: se crea con el nombre)</small>@endunless
                </label>
                <div class="mera-slug-field @error('slug') is-invalid @enderror">
                    <span class="mera-slug-prefix" aria-hidden="true">{{ $base }}</span>
                    <input id="group-slug" type="text" name="slug" maxlength="120" inputmode="url" autocomplete="off"
                        pattern="{{ \App\Models\CourseGroup::SLUG_PATTERN }}" @if ($editing) required @endif
                        class="form-control mera-input" aria-describedby="group-slug-help"
                        value="{{ old('slug', $group->slug ?? '') }}" placeholder="manipulacion-de-alimentos">
                </div>
                <small id="group-slug-help" class="form-text text-muted">
                    Solo minúsculas, números y guiones. La versión en inglés es el mismo link con <code>/en</code> al final.
                    @if ($editing && $group->is_published)
                    <strong class="text-danger d-block mt-1">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        La página está publicada: si cambias el link, el anterior dejará de funcionar para quien ya lo tenga.
                    </strong>
                    @endif
                </small>
            </div>
        </div>
    </div>

    <fieldset class="mera-fieldset mt-4 mb-3">
        <legend>Página pública <small class="text-muted fw-normal">(todo opcional)</small></legend>
        <p class="text-muted small mb-3">
            Texto de presentación y links de la evaluación y la encuesta (Microsoft Forms).
            Si un campo en inglés queda vacío, la página en inglés usa el de español.
        </p>

        <div class="row g-3">
            @foreach (['es' => 'español', 'en' => 'inglés'] as $code => $label)
            <div class="col-lg-6">
                <h3 class="h6 mera-fieldset-sub">{{ ucfirst($label) }}</h3>
                <div class="form-group mera-form-group mb-3">
                    <label for="group-description-{{ $code }}">Descripción</label>
                    <textarea id="group-description-{{ $code }}" name="description_{{ $code }}" rows="3" maxlength="600"
                        class="form-control mera-input @error('description_'.$code) is-invalid @enderror"
                        placeholder="{{ $code === 'es' ? 'Ej. Aprende paso a paso cómo cuidar los alimentos que servimos.' : 'E.g. Learn step by step how to take care of the food we serve.' }}">{{ old('description_'.$code, $group->{'description_'.$code} ?? '') }}</textarea>
                </div>
                <div class="form-group mera-form-group mb-3">
                    <label for="group-evaluation-{{ $code }}">Link de la evaluación</label>
                    <input id="group-evaluation-{{ $code }}" type="url" name="evaluation_url_{{ $code }}" maxlength="500"
                        class="form-control mera-input @error('evaluation_url_'.$code) is-invalid @enderror"
                        value="{{ old('evaluation_url_'.$code, $group->{'evaluation_url_'.$code} ?? '') }}"
                        placeholder="https://forms.cloud.microsoft/…">
                </div>
                <div class="form-group mera-form-group mb-0">
                    <label for="group-survey-{{ $code }}">Link de la encuesta</label>
                    <input id="group-survey-{{ $code }}" type="url" name="survey_url_{{ $code }}" maxlength="500"
                        class="form-control mera-input @error('survey_url_'.$code) is-invalid @enderror"
                        value="{{ old('survey_url_'.$code, $group->{'survey_url_'.$code} ?? '') }}"
                        placeholder="https://forms.cloud.microsoft/…">
                </div>
            </div>
            @endforeach
        </div>
    </fieldset>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ $editing ? route('course-groups.show', $group) : route('course-groups.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
