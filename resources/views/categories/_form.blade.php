{{-- Variables: $action, $method, $category (nullable), $categories, $selectedParent, $excludedIds, $submitLabel --}}
@include('partials.form-errors')

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
    @method($method)
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="form-group mera-form-group mb-3">
                <label for="category-name">
                    Nombre de la categoría <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input id="category-name" type="text" name="name" maxlength="255"
                    class="form-control mera-input @error('name') is-invalid @enderror"
                    value="{{ old('name', $category->name ?? '') }}"
                    placeholder="Ejemplo: Seguridad e higiene" required>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group mera-form-group mb-3">
                <label for="category-parent">Categoría padre</label>
                @include('categories.components.select', [
                    'name' => 'parent_id',
                    'id' => 'category-parent',
                    'categories' => $categories,
                    'selected' => old('parent_id', $selectedParent),
                    'placeholder' => '-- Categoría principal --',
                    'excludedIds' => $excludedIds,
                ])
                <small class="text-muted">
                    Si eliges una categoría padre, esta será una subcategoría.
                    @isset($category) No puedes elegir esta misma categoría ni sus subcategorías. @endisset
                </small>
            </div>
        </div>
    </div>

    <div class="mera-form-actions">
        <button type="submit" class="btn mera-btn-save">
            <i class="fas fa-save" aria-hidden="true"></i> {{ $submitLabel }}
        </button>
        <a href="{{ route('categories.index') }}" class="btn mera-btn-cancel">
            <i class="fas fa-times" aria-hidden="true"></i> Cancelar
        </a>
    </div>
</form>
