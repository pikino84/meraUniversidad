{{-- Tablero del corte (estructura de la plantilla de referencia, INSTRUCTIVO_REPORTES.md §5.4).
     Lo llena resources/js/report-dashboard.js con los datos de #report-data; los totales vienen ya
     calculados del servidor para que se vean aunque el JS no cargue. Variables: $report --}}
@php
    $s = $report->summary ?? [];
    $pct = fn ($v) => $v === null ? '—' : number_format(round($v)).' %';
    $t = config('mera.reports.dashboard');
@endphp
<div class="mera-report" data-course-report data-thresholds="{{ json_encode($t) }}">
    <p class="lx-take" id="lx-take">
        {{ isset($s['pct_completado']) ? $pct($s['pct_completado']).' de las personas ya completó el curso en todas las localidades.' : '' }}
    </p>

    <div class="lx-filters" role="group" aria-label="Filtros del reporte" data-report-filters hidden>
        <label class="visually-hidden" for="lx-fl">Localidad</label>
        <select id="lx-fl"></select>
        <label class="visually-hidden" for="lx-fa">Área</label>
        <select id="lx-fa"></select>
        <button class="lx-btn" id="lx-reset" type="button">Quitar filtros</button>
    </div>

    <div class="mera-report-kpis" aria-live="polite">
        <div class="mera-report-kpi">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span class="mera-report-kpi-label">Asignados</span>
            <strong id="k-asig">{{ number_format($s['asignados'] ?? 0) }}</strong>
        </div>
        <div class="mera-report-kpi">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <span class="mera-report-kpi-label">Completaron</span>
            <strong id="k-comp">{{ $pct($s['pct_completado'] ?? null) }}</strong>
            <small id="k-comp-s">{{ number_format($s['completados'] ?? 0) }} de {{ number_format($s['asignados'] ?? 0) }} personas</small>
            <div class="lx-meter" aria-hidden="true"><span id="k-meter" style="width: {{ (float) ($s['pct_completado'] ?? 0) }}%"></span></div>
        </div>
        <div class="mera-report-kpi">
            <i class="fas fa-hourglass-start" aria-hidden="true"></i>
            <span class="mera-report-kpi-label">Todavía no empiezan</span>
            <strong id="k-sin">{{ number_format($s['sin_iniciar'] ?? 0) }}</strong>
            <small id="k-sin-s"></small>
        </div>
        <div class="mera-report-kpi">
            <i class="fas fa-star" aria-hidden="true"></i>
            <span class="mera-report-kpi-label">Calificación promedio</span>
            <strong id="k-cal">{{ isset($s['calif_promedio']) ? number_format($s['calif_promedio'], 1) : '—' }}</strong>
            <small id="k-cal-s">{{ isset($s['pct_80']) ? $pct($s['pct_80']).' sacó 80 o más' : '' }}</small>
        </div>
        <div class="mera-report-kpi">
            <i class="fas fa-smile" aria-hidden="true"></i>
            <span class="mera-report-kpi-label">Satisfacción</span>
            <strong id="k-rec">{{ $pct($s['satisfaccion'] ?? null) }}</strong>
            <small id="k-rec-s">{{ number_format($s['respuestas'] ?? 0) }} respuestas</small>
        </div>
    </div>

    {{-- Pestañas (pedido del usuario 2026-10-23: "demasiada información en una sola vista"). Los filtros
         y los indicadores de arriba se conservan al cambiar; la pestaña queda en la URL (#vista=…). --}}
    <div class="lx-tabs" role="tablist" aria-label="Secciones del reporte" data-report-tabs hidden>
        <button type="button" role="tab" id="tab-resumen" aria-controls="panel-resumen" data-tab="resumen"><i class="fas fa-trophy" aria-hidden="true"></i> Resumen</button>
        <button type="button" role="tab" id="tab-avance" aria-controls="panel-avance" data-tab="avance"><i class="fas fa-chart-line" aria-hidden="true"></i> Avance</button>
        <button type="button" role="tab" id="tab-evaluacion" aria-controls="panel-evaluacion" data-tab="evaluacion"><i class="fas fa-graduation-cap" aria-hidden="true"></i> Evaluación</button>
        <button type="button" role="tab" id="tab-opiniones" aria-controls="panel-opiniones" data-tab="opiniones"><i class="fas fa-comments" aria-hidden="true"></i> Opiniones</button>
    </div>

    <div data-report-charts hidden>
        <div role="tabpanel" id="panel-resumen" aria-labelledby="tab-resumen" data-panel="resumen">
        <div class="lx-one">
            <section class="lx-card" aria-labelledby="t-ranking">
                <h3 id="t-ranking">Ranking de localidades</h3>
                <p class="lx-note">Ordenadas de mayor a menor porcentaje que ya completó el curso. El podio es para localidades con al menos {{ $t['podium_min_people'] }} personas. Haz clic en una (o selecciónala con Enter) para ver solo sus datos.</p>
                <div class="lx-legend"><span><i style="background:#55B882"></i>Completaron</span><span><i style="background:var(--cds-chart-muted)"></i>Sin iniciar</span><span><i style="background:#E5A823"></i>Del lugar {{ $t['ranking_low_from'] }} en adelante: aún sin cumplimiento</span></div>
                <div class="lx-split" id="lx-locs"><div id="lx-la"></div><div id="lx-lb"></div></div>
            </section>
        </div>
        </div>

        <div role="tabpanel" id="panel-avance" aria-labelledby="tab-avance" data-panel="avance">
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-meses">
                <h3 id="t-meses">Avance por mes</h3>
                <p class="lx-note">Personas que ya completaron el curso, según el mes en que lo empezaron.</p>
                <div class="lx-chart" id="lx-months"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-acumulado">
                <h3 id="t-acumulado">Personas que han completado, mes a mes</h3>
                <p class="lx-note">Suma acumulada según el mes en que empezaron el curso. La línea punteada es el total de personas asignadas.</p>
                <div class="lx-chart" id="lx-cum"></div>
            </section>
        </div>
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-area">
                <h3 id="t-area">Avance por área</h3>
                <p class="lx-note">Porcentaje de cada área que ya completó el curso, con el número de personas entre paréntesis.</p>
                <div id="lx-area-av"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-puesto">
                <h3 id="t-puesto">Avance en los puestos más grandes</h3>
                <p class="lx-note">Los 8 puestos con más personas asignadas, y el porcentaje de cada uno que ya completó.</p>
                <div id="lx-pz-av"></div>
            </section>
        </div>
        </div>

        <div role="tabpanel" id="panel-evaluacion" aria-labelledby="tab-evaluacion" data-panel="evaluacion">
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-calif">
                <h3 id="t-calif">Calificaciones</h3>
                <p class="lx-note">Entre quienes completaron el curso.</p>
                <div id="lx-grades"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-reprueban">
                <h3 id="t-reprueban">Dónde reprueban más</h3>
                <p class="lx-note">Reprobar aquí es sacar menos de 80. Se muestran los grupos con al menos {{ $t['failing_group_min'] }} personas que completaron.</p>
                <h4>Por área</h4>
                <div id="lx-rep-a"></div>
                <h4>Por localidad</h4>
                <div id="lx-rep-l"></div>
            </section>
        </div>
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-bien">
                <h3 id="t-bien">Puestos que van bien</h3>
                <p class="lx-note">Puestos con al menos {{ $t['position_min_graded'] }} personas evaluadas. Va bien: menos de {{ round($t['ok_below'] * 100) }} % sacó menos de 80.</p>
                <div id="lx-pz-ok"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-apoyo">
                <h3 id="t-apoyo">Puestos que necesitan más apoyo</h3>
                <p class="lx-note">Puestos con al menos {{ $t['position_min_graded'] }} personas evaluadas. Necesita apoyo: {{ round($t['needs_support_from'] * 100) }} % o más sacó menos de 80.</p>
                <div id="lx-pz-bad"></div>
            </section>
        </div>
        </div>

        <div role="tabpanel" id="panel-opiniones" aria-labelledby="tab-opiniones" data-panel="opiniones">
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-sat">
                <h3 id="t-sat">Qué tan satisfechas están las personas</h3>
                <p class="lx-note" id="lx-sat-note">—</p>
                <div id="lx-sat"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-sat-loc">
                <h3 id="t-sat-loc">Satisfacción por localidad</h3>
                <p class="lx-note">Porcentaje que respondió favorable en todas las preguntas de la encuesta. Solo localidades con al menos {{ $t['satisfaction_min_answers'] }} respuestas.</p>
                <div id="lx-sat-loc"></div>
            </section>
        </div>
        <div class="lx-grid">
            <section class="lx-card" aria-labelledby="t-valoran">
                <h3 id="t-valoran">Lo que más valoran</h3>
                <p class="lx-note" id="lx-val-note">—</p>
                <div id="lx-val"></div>
            </section>
            <section class="lx-card" aria-labelledby="t-mejorar">
                <h3 id="t-mejorar">Qué piden mejorar del contenido</h3>
                <p class="lx-note" id="lx-mej-note">—</p>
                <div id="lx-mej"></div>
            </section>
        </div>
        </div>
    </div>

    <noscript><p class="lx-noscript">Las gráficas y los filtros necesitan JavaScript. Los totales de arriba son del corte completo.</p></noscript>
    <div class="lx-tip" id="lx-tip" aria-hidden="true"></div>
</div>

{{-- Datos del tablero. Nunca {{ }} dentro de <script>: JSON con escapes (CLAUDE.md §3). --}}
<script type="application/json" id="report-data">@json($report->data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)</script>
