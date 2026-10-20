{{-- Barras apiladas por grupo: aprobado · reprobado · en curso · sin iniciar (sobre lo asignado).
     Variables: $rows (label + cifras de ProgressDashboard::normalize), $link (fn($label) => url|null),
     $numbered (bool, ranking) --}}
@if ($rows->isEmpty())
<p class="text-muted small mb-0">Sin datos con estos filtros.</p>
@else
<ol class="mera-track-bars list-unstyled mb-0">
    @foreach ($rows as $r)
    @php
        $a = max(1, $r['assigned']);
        $url = isset($link) ? $link($r['k'] ?? $r['label']) : null;
    @endphp
    <li class="mera-track-bar-row">
        <span class="mera-track-bar-label">
            @if (! empty($numbered))<b class="mera-track-rank">{{ $loop->iteration }}</b>@endif
            @if ($url)<a href="{{ $url }}">{{ $r['label'] }}</a>@else{{ $r['label'] }}@endif
            @if (! empty($r['specific']))<span class="mera-scope-badge is-specific">Específico</span>@endif
            @if (($r['modality'] ?? null) === 'BL')<span class="mera-scope-badge is-bl">BL</span>@endif
            <small>{{ number_format($r['people']) }} {{ $r['people'] === 1 ? 'persona' : 'personas' }}</small>
        </span>
        <span class="mera-track-bar" role="img"
            aria-label="{{ $r['pct_done'] }} % realizado: {{ $r['pct_passed'] }} % aprobado, {{ round($r['failed'] / $a * 100, 1) }} % reprobado; {{ round($r['in_progress'] / $a * 100, 1) }} % en curso; {{ $r['pct_not_started'] }} % sin iniciar">
            <i class="is-passed" style="width: {{ $r['passed'] / $a * 100 }}%"></i>
            <i class="is-failed" style="width: {{ $r['failed'] / $a * 100 }}%"></i>
            <i class="is-progress" style="width: {{ $r['in_progress'] / $a * 100 }}%"></i>
        </span>
        <span class="mera-track-bar-value"><b>{{ round($r['pct_done']) }} %</b> <small>{{ round($r['pct_passed']) }} % aprob.</small></span>
    </li>
    @endforeach
</ol>
@endif
