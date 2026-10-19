{{-- Sección "Link para compartir" de una versión (se publica POR VERSIÓN,
     docs/carpetas/INSTRUCTIVO_VERSIONES.md §5). Variables: $group, $version --}}
@php
    $published = $version->is_published;
    $url = $version->publicUrl();
    $empty = $version->modules_count === 0;
@endphp
<section class="card mera-table-card" aria-labelledby="link-version">
    <div class="card-body">
        <div class="mera-share-head">
            <div>
                <h2 id="link-version" class="h6 mb-1">Página pública</h2>
                @if ($published)
                <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Publicada</span>
                <small class="text-muted ms-1">Cualquiera con el link puede verla.</small>
                @else
                <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Apagada</span>
                <small class="text-muted ms-1">Solo administradores la ven (vista previa).</small>
                @endif
            </div>
            <form method="POST" action="{{ route('course-groups.versions.publish', [$group, $version]) }}"
                data-confirm="{{ $published ? '¿Apagar la página?' : '¿Publicar la página?' }}" data-confirm-tone="info"
                data-confirm-strong="{{ $group->name }} · {{ $version->label() }}"
                data-confirm-detail="{{ $published ? 'Quien tenga el link verá «no encontrado» hasta que la vuelvas a publicar.' : 'Cualquier persona con el link podrá ver el curso y abrir sus módulos.' }}"
                data-confirm-button="{{ $published ? 'Apagar' : 'Publicar' }}">
                @csrf @method('PATCH')
                <input type="hidden" name="published" value="{{ $published ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm {{ $published ? 'mera-btn-ghost' : 'mera-btn-primary' }}" @disabled(! $published && $empty)>
                    <i class="fas {{ $published ? 'fa-eye-slash' : 'fa-globe' }}" aria-hidden="true"></i>
                    {{ $published ? 'Apagar página' : 'Publicar página' }}
                </button>
            </form>
        </div>

        <div class="mera-share-url">
            <code class="mera-public-url">{{ $url }}</code>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm mera-btn-ghost" data-copy="{{ $url }}" aria-label="Copiar link de {{ $version->label() }}">
                    <i class="fas fa-copy" aria-hidden="true"></i> <span data-copy-label>Copiar link</span>
                </button>
                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm mera-btn-ghost"
                    aria-label="{{ $published ? 'Ver' : 'Vista previa de' }} la página de {{ $version->label() }} (nueva pestaña)">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> {{ $published ? 'Ver' : 'Vista previa' }}
                </a>
            </div>
        </div>
        @if ($empty)
        <p class="mera-public-warn mb-0">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            Sin módulos: agrégalos antes de publicar.
            @if ($version->market->parent)
            Mientras tanto, el link muestra la versión {{ $version->market->parent->name }}.
            @endif
        </p>
        @endif
    </div>
</section>

<section class="card mera-table-card mt-3" aria-labelledby="datos-pagina">
    <div class="card-body">
        <div class="mera-share-head">
            <h2 id="datos-pagina" class="h6 mb-0">Lo que muestra la página</h2>
            <a href="{{ route('course-groups.versions.edit', [$group, $version]) }}" class="btn btn-sm mera-btn-ghost">
                <i class="fas fa-pencil-alt" aria-hidden="true"></i> Editar
            </a>
        </div>
        <dl class="mera-facts mb-0">
            <dt>Título</dt>
            <dd>{{ $version->displayTitle() }}</dd>
            <dt>Descripción</dt>
            <dd>{{ $version->description ?: '—' }}</dd>
            <dt>Evaluación</dt>
            <dd>@if ($version->evaluation_url)<a href="{{ $version->evaluation_url }}" target="_blank" rel="noopener">{{ $version->evaluation_url }}</a>@else — @endif</dd>
            <dt>Encuesta</dt>
            <dd>@if ($version->survey_url)<a href="{{ $version->survey_url }}" target="_blank" rel="noopener">{{ $version->survey_url }}</a>@else — @endif</dd>
            <dt>Código del catálogo</dt>
            <dd>{{ $version->catalog_code ?: '—' }}</dd>
        </dl>
    </div>
</section>
