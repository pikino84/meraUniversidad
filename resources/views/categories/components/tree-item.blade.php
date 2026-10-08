@php
    $children = $category->childrenRecursive;
    $impact = [
        'subcategories' => $children->count() ? $category->getDescendantIds()->count() - 1 : 0,
        'courses' => $category->getDescendantIds()->sum(fn ($id) => $flat[$id]['courses_count'] ?? 0),
    ];
    $detail = $impact['subcategories'] || $impact['courses']
        ? "Se eliminarán también {$impact['subcategories']} subcategorías y {$impact['courses']} cursos quedarán sin categoría. Esta acción no se puede deshacer."
        : 'Esta acción no se puede deshacer.';
@endphp
<li class="mt-2">
    <div class="card mera-tree-card mb-0">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fa {{ $children->count() ? 'fa-folder mera-folder-icon' : 'fa-folder-open mera-folder-empty-icon' }} me-2" aria-hidden="true"></i>
                    <div>
                        <strong class="mera-tree-title">{{ $category->name }}</strong>
                        <small class="mera-tree-count ms-2">
                            {{ $children->count() }} {{ $children->count() === 1 ? 'subcategoría' : 'subcategorías' }}
                            · {{ $category->courses_count ?? 0 }} {{ ($category->courses_count ?? 0) === 1 ? 'curso' : 'cursos' }}
                        </small>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="{{ route('courses.index', ['category_id' => $category->id]) }}" class="mera-action-btn mera-add-btn"
                        title="Ver cursos" aria-label="Ver cursos de {{ $category->name }}">
                        <i class="fa fa-book" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('categories.create', ['parent' => $category->id]) }}" class="mera-action-btn mera-add-btn"
                        title="Nueva subcategoría" aria-label="Nueva subcategoría de {{ $category->name }}">
                        <i class="fa fa-plus" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('categories.edit', $category) }}" class="mera-action-btn mera-edit-btn"
                        title="Editar" aria-label="Editar categoría {{ $category->name }}">
                        <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                    </a>
                    @include('partials.delete-button', [
                        'action' => route('categories.destroy', $category),
                        'label' => "Eliminar categoría {$category->name}",
                        'confirm' => "¿Eliminar la categoría «{$category->name}»?",
                        'detail' => $detail,
                    ])
                </div>
            </div>
        </div>
    </div>

    @if ($children->count())
    <ul class="list-unstyled mera-tree-children mb-0">
        @foreach ($children as $child)
            @include('categories.components.tree-item', ['category' => $child, 'flat' => $flat])
        @endforeach
    </ul>
    @endif
</li>
