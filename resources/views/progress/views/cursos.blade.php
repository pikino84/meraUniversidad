{{-- Pestaña Cursos: todos los cursos con orden elegible, y los específicos y presenciales. --}}
<section class="card mera-table-card" aria-labelledby="c-cursos">
    <div class="card-body">
        <div class="mera-section-toolbar">
            <div>
                <h2 id="c-cursos" class="h6 mera-track-title mb-1">Cómo va cada curso</h2>
                @include('progress._legend')
            </div>
            <nav class="mera-sort" aria-label="Ordenar">
                <span class="small text-muted">Ordenar:</span>
                @foreach (['avance-asc' => 'Menor avance', 'avance-desc' => 'Mayor avance', 'nombre' => 'Nombre'] as $key => $label)
                <a href="{{ $with(['orden' => $key]) }}" class="mera-sort-option {{ $sort === $key ? 'is-active' : '' }}" @if ($sort === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
        @include('progress._bars', ['rows' => $courses, 'deltas' => $deltas['courses'] ?? [], 'link' => $filters['curso'] ? null : fn ($code) => $with(['curso' => $code])])
    </div>
</section>

<section class="card mera-table-card mt-3" aria-labelledby="t-especificos">
    <div class="card-body">
        <h2 id="t-especificos" class="h6 mera-track-title">Cursos específicos y presenciales</h2>
        <p class="small text-muted">
            Cursos para ciertas áreas o puestos, y los presenciales (BL). No se comparan entre localidades: cada uno muestra a quién va.
            En un BL, el avance mide las <strong>actividades en línea</strong>, no la asistencia presencial.
        </p>
        @if ($specific->isEmpty())
        <p class="text-muted small mb-0">No hay cursos específicos ni presenciales con estos filtros.</p>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack mb-0">
                <thead>
                    <tr>
                        <th scope="col">Curso</th>
                        <th scope="col">A quién va</th>
                        <th scope="col">Realizado</th>
                        <th scope="col">Aprobado</th>
                        <th scope="col">Sin iniciar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($specific as $s)
                    <tr>
                        <td data-label="Curso">
                            <a href="{{ $with(['curso' => $s['k']]) }}" class="user-name">{{ $s['label'] }}</a>
                            <span class="d-block mt-1">
                                @if ($s['specific'])<span class="mera-scope-badge is-specific">Específico</span>@endif
                                <span class="mera-scope-badge {{ $s['modality'] === 'BL' ? 'is-bl' : '' }}">{{ $s['modality'] === 'BL' ? 'BL · presencial' : $s['modality'] }}</span>
                            </span>
                            <small class="d-block text-muted">{{ number_format($s['assigned']) }} asignados</small>
                        </td>
                        <td data-label="A quién va">
                            <small class="d-block">{{ collect($s['areas'])->map(fn ($n, $a) => $a.' ('.number_format($n).')')->join(', ') }}</small>
                            <small class="d-block text-muted">{{ collect($s['positions'])->map(fn ($n, $p) => $p.' ('.number_format($n).')')->join(', ') }}</small>
                        </td>
                        <td data-label="Realizado" class="mera-nowrap">{{ round($s['pct_done']) }} %</td>
                        <td data-label="Aprobado" class="mera-nowrap">{{ round($s['pct_passed']) }} %</td>
                        <td data-label="Sin iniciar" class="mera-nowrap">{{ round($s['pct_not_started']) }} %</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>
