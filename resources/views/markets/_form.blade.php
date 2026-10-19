{{-- Variables: $action, $method, $market (nullable), $parents, $submitLabel --}}
@php $editing = isset($market); @endphp
@include('partials.form-errors')

<form action="{{ $action }}" method="POST" data-warn-unsaved>
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="m-name">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="m-name" type="text" name="name" maxlength="60" required @unless ($editing) autofocus @endunless
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    value="{{ old('name', $market->name ?? '') }}" placeholder="Ej. Panamá">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="m-code">Código <span class="text-danger" aria-hidden="true">*</span></label>
                <input id="m-code" type="text" name="code" maxlength="20" required autocomplete="off"
                    class="form-control mera-input @error('code') is-invalid @enderror"
                    value="{{ old('code', $market->code ?? '') }}" placeholder="Ej. PA">
                <small class="form-text text-muted">En mayúsculas. Si el catálogo usa un sufijo para este país (como <code>-MX</code>), pon el mismo.</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="m-slug">Link <span class="text-danger" aria-hidden="true">*</span></label>
                <div class="mera-slug-field @error('slug') is-invalid @enderror">
                    <span class="mera-slug-prefix" aria-hidden="true">{{ rtrim(url('/'), '/') }}/</span>
                    <input id="m-slug" type="text" name="slug" maxlength="40" required autocomplete="off" inputmode="url"
                        pattern="{{ \App\Models\CourseGroup::SLUG_PATTERN }}"
                        class="form-control mera-input" value="{{ old('slug', $market->slug ?? '') }}" placeholder="panama">
                </div>
                <small class="form-text text-muted">
                    Va al final del link de cada curso: <code>/introduccion-a-la-higiene/2026/{{ old('slug', $market->slug ?? 'panama') }}</code>.
                    @if ($editing && $market->versions()->where('is_published', true)->exists())
                    <strong class="text-danger d-block mt-1">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        Hay páginas publicadas con esta versión: si cambias el link, los anteriores dejarán de funcionar.
                    </strong>
                    @endif
                </small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mera-form-group mb-0">
                <label for="m-language">Página en</label>
                <select id="m-language" name="language" class="form-select mera-input">
                    <option value="es" @selected(old('language', $market->language ?? 'es') === 'es')>Español</option>
                    <option value="en" @selected(old('language', $market->language ?? 'es') === 'en')>Inglés</option>
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group mera-form-group mb-0">
                <label for="m-hreflang">Idioma y país <small class="text-muted fw-normal">(opcional)</small></label>
                <input id="m-hreflang" type="text" name="hreflang" maxlength="10" autocomplete="off"
                    class="form-control mera-input @error('hreflang') is-invalid @enderror"
                    value="{{ old('hreflang', $market->hreflang ?? '') }}" placeholder="es-PA">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mera-form-group mb-0">
                <label for="m-parent">Pertenece a <small class="text-muted fw-normal">(opcional)</small></label>
                <select id="m-parent" name="parent_id" class="form-select mera-input">
                    <option value="">— Ninguna —</option>
                    @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $market->parent_id ?? '') === (string) $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select>
                <small class="form-text text-muted">Si esta versión no tiene módulos en un curso, su página muestra la de la versión a la que pertenece.</small>
            </div>
        </div>
        <div class="col-md-6 d-flex align-items-center">
            <input type="hidden" name="is_active" value="0">
            <label class="mera-show-passwords mt-3">
                <input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $market->is_active ?? true))>
                Activa (se ofrece al mover y subir módulos)
            </label>
        </div>
    </div>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save"><i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}</button>
        <a href="{{ route('markets.index') }}" class="btn mera-btn-cancel"><i class="fas fa-times" aria-hidden="true"></i> Cancelar</a>
    </div>
</form>
