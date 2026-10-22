/**
 * Tablero de un corte de reporte (docs/reportes/INSTRUCTIVO_REPORTES.md §5.4).
 *
 * Es la lógica del último <script> de la plantilla de referencia, con estos cambios:
 *  - Los datos vienen de <script type="application/json" id="report-data"> como
 *    {columns, rows} (no CSV): se adaptan en un solo punto, data().
 *  - d3 7.9.0 desde npm (solo las funciones que se usan) y meses en español.
 *  - El ranking de localidades se puede usar con teclado y los totales de arriba siguen los filtros.
 *  - Umbrales SIN cambios (§6.5), pero desde config('mera.reports.dashboard') (data-thresholds) en
 *    lugar de números sueltos, para que el JS y los textos de la vista no se desalineen.
 *    Reprobar = menos de 80 es fijo: es el corte de las franjas b80/b70/b60/b50 (§7).
 *  - Al cambiar el tamaño solo se vuelve a dibujar si cambió el ANCHO (en celular, el scroll
 *    oculta la barra del navegador y dispara "resize" sin que cambie nada).
 *
 * Es una entrada propia de Vite (sin imports compartidos con app.js) para que "Descargar HTML"
 * pueda incrustar el archivo compilado tal cual.
 */
import {
    axisBottom, axisLeft, formatLocale, line, max, rollups, scaleBand, scaleLinear, scalePoint, select,
    sum as d3sum, timeFormatLocale,
} from 'd3';

const ES = formatLocale({ decimal: '.', thousands: ',', grouping: [3], currency: ['$', ''] });
const MONTHS = timeFormatLocale({
    dateTime: '%x, %X', date: '%d/%m/%Y', time: '%H:%M:%S', periods: ['a. m.', 'p. m.'],
    days: ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
    shortDays: ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'],
    months: ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    shortMonths: ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
});

function init() {
    const root = document.querySelector('[data-course-report]');
    const source = document.getElementById('report-data');
    if (!root || !source) return;

    let raw;
    try {
        raw = JSON.parse(source.textContent);
    } catch {
        return;
    }

    // {columns, rows} → lista de objetos (lo que devolvía d3.csvParse en la plantilla).
    const cache = {};
    const data = (id) => cache[id] || (cache[id] = (raw?.[id]?.rows || []).map((row) => {
        const obj = {};
        raw[id].columns.forEach((c, i) => { obj[c] = row[i]; });
        return obj;
    }));

    // Mismos valores que la plantilla; data-thresholds los trae de config (por si faltara, estos).
    let configured = {};
    try { configured = JSON.parse(root.dataset.thresholds || '{}'); } catch { configured = {}; }
    const T = {
        podium_min_people: 20, ranking_low_from: 13, failing_group_min: 20, position_min_graded: 15,
        ok_below: 0.35, needs_support_from: 0.45, satisfaction_min_answers: 30, ...configured,
    };

    let params = {};
    const $ = (id) => document.getElementById(id);
    const num = (v) => +v || 0;
    const fmt = ES.format(',');
    const pct = (x, d = 0) => (x * 100).toFixed(d) + ' %';
    const sum = (rows, k) => d3sum(rows, (r) => num(r[k]));
    const month = MONTHS.utcFormat('%b %y');
    const monthOf = (d) => month(new Date(d + '-01T00:00:00Z'));
    // Preguntas favorables que trae la encuesta del corte (tabla "preguntas"; etiqueta vacía = sin barra).
    // Los cortes creados antes de 2026-10-21 no la tienen: son de Higiene y usan sus 8 preguntas.
    const QS = raw?.preguntas ? data('preguntas').map((r) => [r.clave, r.etiqueta]) : [['f_utilidad', 'Les sirve para su trabajo'], ['f_riesgos', 'Entienden los riesgos'], ['f_ejemplos', 'Ejemplos claros'], ['f_herramientas', 'Aprendieron herramientas'], ['f_capaz', 'Se sienten capaces de aplicarlo'], ['f_facil', 'Fue fácil de usar'], ['f_recursos', 'Recursos útiles'], ['f_recomienda', null]];
    const Q = QS.filter(([, label]) => label);
    const FK = QS.map(([key]) => key);
    // null = Sí/No escrito a mano sin respuesta clara: esa respuesta queda fuera del % (2026-10-22).
    const clear = (r) => FK.every((k) => r[k] !== null);
    const allOk = (r) => FK.every((k) => num(r[k]) === 1);
    const satShare = (rows) => { const c = rows.filter(clear); return c.length ? c.filter(allOk).length / c.length : null; };
    const STATE = { ok: ['Va bien', '#55B882'], warn: ['Atención', '#E5A823'], bad: ['Necesita apoyo', 'var(--cds-chart-status-critical)'] };
    const state = (bajo) => (bajo >= T.needs_support_from ? 'bad' : (bajo < T.ok_below ? 'ok' : 'warn'));
    const tip = $('lx-tip');
    const showTip = (event, text) => {
        const r = root.getBoundingClientRect();
        tip.textContent = text;
        tip.style.opacity = 1;
        tip.style.left = Math.min(Math.max(event.clientX - r.left + 12, 0), Math.max(r.width - 200, 0)) + 'px';
        tip.style.top = (event.clientY - r.top + 14) + 'px';
    };
    const hideTip = () => { tip.style.opacity = 0; };
    const div = (cls, text) => {
        const d = document.createElement('div');
        if (cls) d.className = cls;
        if (text !== undefined) d.textContent = text;
        return d;
    };
    const axisStyle = (g) => { g.selectAll('text').attr('font-size', null).attr('font-family', null).style('font-size', '11px'); };

    const fl = $('lx-fl');
    const fa = $('lx-fa');
    // Los filtros quedan en la URL (#localidad=…&area=…): al recargar o compartir se conservan.
    const saveHash = () => {
        const h = new URLSearchParams();
        if (params.loc) h.set('localidad', params.loc);
        if (params.area) h.set('area', params.area);
        const hash = h.toString();
        history.replaceState(null, '', hash ? `#${hash}` : location.pathname + location.search);
    };
    const fromHash = new URLSearchParams(location.hash.slice(1));
    params = { loc: fromHash.get('localidad') || null, area: fromHash.get('area') || null };
    const setParams = (p) => { params = { ...params, ...p }; saveHash(); render(); };
    fl.addEventListener('change', () => setParams({ loc: fl.value || null }));
    fa.addEventListener('change', () => setParams({ area: fa.value || null }));
    $('lx-reset').addEventListener('click', () => { params = {}; saveHash(); render(); });

    const pickLocation = (row) => setParams({ loc: params.loc === row.dataset.key ? null : row.dataset.key });
    $('lx-locs').addEventListener('click', (e) => {
        const row = e.target.closest('[data-key]');
        if (row) pickLocation(row);
    });
    $('lx-locs').addEventListener('keydown', (e) => {
        const row = e.target.closest('[data-key]');
        if (row && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            const key = row.dataset.key;
            pickLocation(row);
            // Tras volver a pintar, el foco regresa a la misma localidad.
            root.querySelector(`[data-key="${CSS.escape(key)}"]`)?.focus();
        }
    });

    const barRows = (el, items, total, tone) => {
        el.replaceChildren();
        items.forEach((it) => {
            const row = div('lx-bar' + (it.tone ? ' lx-' + it.tone : (tone ? ' lx-' + tone : '')));
            const t = div('lx-t');
            const s = document.createElement('span');
            s.style.width = Math.max(0, Math.min(100, total ? (it.value / total) * 100 : 0)) + '%';
            t.append(s);
            row.append(div('', it.label), t, div('lx-n', it.text));
            el.append(row);
        });
    };
    const group = (rows, keyFn) => rollups(rows, (g) => ({ asig: sum(g, 'asignados'), comp: sum(g, 'completados'), sin: sum(g, 'sin_iniciar'), n: sum(g, 'calif_n'), ok: sum(g, 'b80'), sc: sum(g, 'calif_suma') }), keyFn)
        .map(([k, v]) => ({ k, ...v, p: v.asig ? v.comp / v.asig : 0, bajo: v.n ? (v.n - v.ok) / v.n : 0, okp: v.n ? v.ok / v.n : 0, prom: v.n ? v.sc / v.n : 0 }));
    const apRows = (el, list) => {
        el.replaceChildren();
        const h = div('lx-ap lx-head');
        ['', 'Sacó 80 o más', 'Promedio', 'Estado'].forEach((t, i) => h.append(div(i === 2 ? 'lx-n' : '', t)));
        el.append(h);
        list.forEach((v) => {
            const row = div('lx-ap');
            const t = div('lx-t');
            const sp = document.createElement('span');
            sp.style.width = (v.okp * 100) + '%';
            t.append(sp);
            const barCell = div('');
            barCell.append(t);
            const st = STATE[state(v.bajo)];
            const chip = div('lx-chip');
            const dot = document.createElement('i');
            dot.style.background = st[1];
            chip.append(dot, document.createTextNode(st[0]));
            row.append(div('', v.k + ' · ' + pct(v.okp)), barCell, div('lx-n', v.n ? v.prom.toFixed(1) : '—'), chip);
            row.addEventListener('mousemove', (e) => showTip(e, v.k + ': ' + fmt(v.ok) + ' de ' + fmt(v.n) + ' evaluadas sacaron 80 o más'));
            row.addEventListener('mouseleave', hideTip);
            el.append(row);
        });
        if (!list.length) el.append(div('lx-sub', 'Sin datos con estos filtros.'));
    };

    function render() {
        const cubo = data('cubo');
        const meses = data('inicio_mes');
        const enc = data('encuesta');
        const cat = data('catalogo');
        const loc = params.loc || '';
        const area = params.area || '';

        const locs = [...new Set(cubo.map((r) => r.localidad))].sort((a, b) => a.localeCompare(b, 'es'));
        const areas = [...new Set(cubo.map((r) => r.area))].sort((a, b) => a.localeCompare(b, 'es'));
        if (fl.options.length !== locs.length + 1) {
            fl.replaceChildren(new Option('Todas las localidades', ''), ...locs.map((x) => new Option(x, x)));
            fa.replaceChildren(new Option('Todas las áreas', ''), ...areas.map((x) => new Option(x, x)));
        }
        // Un filtro del hash que no existe en estos datos se ignora.
        if (loc && !locs.includes(loc)) { params.loc = null; return render(); }
        if (area && !areas.includes(area)) { params.area = null; return render(); }
        fl.value = loc;
        fa.value = area;

        const byArea = cubo.filter((r) => !area || r.area === area);
        const byLoc = cubo.filter((r) => !loc || r.localidad === loc);
        const sel = byArea.filter((r) => !loc || r.localidad === loc);
        const encF = enc.filter((r) => (!loc || r.localidad === loc) && (!area || r.area === area));
        const nE = encF.length;

        // Totales de arriba (siguen los filtros)
        const asig = sum(sel, 'asignados');
        const comp = sum(sel, 'completados');
        const sin = sum(sel, 'sin_iniciar');
        const calN = sum(sel, 'calif_n');
        const ok80 = sum(sel, 'b80');
        const rec = nE && FK.length ? satShare(encF) : null;
        $('k-asig').textContent = fmt(asig);
        $('k-comp').textContent = asig ? pct(comp / asig) : '—';
        $('k-comp-s').textContent = fmt(comp) + ' de ' + fmt(asig) + ' personas';
        $('k-meter').style.width = asig ? (comp / asig * 100) + '%' : '0';
        $('k-sin').textContent = fmt(sin);
        $('k-sin-s').textContent = asig ? pct(sin / asig) + ' de las asignadas' : '';
        $('k-cal').textContent = calN ? (sum(sel, 'calif_suma') / calN).toFixed(1) : '—';
        $('k-cal-s').textContent = calN ? pct(ok80 / calN) + ' sacó 80 o más' : '';
        $('k-rec').textContent = rec === null ? '—' : pct(rec);
        $('k-rec-s').textContent = !nE ? 'Sin respuestas con estos filtros' : !FK.length ? 'La encuesta no trae preguntas para calcularla' : (FK.length === 1 ? 'Recomiendan el curso' : 'Favorable en las ' + FK.length + ' preguntas') + ' (' + fmt(nE) + ' respuestas)';

        // Texto principal
        const aAll = group(byLoc, (r) => r.area).filter((v) => v.n > 0).sort((a, b) => b.okp - a.okp);
        const quien = (loc || 'todas las localidades') + (area ? ', área ' + area : '');
        const peor = aAll.length ? aAll[aAll.length - 1] : null;
        $('lx-take').textContent = asig
            ? pct(comp / asig) + ' de las personas ya completó el curso en ' + quien + (!area && peor ? '. El área que más necesita apoyo en la evaluación es ' + peor.k + ': ' + pct(peor.bajo) + ' sacó menos de 80.' : '.')
            : 'No hay personas asignadas con estos filtros.';

        // Ranking de localidades
        const LL = group(byArea, (r) => r.localidad).filter((v) => v.asig > 0).sort((a, b) => b.p - a.p);
        const L = LL.filter((v) => v.asig >= T.podium_min_people).concat(LL.filter((v) => v.asig < T.podium_min_people));
        let rank = 0;
        const items = L.map((v) => {
            const big = v.asig >= T.podium_min_people;
            if (big) rank += 1;
            return { v, big, r: rank, pod: big && rank <= 3 };
        });
        const firstSmall = items.findIndex((x) => !x.big);
        const half = Math.ceil(items.length / 2);
        const fill = (el, list, startIdx) => {
            el.replaceChildren();
            const lh = div('lx-rk lx-head');
            ['#', 'Localidad', '', 'Personas', 'Hecho', 'Sin iniciar'].forEach((t, i) => lh.append(div(i > 2 ? 'lx-n' : '', t)));
            el.append(lh);
            list.forEach((it, j) => {
                const v = it.v;
                if (firstSmall >= 0 && startIdx + j === firstSmall) el.append(div('lx-sep', `Localidades con menos de ${T.podium_min_people} personas`));
                const low = !it.big || it.r >= T.ranking_low_from;
                const row = div('lx-rk' + (loc === v.k ? ' lx-sel' : '') + (it.pod ? ' lx-pod' : '') + (low ? ' lx-low' : ''));
                row.dataset.key = v.k;
                row.tabIndex = 0;
                row.setAttribute('role', 'button');
                row.setAttribute('aria-pressed', loc === v.k ? 'true' : 'false');
                row.setAttribute('aria-label', `${v.k}: ${pct(v.p)} completó, ${fmt(v.asig)} personas. ${loc === v.k ? 'Quitar filtro' : 'Ver solo esta localidad'}`);
                const st = div('lx-stack');
                [[v.comp, low ? '#E5A823' : '#55B882'], [v.sin, 'var(--cds-chart-muted)']].forEach(([x, c]) => {
                    const sp = document.createElement('span');
                    sp.style.width = (v.asig ? x / v.asig * 100 : 0) + '%';
                    sp.style.background = c;
                    st.append(sp);
                });
                st.addEventListener('mousemove', (e) => showTip(e, v.k + ': ' + fmt(v.comp) + ' completaron y ' + fmt(v.sin) + ' sin iniciar, de ' + fmt(v.asig)));
                st.addEventListener('mouseleave', hideTip);
                const rk = div('');
                if (it.pod) rk.append(div('lx-medal lx-m' + it.r, String(it.r)));
                else rk.textContent = it.big ? String(it.r) : '–';
                row.append(rk, div('', v.k), st, div('lx-n', fmt(v.asig)), div('lx-n', pct(v.p)), div('lx-n', fmt(v.sin)));
                el.append(row);
            });
        };
        fill($('lx-la'), items.slice(0, half), 0);
        fill($('lx-lb'), items.slice(half), half);

        // Quién va bien (por evaluación)
        const PZ = group(sel, (r) => r.puesto).filter((v) => v.n >= T.position_min_graded);
        apRows($('lx-pz-bad'), PZ.filter((v) => state(v.bajo) !== 'ok').sort((a, b) => a.okp - b.okp).slice(0, 6));
        apRows($('lx-pz-ok'), PZ.filter((v) => state(v.bajo) === 'ok').sort((a, b) => b.okp - a.okp).slice(0, 6));

        // Meses
        const mf = rollups(meses.filter((r) => (!loc || r.localidad === loc) && (!area || r.area === area)), (g) => sum(g, 'n'), (r) => r.mes).sort((a, b) => a[0].localeCompare(b[0]));
        const mEl = $('lx-months');
        mEl.replaceChildren();
        if (mf.length) {
            const W = Math.max(mEl.clientWidth, 260); const H = 190; const m = { t: 8, r: 8, b: 26, l: 44 };
            const svg = select(mEl).append('svg').attr('width', '100%').attr('viewBox', `0 0 ${W} ${H}`).attr('role', 'img')
                .attr('aria-label', 'Completaron por mes de inicio: ' + mf.map((d) => monthOf(d[0]) + ' ' + fmt(d[1])).join(', '));
            const x = scaleBand(mf.map((d) => d[0]), [m.l, W - m.r]).padding(0.25);
            const y = scaleLinear([0, max(mf, (d) => d[1])], [H - m.b, m.t]).nice();
            svg.append('g').attr('transform', `translate(${m.l},0)`).call(axisLeft(y).ticks(4).tickFormat(fmt).tickSize(-(W - m.l - m.r)))
                .call((g) => { g.select('.domain').remove(); g.selectAll('line').attr('stroke', 'var(--cds-chart-grid)'); axisStyle(g); });
            const step = Math.ceil(mf.length / Math.max(1, Math.floor((W - m.l - m.r) / 54)));
            svg.append('g').attr('transform', `translate(0,${H - m.b})`)
                .call(axisBottom(x).tickValues(mf.map((d) => d[0]).filter((_, i) => i % step === 0)).tickFormat(monthOf).tickSize(0))
                .call((g) => { g.select('.domain').attr('stroke', 'var(--cds-chart-axis)'); axisStyle(g); });
            svg.append('g').selectAll('rect').data(mf).join('rect')
                .attr('x', (d) => x(d[0])).attr('width', x.bandwidth()).attr('y', (d) => y(d[1])).attr('height', (d) => y(0) - y(d[1])).attr('rx', 3).attr('fill', '#55B882')
                .on('mousemove', (e, d) => showTip(e, monthOf(d[0]) + ': ' + fmt(d[1]) + ' personas'))
                .on('mouseleave', hideTip);
        }

        // Acumulado
        const cEl = $('lx-cum');
        cEl.replaceChildren();
        if (mf.length && asig) {
            let run = 0;
            const cum = mf.map(([mes, n]) => { run += n; return [mes, run]; });
            const W = Math.max(cEl.clientWidth, 260); const H = 190; const m = { t: 20, r: 14, b: 26, l: 48 }; // t: 20 deja ver la etiqueta "Asignadas" arriba
            const svg = select(cEl).append('svg').attr('width', '100%').attr('viewBox', `0 0 ${W} ${H}`).attr('role', 'img')
                .attr('aria-label', `Acumulado: ${fmt(run)} de ${fmt(asig)} asignadas han completado`);
            const x = scalePoint(cum.map((d) => d[0]), [m.l + 8, W - m.r - 8]).padding(0);
            const y = scaleLinear([0, Math.max(asig, run)], [H - m.b, m.t]).nice();
            svg.append('g').attr('transform', `translate(${m.l},0)`).call(axisLeft(y).ticks(4).tickFormat(fmt).tickSize(-(W - m.l - m.r)))
                .call((g) => { g.select('.domain').remove(); g.selectAll('line').attr('stroke', 'var(--cds-chart-grid)'); axisStyle(g); });
            const stepC = Math.ceil(cum.length / Math.max(1, Math.floor((W - m.l - m.r) / 54)));
            svg.append('g').attr('transform', `translate(0,${H - m.b})`)
                .call(axisBottom(x).tickValues(cum.map((d) => d[0]).filter((_, i) => i % stepC === 0)).tickFormat(monthOf).tickSize(0))
                .call((g) => { g.select('.domain').attr('stroke', 'var(--cds-chart-axis)'); axisStyle(g); });
            svg.append('line').attr('x1', m.l).attr('x2', W - m.r).attr('y1', y(asig)).attr('y2', y(asig)).attr('stroke', 'var(--cds-chart-reference)').attr('stroke-dasharray', '4 4');
            svg.append('text').attr('x', W - m.r).attr('y', y(asig) - 4).attr('text-anchor', 'end').style('font-size', '11px').text('Asignadas: ' + fmt(asig));
            const ln = line().x((d) => x(d[0])).y((d) => y(d[1]));
            svg.append('path').datum(cum).attr('d', ln).attr('fill', 'none').attr('stroke', '#55B882').attr('stroke-width', 2.5).attr('stroke-linecap', 'round').attr('stroke-linejoin', 'round');
            svg.append('g').selectAll('circle').data(cum).join('circle').attr('cx', (d) => x(d[0])).attr('cy', (d) => y(d[1])).attr('r', 4).attr('fill', '#55B882')
                .on('mousemove', (e, d) => showTip(e, monthOf(d[0]) + ': ' + fmt(d[1]) + ' han completado (' + pct(d[1] / asig) + ' de las asignadas)'))
                .on('mouseleave', hideTip);
            const last = cum[cum.length - 1];
            svg.append('text').attr('x', x(last[0])).attr('y', y(last[1]) + 18).attr('text-anchor', 'end').style('font-size', '11px').text(fmt(last[1]));
        }

        // Avance por área y por puesto
        const AR = group(byLoc, (r) => r.area).filter((v) => v.asig > 0).sort((a, b) => b.p - a.p);
        barRows($('lx-area-av'), AR.map((v) => ({ label: v.k + ' (' + fmt(v.asig) + ')', value: v.p, text: pct(v.p) })), 1, '');
        const PV = group(sel, (r) => r.puesto).filter((v) => v.asig > 0).sort((a, b) => b.asig - a.asig).slice(0, 8).sort((a, b) => b.p - a.p);
        barRows($('lx-pz-av'), PV.map((v) => ({ label: v.k + ' (' + fmt(v.asig) + ')', value: v.p, text: pct(v.p) })), 1, '');
        if (!PV.length) $('lx-pz-av').append(div('lx-sub', 'Sin datos con estos filtros.'));

        // Calificaciones
        const gb = [['80 o más', sum(sel, 'b80'), ''], ['70 a 79', sum(sel, 'b70'), 'warn'], ['60 a 69', sum(sel, 'b60'), 'warn'], ['Menos de 60', sum(sel, 'b50'), 'crit']];
        barRows($('lx-grades'), gb.map(([l, v, t]) => ({ label: l, value: v, text: calN ? pct(v / calN) : '—', tone: t })), calN, '');
        $('lx-grades').append(div('lx-sub', calN ? 'Sobre ' + fmt(calN) + ' personas. ' + pct((calN - ok80) / calN) + ' sacó menos de 80.' : 'Sin datos con estos filtros.'));

        // Reprobación
        const rep = (rows, keyFn, el) => {
            const g = group(rows, keyFn).filter((v) => v.n >= T.failing_group_min).sort((a, b) => b.bajo - a.bajo).slice(0, 5);
            barRows(el, g.map((v) => ({ label: v.k, value: v.bajo, text: pct(v.bajo), tone: v.bajo >= T.needs_support_from ? 'crit' : 'warn' })), 1, '');
            if (!g.length) el.append(div('lx-sub', `Sin grupos con ${T.failing_group_min} personas o más.`));
        };
        rep(sel, (r) => r.area, $('lx-rep-a'));
        rep(byArea, (r) => r.localidad, $('lx-rep-l'));

        // Satisfacción
        $('lx-sat-note').textContent = nE ? 'Porcentaje de respuestas favorables. ' + fmt(nE) + ' respuestas.' : 'No hay respuestas de encuesta con estos filtros.';
        barRows($('lx-sat'), Q.map(([k, l]) => {
            const answered = encF.filter((r) => r[k] !== null);
            const share = answered.length ? d3sum(answered, (r) => num(r[k])) / answered.length : 0;
            return { label: l, value: share, text: answered.length ? pct(share) : '—' };
        }).sort((a, b) => b.value - a.value), 1, '');

        // Satisfacción por localidad
        const encA = enc.filter((r) => (!area || r.area === area) && r.localidad !== 'Ya no activo');
        const SL = !FK.length ? [] : rollups(encA.filter(clear), (g) => ({ n: g.length, a: g.filter(allOk).length }), (r) => r.localidad)
            .map(([k, v]) => ({ k, ...v, p: v.a / v.n })).filter((v) => v.n >= T.satisfaction_min_answers).sort((a, b) => b.p - a.p);
        barRows($('lx-sat-loc'), SL.map((v) => ({ label: v.k + ' (' + fmt(v.n) + ')', value: v.p, text: pct(v.p) })), 1, '');
        if (!SL.length) $('lx-sat-loc').append(div('lx-sub', `Sin localidades con ${T.satisfaction_min_answers} respuestas o más.`));

        // Lo que valoran y lo que piden mejorar
        const nV = d3sum(encF, (r) => num(r.v_respondio));
        const todo = d3sum(encF, (r) => num(r.v_todo));
        $('lx-val-note').textContent = nV ? 'Lo que dijeron que les gustó. A ' + pct(todo / nV) + ' le gustó todo el curso; esto es lo que mencionan en concreto.' : 'Sin comentarios con estos filtros.';
        const vs = cat.filter((c) => c.tipo === 'valora' && c.codigo !== 'v_todo').map((c) => ({ label: c.etiqueta, value: d3sum(encF, (r) => num(r[c.codigo])) }))
            .filter((c) => c.value > 0).sort((a, b) => b.value - a.value).slice(0, 6);
        barRows($('lx-val'), vs.map((c) => ({ ...c, text: nV ? pct(c.value / nV) : '—' })), vs.length ? vs[0].value : 0, '');
        const ms = cat.filter((c) => c.tipo === 'mejora').map((c) => ({ label: c.etiqueta, value: d3sum(encF, (r) => num(r[c.codigo])) }))
            .filter((c) => c.value > 0).sort((a, b) => b.value - a.value);
        $('lx-mej-note').textContent = ms.length ? 'Solo temas del curso. Se cuenta cuántas personas lo pidieron con sus propias palabras.' : 'Sin comentarios de contenido con estos filtros.';
        barRows($('lx-mej'), ms.map((c) => ({ ...c, text: fmt(c.value) })), ms.length ? ms[0].value : 0, 'warn');
    }

    root.querySelector('[data-report-filters]').hidden = false;
    root.querySelector('[data-report-charts]').hidden = false;
    render();

    let timer;
    let width = root.clientWidth;
    window.addEventListener('resize', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (root.clientWidth === width) return;
            width = root.clientWidth;
            render();
        }, 150);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
