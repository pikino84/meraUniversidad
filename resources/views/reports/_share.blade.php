{{-- Link para compartir el corte sin sesión (decisión del usuario, INSTRUCTIVO_REPORTES.md §10).
     Variables: $group, $report --}}
<section class="mera-public-card {{ $report->isShared() ? 'is-published' : '' }} mt-3" aria-labelledby="compartir-reporte">
    <div class="mera-public-head">
        <div>
            <h2 id="compartir-reporte" class="h6 mb-1"><i class="fas fa-share-alt" aria-hidden="true"></i> Compartir sin sesión</h2>
            @if ($report->isShared())
            <span class="mera-status mera-status-on"><i class="fas fa-circle" aria-hidden="true"></i> Link activo</span>
            <small class="text-muted ms-1">Cualquiera con el link puede ver este corte (solo totales, sin datos personales).</small>
            @else
            <span class="mera-status mera-status-off"><i class="fas fa-circle" aria-hidden="true"></i> Sin link</span>
            <small class="text-muted ms-1">Solo se ve dentro del panel.</small>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($report->isShared())
            <form method="POST" action="{{ route('course-groups.reports.share', [$group, $report]) }}"
                data-confirm="¿Generar un link nuevo?" data-confirm-detail="El link actual dejará de funcionar para quien ya lo tenga." data-confirm-tone="info" data-confirm-button="Generar">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="regenerate">
                <button type="submit" class="btn btn-sm mera-btn-cancel"><i class="fas fa-sync-alt" aria-hidden="true"></i> Regenerar</button>
            </form>
            <form method="POST" action="{{ route('course-groups.reports.share', [$group, $report]) }}"
                data-confirm="¿Desactivar el link?" data-confirm-detail="Quien tenga el link verá «no encontrado»." data-confirm-tone="info" data-confirm-button="Desactivar">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn btn-sm mera-btn-cancel"><i class="fas fa-ban" aria-hidden="true"></i> Desactivar</button>
            </form>
            @else
            <form method="POST" action="{{ route('course-groups.reports.share', [$group, $report]) }}"
                data-confirm="¿Activar el link para compartir?" data-confirm-detail="Cualquier persona con el link podrá ver los totales de este corte, sin iniciar sesión." data-confirm-tone="info" data-confirm-button="Activar">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="enable">
                <button type="submit" class="btn btn-sm mera-btn-primary"><i class="fas fa-link" aria-hidden="true"></i> Activar link</button>
            </form>
            @endif
        </div>
    </div>

    @if ($report->isShared())
    <ul class="mera-public-links list-unstyled mb-0">
        <li class="mera-public-link">
            <code class="mera-public-url">{{ $report->shareUrl() }}</code>
            <span class="mera-public-actions">
                <button type="button" class="btn btn-sm mera-btn-cancel" data-copy="{{ $report->shareUrl() }}" aria-label="Copiar link del reporte">
                    <i class="fas fa-copy" aria-hidden="true"></i> <span data-copy-label>Copiar link</span>
                </button>
                <a href="{{ $report->shareUrl() }}" target="_blank" rel="noopener" class="btn btn-sm mera-btn-cancel" aria-label="Ver el reporte compartido (nueva pestaña)">
                    <i class="fas fa-external-link-alt" aria-hidden="true"></i> Ver
                </a>
            </span>
        </li>
    </ul>
    @endif
</section>
