{{--
    Loader de página (2026-10-14). Cubre la página mientras llegan la tipografía Montserrat y los
    estilos, para que no se vea el "parpadeo" (texto en Arial, íconos como cuadros).

    - Va como PRIMER hijo de <body>. Estilos y script en línea: tienen que funcionar antes de
      que cargue cualquier CSS externo.
    - Se quita cuando el DOM y las fuentes están listos, con un tope de 2.5 s.
    - Respaldo sin JS: una animación CSS lo oculta a los 4 s y <noscript> no lo muestra.
    - No es el preloader de la plantilla (P12): con las fuentes en caché desaparece casi al instante.
    - Al ENVIAR un formulario, submit-guard.js agrega html.mera-busy y el loader reaparece con "Procesando…".
--}}
<style>
    .mera-loader {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #F3F8F4;
        transition: opacity .2s ease, visibility .2s ease;
        animation: mera-loader-failsafe 0s linear 4s forwards;
    }

    .mera-loader__box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 18px;
    }

    .mera-loader__logo {
        width: 132px;
        height: auto;
    }

    .mera-loader__spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #E3F0D0;
        border-top-color: #024D25;
        border-radius: 50%;
        animation: mera-loader-spin .8s linear infinite;
    }

    /* Solo para lectores de pantalla (el login y los programas no cargan Bootstrap) */
    .mera-loader__sr {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .mera-loader__msg {
        display: none;
        color: #024D25;
        font-weight: 600;
        font-size: 15px;
    }

    /* Enviando un formulario (submit-guard.js): se vuelve a mostrar, con mensaje y fondo translúcido.
       animation: none anula el respaldo de 4 s, que deja el loader oculto para siempre. */
    /* Más específico que html.mera-loaded .mera-loader (la página ya cargó cuando se envía). */
    html.mera-busy.mera-busy .mera-loader {
        animation: none;
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        background: rgba(243, 248, 244, .88);
    }

    html.mera-busy.mera-busy .mera-loader__msg {
        display: block;
    }

    html.mera-loaded .mera-loader {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    @keyframes mera-loader-spin {
        to { transform: rotate(360deg); }
    }

    @keyframes mera-loader-failsafe {
        to { opacity: 0; visibility: hidden; pointer-events: none; }
    }

    @media (prefers-reduced-motion: reduce) {
        .mera-loader { transition: none; }
        .mera-loader__spinner { animation-duration: 2.4s; }
    }
</style>
<noscript><style>.mera-loader { display: none !important; }</style></noscript>

<div class="mera-loader" role="status" aria-live="polite">
    <div class="mera-loader__box">
        <img class="mera-loader__logo" src="{{ asset('images/logo_university.svg') }}" width="132" height="48" alt="">
        <span class="mera-loader__spinner" aria-hidden="true"></span>
        <span class="mera-loader__msg" aria-hidden="true">Procesando…</span>
        <span class="mera-loader__sr">Cargando…</span>
    </div>
</div>

<script>
    (function () {
        var root = document.documentElement;
        var finished = false;

        function done() {
            if (finished) return;
            finished = true;
            root.classList.add('mera-loaded');
            if (window.performance && performance.mark) performance.mark('mera-loaded');
        }

        var domReady = new Promise(function (resolve) {
            if (document.readyState !== 'loading') resolve();
            else document.addEventListener('DOMContentLoaded', resolve, { once: true });
        });
        var fontsReady = document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve();
        var timeout = new Promise(function (resolve) { setTimeout(resolve, 2500); });

        Promise.race([Promise.all([domReady, fontsReady]), timeout]).then(done, done);

        // Al volver con "Atrás", el navegador puede restaurar la página desde caché.
        window.addEventListener('pageshow', function (event) { if (event.persisted) done(); });
    })();
</script>
