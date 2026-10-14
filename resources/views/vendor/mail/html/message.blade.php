<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer (línea de marca MERA) --}}
<x-slot:footer>
<x-mail::footer>
<span class="mera-tagline">Vision · Values · Results</span>

Mensaje automático del panel de Mera Universidad. No respondas a este correo.<br>
© {{ date('Y') }} MERA Corporation
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
