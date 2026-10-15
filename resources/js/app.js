import './bootstrap';

import Alpine from 'alpinejs';
import swal from 'sweetalert';

import { initConfirmForms } from './modules/confirm';
import { initSubmitGuard } from './modules/submit-guard';
import { initFlashMessages } from './modules/flash';
import { initUploadForms } from './modules/upload';
import { initProgramProgress } from './modules/program-progress';
import { initCourseGroups } from './modules/course-groups';
import { initSortableModules } from './modules/sortable-modules';
import { initShowPasswords, initUnsavedWarning } from './modules/unsaved';
import { initPanelTemplate } from './modules/panel-template';
import { initReportForm } from './modules/report-form';

window.Alpine = Alpine;
window.swal = swal; // usado por vistas antiguas y por los módulos

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initFlashMessages();
    initConfirmForms();
    initSubmitGuard();
    initUploadForms();
    initProgramProgress();
    initCourseGroups();
    initSortableModules();
    initUnsavedWarning();
    initShowPasswords();
    initFiltersToggle();
    initPanelTemplate();
    initReportForm();
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

/**
 * Botón "Filtros" en móvil (U19): muestra u oculta el bloque de filtros indicado en aria-controls.
 */
function initFiltersToggle() {
    document.querySelectorAll('[data-filters-toggle]').forEach((button) => {
        const target = document.getElementById(button.getAttribute('aria-controls'));

        if (!target) return;

        button.addEventListener('click', () => {
            const open = target.classList.toggle('is-open');
            button.setAttribute('aria-expanded', String(open));
        });
    });
}
