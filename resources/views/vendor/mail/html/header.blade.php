@props(['url'])
{{-- Encabezado MERA: logo a color en PNG (los clientes de correo no muestran SVG) con URL
     absoluta desde APP_URL: un correo no puede usar rutas relativas. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/images/logo-mera-university-email.png" class="logo" width="180" height="66" alt="MERA University">
</a>
</td>
</tr>
