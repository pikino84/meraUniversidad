{{-- "Lo que opinan" (INSTRUCTIVO_REPORTE_PERSONAS.md §6.1): una encuesta por curso. Tabla para comparar
     cursos y, con un curso elegido, el detalle de su encuesta. Variables: $report, $surveys,
     $surveyCourses, $filters, $labels, $with --}}
@php
    $current = $filters['curso'] ? $surveys->firstWhere('course_code', $filters['curso']) : null;
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').' %';
@endphp
<section class="card mera-table-card mt-3" aria-labelledby="t-opinan">
    <div class="card-body">
        <div class="mera-section-toolbar">
            <div>
                <h2 id="t-opinan" class="h6 mera-track-title mb-1">Lo que opinan</h2>
                <small class="text-muted">Encuestas de satisfacción por curso. NPS = % que puso 9–10 menos % que puso 0–6.</small>
            </div>
        </div>

        @if ($surveys->isEmpty())
        <p class="text-muted small">Todavía no hay encuestas en este corte. Agrégalas abajo, una por curso.</p>
        @else
        <div class="table-responsive">
            <table class="table mera-table mera-table-stack mb-3">
                <thead>
                    <tr>
                        <th scope="col">Curso</th>
                        <th scope="col">Respuestas</th>
                        <th scope="col">Recomienda</th>
                        <th scope="col">NPS</th>
                        <th scope="col">Problemas técnicos</th>
                        <th scope="col">Satisfacción</th>
                        @unless ($share)<th scope="col" class="text-center mera-col-actions">Acciones</th>@endunless
                    </tr>
                </thead>
                <tbody>
                    @foreach ($surveys as $s)
                    <tr @if ($current && $current->id === $s->id) class="is-current" @endif>
                        <td data-label="Curso">
                            <a href="{{ $with(['curso' => $s->course_code]) }}#t-opinan" class="user-name">{{ $labels[$s->course_code] ?? $s->course_code }}</a>
                            <small class="d-block text-muted">{{ $s->title }}</small>
                            @if ($s->warnings)<span class="mera-missing-badge" title="{{ implode(' ', $s->warnings) }}"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> {{ count($s->warnings) }} aviso(s)</span>@endif
                        </td>
                        <td data-label="Respuestas" class="mera-nowrap">{{ number_format($s->responses) }}<small class="d-block text-muted">{{ number_format($s->active) }} activos</small></td>
                        <td data-label="Recomienda">{{ $pct($s->indicator('recommend')) }}</td>
                        <td data-label="NPS">{{ $s->indicator('nps') === null ? '—' : sprintf('%+d', $s->indicator('nps')) }}</td>
                        <td data-label="Problemas técnicos">{{ $pct($s->indicator('tech_problems')) }}</td>
                        <td data-label="Satisfacción">{{ $pct($s->indicator('satisfaction')) }}@if ($s->indicator('satisfaction') === null)<small class="d-block text-muted">sin configurar</small>@endif</td>
                        @unless ($share)
                        <td data-label="Acciones" class="text-center mera-col-actions">
                            @include('partials.delete-button', [
                                'action' => route('progress.surveys.destroy', [$report, $s]),
                                'label' => 'Eliminar la encuesta de '.($labels[$s->course_code] ?? $s->course_code),
                                'confirm' => '¿Eliminar la encuesta del corte?',
                                'strong' => $labels[$s->course_code] ?? $s->course_code,
                                'detail' => 'Se puede volver a subir cuando quieras.',
                            ])
                        </td>
                        @endunless
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if ($current)
        <h3 class="h6 mt-2">{{ $labels[$current->course_code] ?? $current->course_code }}: respuestas por pregunta</h3>
        @foreach ($current->warnings ?? [] as $warning)
        <p class="small text-muted mb-1"><i class="fas fa-info-circle" aria-hidden="true"></i> {{ $warning }}</p>
        @endforeach
        <div class="mera-track-grid mt-2">
            @foreach ($current->data['closed'] ?? [] as $q)
            @php
                // Compartido: las respuestas con menos de 3 menciones se agrupan (ningún texto único a la vista).
                if ($share) {
                    $rare = array_filter($q['answers'], fn ($n) => $n < 3);
                    $q['answers'] = array_diff_key($q['answers'], $rare) + ($rare ? ['Otras respuestas' => array_sum($rare)] : []);
                }
            @endphp
            <div class="mera-survey-question">
                <p class="small fw-semibold mb-2">{{ $q['question'] }}</p>
                <ol class="mera-track-months list-unstyled mb-0">
                    @foreach ($q['answers'] as $answer => $count)
                    <li>
                        <span class="mera-track-month" title="{{ $answer }}">{{ \Illuminate\Support\Str::limit($answer, 22) }}</span>
                        <span class="mera-track-bar"><i class="is-passed" style="width: {{ $count / max(1, $q['n']) * 100 }}%"></i></span>
                        <span class="mera-track-bar-value"><b>{{ round($count / max(1, $q['n']) * 100) }} %</b> <small>{{ number_format($count) }}</small></span>
                    </li>
                    @endforeach
                </ol>
            </div>
            @endforeach
        </div>
        {{-- Respuestas abiertas: solo en el panel (pueden traer nombres escritos por quien respondió). --}}
        @foreach ($share ? [] : ($current->data['open'] ?? []) as $q)
        <details class="mera-survey-open mt-2">
            <summary><span class="fw-semibold">{{ $q['question'] }}</span> <span class="mera-section-count">{{ number_format(count($q['answers'])) }}</span></summary>
            <ul class="small mb-0 mt-2">
                @foreach ($q['answers'] as $answer)<li>{{ $answer }}</li>@endforeach
            </ul>
        </details>
        @endforeach
        @elseif ($surveys->isNotEmpty())
        <p class="small text-muted mb-0">Elige un curso en el filtro (o en la tabla) para ver las respuestas de su encuesta.</p>
        @endif

        @unless ($share)
        <form method="POST" action="{{ route('progress.surveys.store', $report) }}" enctype="multipart/form-data" class="mera-survey-upload mt-3"
            data-loader-message="Leyendo la encuesta…">
            @csrf
            <div>
                <label for="s-file" class="form-label">Agregar encuesta de un curso</label>
                <input id="s-file" type="file" name="encuesta" required accept=".xlsx" class="form-control">
            </div>
            <div>
                <label for="s-course" class="form-label">Curso</label>
                <select id="s-course" name="course_code" class="form-select mera-input">
                    <option value="">Reconocer por el título de la encuesta</option>
                    @foreach (collect($surveyCourses)->keys()->mapWithKeys(fn ($c) => [$c => $labels[$c] ?? $c])->sort() as $code => $label)
                    <option value="{{ $code }}" @selected($filters['curso'] === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm mera-btn-save">Agregar</button>
        </form>
        <p class="small text-muted mt-1 mb-0">El Excel no se guarda: solo los totales y el texto de las respuestas abiertas (sin nombres). Si el curso ya tenía encuesta, se reemplaza.</p>
        @endunless
    </div>
</section>
