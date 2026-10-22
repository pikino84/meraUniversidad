{{-- NPS de la encuesta; sin escala 0–10 de recomendación, la calificación general del curso (no es NPS). --}}
@if ($s->indicator('nps') !== null)
{{ sprintf('%+d', $s->indicator('nps')) }}
@elseif ($s->indicator('rating_avg') !== null)
{{ number_format($s->indicator('rating_avg'), 1) }} / 10<small class="d-block text-muted">calificación del curso (sin NPS)</small>
@else
—
@endif
