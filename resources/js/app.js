import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('keydown', function (e) {
    if (e.key === 'PrintScreen') {
        navigator.clipboard.writeText('');
        alert('Captura de pantalla deshabilitada.');
    }
    if (e.ctrlKey && (e.key === 'u' || e.key === 's')) {
        e.preventDefault();
        alert('Acción no permitida.');
    }
    if (e.key === 'F12') {
        e.preventDefault();
    }
});

// Overlay cuando pierde foco
document.addEventListener('visibilitychange', function () {
    const overlay = document.getElementById('anti-screenshot-overlay');
    if (document.hidden) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
});

$(function () {

    const pcoded = document.getElementById('pcoded');

    if (!pcoded) return;

    function toggleBodyScroll() {
        if (window.innerWidth > 992) {
            $('body').css('overflow', '');
            return;
        }

        const navType = pcoded.getAttribute('vertical-nav-type');

        if (navType === 'expanded') {
            $('body').css('overflow', 'hidden');
        } else {
            $('body').css('overflow', '');
        }
    }

    // Estado inicial
    toggleBodyScroll();

    // Escuchar cambios del atributo
    const observer = new MutationObserver(function () {
        toggleBodyScroll();
    });

    observer.observe(pcoded, {
        attributes: true,
        attributeFilter: ['vertical-nav-type']
    });

    // Recalcular al cambiar tamaño de pantalla
    $(window).on('resize', function () {
        toggleBodyScroll();
    });

});

$(".mobile-options").on('click', function () {

    if ($(window).width() <= 992) {
        $(".navbar-container .nav-right").slideToggle('slow');
    }

});