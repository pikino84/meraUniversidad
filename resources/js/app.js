import './bootstrap';

import Alpine from 'alpinejs';
import swal from 'sweetalert';

import { initConfirmForms } from './modules/confirm';
import { initFlashMessages } from './modules/flash';
import { initUploadForms } from './modules/upload';

window.Alpine = Alpine;
window.swal = swal; // usado por vistas antiguas y por los módulos

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initFlashMessages();
    initConfirmForms();
    initUploadForms();
    initMobileMenu();
});

/**
 * Bloquea el scroll del body cuando el menú lateral está abierto en móvil.
 */
function initMobileMenu() {
    const pcoded = document.getElementById('pcoded');

    if (!pcoded) return;

    const toggleBodyScroll = () => {
        const expanded = window.innerWidth <= 992 && pcoded.getAttribute('vertical-nav-type') === 'expanded';
        document.body.style.overflow = expanded ? 'hidden' : '';
    };

    toggleBodyScroll();

    new MutationObserver(toggleBodyScroll).observe(pcoded, {
        attributes: true,
        attributeFilter: ['vertical-nav-type'],
    });

    window.addEventListener('resize', toggleBodyScroll);
}
