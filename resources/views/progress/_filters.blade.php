{{-- Filtros del seguimiento (GET). Variables: $action, $filters, $options, $extra (campos ocultos) --}}
<form method="GET" action="{{ $action }}" class="mera-track-filters" aria-label="Filtros">
    @foreach ($extra ?? [] as $name => $value)
    @if (filled($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
    @endforeach

    <div>
        <label for="f-anio" class="form-label">Año</label>
        <select id="f-anio" name="anio" class="form-select mera-input">
            @foreach ($options['years'] as $y)
            <option value="{{ $y }}" @selected($filters['anio'] === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    <div class="mera-track-filter-wide">
        <label for="f-curso" class="form-label">Curso</label>
        <select id="f-curso" name="curso" class="form-select mera-input">
            <option value="">Todos los cursos</option>
            @foreach ($options['courses'] as $code => $label)
            <option value="{{ $code }}" @selected($filters['curso'] === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="f-ruta" class="form-label">Ruta</label>
        <select id="f-ruta" name="ruta" class="form-select mera-input">
            <option value="">Todas</option>
            @foreach ($options['routes'] as $code => $label)
            <option value="{{ $code }}" @selected($filters['ruta'] === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="f-localidad" class="form-label">Localidad</label>
        <select id="f-localidad" name="localidad" class="form-select mera-input">
            <option value="">Todas</option>
            @foreach ($options['localities'] as $value)
            <option value="{{ $value }}" @selected($filters['localidad'] === $value)>{{ $value }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="f-area" class="form-label">Área</label>
        <select id="f-area" name="area" class="form-select mera-input">
            <option value="">Todas</option>
            @foreach ($options['areas'] as $value)
            <option value="{{ $value }}" @selected($filters['area'] === $value)>{{ $value }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="f-centro" class="form-label">Centro de trabajo</label>
        <select id="f-centro" name="centro" class="form-select mera-input">
            <option value="">Todos</option>
            @foreach ($options['centers'] as $value)
            <option value="{{ $value }}" @selected($filters['centro'] === $value)>{{ $value }}</option>
            @endforeach
        </select>
    </div>
    <label class="mera-tag-check mera-track-filter-toggle" title="Por defecto, las comparaciones entre localidades, áreas y puestos usan solo los cursos generales (para todos).">
        <input type="checkbox" name="especificos" value="1" @checked($filters['especificos'])>
        <span>Incluir cursos específicos en las comparaciones</span>
    </label>
    <div class="mera-track-filter-actions">
        <button type="submit" class="btn mera-btn-save btn-sm">Aplicar</button>
        @if (collect($filters)->except('anio')->filter()->isNotEmpty())
        <a href="{{ $action }}?{{ http_build_query(array_filter(['anio' => $filters['anio']] + ($extra ?? []))) }}" class="btn btn-sm mera-btn-ghost">Quitar filtros</a>
        @endif
    </div>
</form>
