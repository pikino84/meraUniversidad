@extends('layouts.app')

@section('title', 'Versiones')

@section('content')
@include('partials.page-header', [
    'title' => 'Versiones',
    'subtitle' => 'Versiones por país de los cursos (México, Latam, EUA…). El orden es el de las pestañas.',
    'action' => ['url' => route('markets.create'), 'label' => 'Nueva versión'],
])

<div class="mera-notice mb-3" role="note">
    Cada carpeta de curso tiene, por año, una versión por país. Para dividir una versión (p. ej. Latam en
    Panamá y Colombia), crea la nueva con «Pertenece a» = Latam: mientras no tenga módulos, su página muestra
    la de Latam. Una versión que ya se usa no se puede borrar: desactívala.
</div>

<div class="card mera-table-card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">Versión</th>
                        <th scope="col">Código</th>
                        <th scope="col">Link</th>
                        <th scope="col">Página en</th>
                        <th scope="col">Usada en</th>
                        <th scope="col" class="text-center mera-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($markets as $market)
                    <tr>
                        <td data-label="Versión">
                            <span class="user-name">{{ $market->name }}</span>
                            @if ($market->parent)
                            <small class="d-block text-muted">Pertenece a {{ $market->parent->name }}</small>
                            @endif
                            @unless ($market->is_active)
                            <span class="mera-status mera-status-off mt-1"><i class="fas fa-circle" aria-hidden="true"></i> Inactiva</span>
                            @endunless
                        </td>
                        <td data-label="Código"><code class="mera-code-badge">{{ $market->code }}</code></td>
                        <td data-label="Link"><code class="mera-code-badge">…/{{ $market->slug }}</code></td>
                        <td data-label="Página en">{{ $market->language === 'en' ? 'Inglés' : 'Español' }}@if ($market->hreflang) <small class="text-muted">({{ $market->hreflang }})</small>@endif</td>
                        <td data-label="Usada en">{{ $market->versions_count }} {{ $market->versions_count === 1 ? 'versión' : 'versiones' }}</td>
                        <td data-label="Acciones" class="text-center text-nowrap mera-col-actions">
                            <form method="POST" action="{{ route('markets.move', $market) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="direction" value="up">
                                <button class="mera-action-btn mera-edit-btn" title="Subir" aria-label="Subir {{ $market->name }}" @disabled($loop->first)>
                                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('markets.move', $market) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="direction" value="down">
                                <button class="mera-action-btn mera-edit-btn" title="Bajar" aria-label="Bajar {{ $market->name }}" @disabled($loop->last)>
                                    <i class="fas fa-arrow-down" aria-hidden="true"></i>
                                </button>
                            </form>
                            <a href="{{ route('markets.edit', $market) }}" class="mera-action-btn mera-edit-btn" title="Editar" aria-label="Editar {{ $market->name }}">
                                <i class="fas fa-pencil-alt" aria-hidden="true"></i>
                            </a>
                            @if ($market->versions_count === 0)
                            @include('partials.delete-button', [
                                'action' => route('markets.destroy', $market),
                                'label' => "Eliminar versión {$market->name}",
                                'confirm' => '¿Eliminar la versión?',
                                'strong' => $market->name,
                                'detail' => 'No se usa en ninguna carpeta. Su link dejará de existir.',
                            ])
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
