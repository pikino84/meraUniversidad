{{--
    Mensajes flash para SweetAlert. Se imprimen como JSON (no dentro de un string de JS),
    así comillas, saltos de línea o HTML en el mensaje no rompen la página ni permiten XSS.
    Lo consume resources/js/modules/flash.js.
--}}
@if (session()->has('success') || session()->has('error'))
<script type="application/json" id="flash-messages">
    @json(['success' => session('success'), 'error' => session('error')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
</script>
@endif
