{{-- Variables: $action, $method, $group (nullable), $submitLabel
     La descripción, la evaluación, la encuesta y la publicación son de cada versión
     (course-groups/versions/edit), no de la carpeta. --}}
@php
    $editing = isset($group);
    $base = rtrim(url('/'), '/').'/';
    $published = $editing && $group->versions()->where('is_published', true)->exists();
@endphp
@include('partials.form-errors')

<form action="{{ $action }}" method="POST" data-warn-unsaved>
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row g-3">
        <div class="col-12">
            <div class="form-group mera-form-group mb-0">
                <label for="group-name">Nombre del curso <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="group-name" type="text" name="name" maxlength="150" required @unless ($editing) autofocus @endunless
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    value="{{ old('name', $group->name ?? '') }}" placeholder="Ej. NRM-HIG-002-EL-26 | Introducción a la Higiene">
                <small class="form-text text-muted">Se puede usar el código del catálogo al inicio («CÓDIGO | Título»).</small>
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
                        value="{{ old('slug', $group->slug ?? '') }}" placeholder="introduccion-a-la-higiene">
                </div>
                <small id="group-slug-help" class="form-text text-muted">
                    Solo minúsculas, números y guiones. Cada versión lleva su nombre antes del link
                    (p. ej. <code>/eua/introduccion-a-la-higiene</code>); México va sin prefijo.
                    @if ($published)
                    <strong class="text-danger d-block mt-1">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        Hay páginas publicadas: si cambias el link, el anterior dejará de funcionar para quien ya lo tenga.
                    </strong>
                    @endif
                </small>
            </div>
        </div>
    </div>

    <p class="small text-muted mt-3 mb-0">
        La descripción, los links de evaluación y encuesta y la publicación se configuran en cada versión
        («Editar versión», dentro de la carpeta).
    </p>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ $editing ? route('course-groups.show', $group) : route('course-groups.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
