{{-- "Dónde reprueban más": localidades con más % de reprobados; cada una lleva a la lista de reprobados. --}}
@if ($failing->isEmpty())
<p class="text-muted small mb-0">Sin datos con estos filtros.</p>
@else
<ol class="mera-track-months list-unstyled mb-0">
    @foreach ($failing->take(10) as $r)
    <li>
        <span class="mera-track-month">@if ($share){{ $r['label'] }}@else<a href="{{ route('progress.people', ['report' => $report] + $base + ['estado' => 'reprobados', 'localidad' => $r['label']]) }}">{{ $r['label'] }}</a>@endif</span>
        <span class="mera-track-bar"><i class="is-failed" style="width: {{ $r['pct_failed'] }}%"></i></span>
        <span class="mera-track-bar-value"><b>{{ round($r['pct_failed']) }} %</b> <small>{{ number_format($r['failed']) }} de {{ number_format($r['done']) }}</small></span>
    </li>
    @endforeach
</ol>
@endif
