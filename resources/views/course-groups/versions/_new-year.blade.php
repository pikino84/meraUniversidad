{{-- "+ Año" (docs/carpetas/INSTRUCTIVO_VERSIONES.md §4 y §9.5): crea un año copiando SOLO la
     estructura de otro (versiones, título, descripción y links, sin módulos y apagadas) o con las
     versiones que se elijan. Variables: $group, $years, $markets, $nextYear, $open, $popover --}}
<details class="mera-new-year {{ ($popover ?? false) ? 'is-popover' : '' }}" @if ($open) open @endif>
    <summary class="btn btn-sm mera-btn-ghost"><i class="fas fa-calendar-plus" aria-hidden="true"></i> Nuevo año</summary>
    <form method="POST" action="{{ route('course-groups.versions.store', $group) }}" class="mera-new-year-form text-start">
        @csrf
        <div class="row g-3 align-items-end">
            <div class="col-sm-4">
                <label for="new-year" class="form-label">Año</label>
                <input id="new-year" type="number" name="year" min="2020" max="2100" required
                    class="form-control mera-input" value="{{ old('year', $nextYear) }}">
            </div>
            @if ($years->isNotEmpty())
            <div class="col-sm-8">
                <label for="copy-from" class="form-label">Copiar la estructura de</label>
                <select id="copy-from" name="copy_from" class="form-select mera-input" data-copy-from>
                    <option value="">No copiar: elegir las versiones</option>
                    @foreach ($years->keys() as $year)
                    <option value="{{ $year }}" @selected((string) old('copy_from', $years->keys()->first()) === (string) $year)>{{ $year }} ({{ $years[$year]->map(fn ($v) => $v->market->name)->join(', ') }})</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <fieldset class="mt-3" data-market-choices>
            <legend class="form-label fs-6">Versiones</legend>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($markets as $market)
                <label class="mera-tag-check">
                    <input type="checkbox" name="market_ids[]" value="{{ $market->id }}"
                        @checked(in_array($market->id, (array) old('market_ids', [$markets->first()?->id]), false))>
                    <span>{{ $market->label() }}</span>
                </label>
                @endforeach
            </div>
        </fieldset>

        <p class="small text-muted mt-2 mb-3">
            Se copian las versiones con su título, descripción y links de evaluación y encuesta.
            Los módulos <strong>no</strong> se copian, y las páginas nacen apagadas.
        </p>
        <button type="submit" class="btn mera-btn-save"><i class="fas fa-plus" aria-hidden="true"></i> Crear año</button>
    </form>
</details>
