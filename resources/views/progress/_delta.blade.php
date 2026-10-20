{{-- Cambio en puntos contra el corte comparado (§7.2.2). Variables: $value (?float), $inverse (bajar es bueno),
     $unit (' pts' por defecto) --}}
@if ($value !== null)
@php
    $good = ($inverse ?? false) ? $value < 0 : $value > 0;
    $class = $value == 0 ? 'is-same' : ($good ? 'is-up' : 'is-down');
@endphp
<span class="mera-delta {{ $class }}" title="Cambio contra el corte comparado">
    <span aria-hidden="true">{{ $value > 0 ? '▲' : ($value < 0 ? '▼' : '=') }}</span>
    {{ $value == 0 ? 'igual' : rtrim(rtrim(number_format(abs($value), 1), '0'), '.').($unit ?? ' pts') }}
</span>
@endif
