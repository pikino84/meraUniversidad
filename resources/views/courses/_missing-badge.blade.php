{{-- F1: aviso de archivos faltantes (revisión de solo lectura en CourseStorage::missingFiles). --}}
@php $m = $missing[$course->id] ?? ['content' => false, 'cover' => false]; @endphp
@if ($m['content'] || $m['cover'])
<span class="mera-missing-badge" title="Vuelve a subir el ZIP o la portada desde «Editar» para corregirlo.">
    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
    @if ($m['content'] && $m['cover'])
    Archivos no encontrados
    @elseif ($m['content'])
    Contenido no encontrado
    @else
    Portada no encontrada
    @endif
</span>
@endif
