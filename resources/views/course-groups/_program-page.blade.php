{{-- Carpeta que alimenta un programa de config('mera.programs'). Su página pública ES la del
     programa (link ya compartido); la de la carpeta redirige allá. Variables: $group, $programLinks --}}
<section class="mera-public-card is-published" aria-labelledby="pagina-publica">
    <div class="mera-public-head">
        <div>
            <h2 id="pagina-publica" class="h6 mb-1">
                <i class="fas fa-globe" aria-hidden="true"></i> Página pública del programa
            </h2>
            <small class="text-muted">
                Esta carpeta alimenta la página del programa: el orden de los módulos y los links de evaluación
                y encuesta de su versión («Editar versión») se reflejan ahí. Los links no cambian.
            </small>
        </div>
    </div>

    <ul class="mera-public-links list-unstyled mb-0">
        @foreach (\App\Models\CourseGroup::LANGUAGES as $code => $label)
        <li class="mera-public-link">
            <span class="mera-lang-pill {{ $code === 'en' ? 'mera-lang-pill-en' : '' }}">{{ $label }}</span>
            @if (isset($programLinks[$code]))
            <code class="mera-public-url">{{ $programLinks[$code] }}</code>
            <span class="mera-public-actions">
                <button type="button" class="btn btn-sm mera-btn-ghost" data-copy="{{ $programLinks[$code] }}" aria-label="Copiar link en {{ $label }}">
                    <i class="fas fa-copy" aria-hidden="true"></i> <span data-copy-label>Copiar link</span>
                </button>
                <a href="{{ $programLinks[$code] }}" target="_blank" rel="noopener" class="btn btn-sm mera-btn-ghost"
                    aria-label="Ver la página en {{ $label }} (nueva pestaña)">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ver
                </a>
            </span>
            @else
            <small class="mera-public-warn">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                Sin módulos en {{ strtolower($label) }}: esta versión no se muestra al público. Agrégalos en la versión que usa el programa (config/mera.php).
            </small>
            @endif
        </li>
        @endforeach
    </ul>
</section>
