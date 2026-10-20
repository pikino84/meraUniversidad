{{-- Filtros del seguimiento (GET), compactos: año, curso, localidad y área a la vista; ruta, centro y
     "incluir específicos" en "Más filtros". Se aplican al cambiar (auto-submit.js) y con el botón (sin
     JS). Los activos se muestran como etiquetas para quitarlos uno por uno.
     Variables: $action, $filters, $options, $extra (campos ocultos que se conservan, p. ej. vista) --}}
@php
    $extra = array_filter($extra ?? [], fn ($v) => filled($v));
    $labels = [
        'curso' => fn ($v) => $options['courses'][$v] ?? $v,
        'ruta' => fn ($v) => $options['routes'][$v] ?? $v,
        'localidad' => fn ($v) => $v,
        'area' => fn ($v) => $v,
        'centro' => fn ($v) => $v,
        'especificos' => fn ($v) => 'Con cursos específicos',
    ];
    $active = collect($filters)->only(array_keys($labels))->filter()->when(! empty($lockedLocality), fn ($c) => $c->except('localidad'));
    $more = $filters['ruta'] || $filters['centro'] || $filters['especificos'];
    $without = fn (string $key) => $action.'?'.http_build_query(array_filter(array_merge($filters, $extra, [$key => null]), fn ($v) => filled($v)));
@endphp
<form method="GET" action="{{ $action }}" class="mera-filterbar" aria-label="Filtros" data-auto-submit>
    @foreach ($extra as $name => $value)
    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    <div class="mera-filterbar-main">
        <label class="mera-filterbar-field is-year">
            <span>Año</span>
            <select name="anio" class="form-select mera-input">
                @foreach ($options['years'] as $y)
                <option value="{{ $y }}" @selected($filters['anio'] === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </label>
        <label class="mera-filterbar-field is-wide">
            <span>Curso</span>
            <select name="curso" class="form-select mera-input">
                <option value="">Todos los cursos</option>
                @foreach ($options['courses'] as $code => $label)
                <option value="{{ $code }}" @selected($filters['curso'] === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if (empty($lockedLocality))
        <label class="mera-filterbar-field">
            <span>Localidad</span>
            <select name="localidad" class="form-select mera-input">
                <option value="">Todas</option>
                @foreach ($options['localities'] as $value)
                <option value="{{ $value }}" @selected($filters['localidad'] === $value)>{{ $value }}</option>
                @endforeach
            </select>
        </label>
        @endif
        <label class="mera-filterbar-field">
            <span>Área</span>
            <select name="area" class="form-select mera-input">
                <option value="">Todas</option>
                @foreach ($options['areas'] as $value)
                <option value="{{ $value }}" @selected($filters['area'] === $value)>{{ $value }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-sm mera-btn-save mera-filterbar-apply">Aplicar</button>
    </div>

    <details class="mera-filterbar-more" @if ($more) open @endif>
        <summary>Más filtros</summary>
        <div class="mera-filterbar-main">
            <label class="mera-filterbar-field">
                <span>Ruta</span>
                <select name="ruta" class="form-select mera-input">
                    <option value="">Todas</option>
                    @foreach ($options['routes'] as $code => $label)
                    <option value="{{ $code }}" @selected($filters['ruta'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mera-filterbar-field is-wide">
                <span>Centro de trabajo</span>
                <select name="centro" class="form-select mera-input">
                    <option value="">Todos</option>
                    @foreach ($options['centers'] as $value)
                    <option value="{{ $value }}" @selected($filters['centro'] === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mera-tag-check mera-filterbar-toggle" title="Por defecto, las comparaciones entre localidades, áreas y puestos usan solo los cursos generales (para todos).">
                <input type="checkbox" name="especificos" value="1" @checked($filters['especificos'])>
                <span>Incluir cursos específicos en las comparaciones</span>
            </label>
        </div>
    </details>

    @if ($active->isNotEmpty())
    <div class="mera-filterbar-chips" aria-label="Filtros activos">
        @foreach ($active as $key => $value)
        <a href="{{ $without($key) }}" class="mera-filter-chip text-decoration-none" aria-label="Quitar filtro {{ $labels[$key]($value) }}">
            {{ $labels[$key]($value) }} <i class="fas fa-times" aria-hidden="true"></i>
        </a>
        @endforeach
        @if ($active->count() > 1)
        <a href="{{ $action }}?{{ http_build_query(array_filter(['anio' => $filters['anio']] + $extra)) }}" class="small">Quitar todos</a>
        @endif
    </div>
    @endif
</form>
