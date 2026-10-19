{{-- Encabezado de los niveles de una carpeta (Carpeta → Año → Versión), con ruta de navegación.
     <x-folder-header :crumbs="[['Carpetas', url], ['Example', url], ['2026', null]]" title="…" subtitle="…">acciones</x-folder-header> --}}
@props(['crumbs' => [], 'title', 'subtitle' => null, 'status' => null])
<nav class="mera-crumbs" aria-label="Ruta">
    <ol>
        @foreach ($crumbs as [$label, $url])
        <li>
            @if ($url && ! $loop->last)
            <a href="{{ $url }}">{{ $label }}</a>
            @else
            <span aria-current="page">{{ $label }}</span>
            @endif
        </li>
        @endforeach
    </ol>
</nav>
<div class="page-header mera-page-header mera-folder-header">
    <div class="header-title-wrapper">
        <span class="header-line" aria-hidden="true"></span>
        <div>
            <h1 class="mera-title h5">{{ $title }} {{ $status }}</h1>
            @if ($subtitle)
            <p class="mera-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot) !== '')
    <div class="mera-folder-header-actions">{{ $slot }}</div>
    @endif
</div>
