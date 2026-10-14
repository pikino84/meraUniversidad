{{-- Recuadro "Página pública" dentro de la carpeta. Variables: $group, $counts --}}
@php $published = $group->is_published; @endphp
<section class="mera-public-card {{ $published ? 'is-published' : '' }}" aria-labelledby="pagina-publica">
    <div class="mera-public-head">
        <div>
            <h2 id="pagina-publica" class="h6 mb-1">
                <i class="fas fa-globe" aria-hidden="true"></i> Página pública
            </h2>
            @if ($published)
            <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Publicada</span>
            <small class="text-muted ms-1">Cualquiera con el link puede verla.</small>
            @else
            <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Apagada</span>
            <small class="text-muted ms-1">Solo administradores pueden verla (vista previa).</small>
            @endif
        </div>

        <form method="POST" action="{{ route('course-groups.publish', $group) }}"
            data-confirm="{{ $published ? '¿Apagar la página pública?' : '¿Publicar la página?' }}"
            data-confirm-detail="{{ $published ? 'Quien tenga el link verá «no encontrado» hasta que la vuelvas a publicar.' : 'Cualquier persona con el link podrá ver el curso y abrir sus módulos.' }}"
            data-confirm-button="{{ $published ? 'Apagar' : 'Publicar' }}">
            @csrf @method('PATCH')
            <input type="hidden" name="published" value="{{ $published ? 0 : 1 }}">
            <button type="submit" class="btn {{ $published ? 'mera-btn-cancel' : 'mera-btn-primary' }}">
                <i class="fas {{ $published ? 'fa-eye-slash' : 'fa-globe' }}" aria-hidden="true"></i>
                {{ $published ? 'Apagar página' : 'Publicar página' }}
            </button>
        </form>
    </div>

    <ul class="mera-public-links list-unstyled mb-0">
        @foreach (\App\Models\CourseGroup::LANGUAGES as $code => $label)
        @php
            $url = $group->publicUrl($code);
            $hasModules = ($counts[$code] ?? 0) > 0;
        @endphp
        <li class="mera-public-link">
            <span class="mera-lang-pill {{ $code === 'en' ? 'mera-lang-pill-en' : '' }}">{{ $label }}</span>
            <code class="mera-public-url" id="url-{{ $code }}">{{ $url }}</code>
            <span class="mera-public-actions">
                <button type="button" class="btn btn-sm mera-btn-cancel" data-copy="{{ $url }}"
                    aria-label="Copiar link en {{ $label }}">
                    <i class="fas fa-copy" aria-hidden="true"></i> <span data-copy-label>Copiar link</span>
                </button>
                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-sm mera-btn-cancel"
                    aria-label="{{ $published ? 'Ver' : 'Vista previa de' }} la página en {{ $label }} (nueva pestaña)">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> {{ $published ? 'Ver' : 'Vista previa' }}
                </a>
            </span>
            @unless ($hasModules)
            <small class="mera-public-warn">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                Sin módulos en {{ strtolower($label) }}: esta versión no se muestra al público.
            </small>
            @endunless
        </li>
        @endforeach
    </ul>
</section>
