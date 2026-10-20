/**
 * Lo único que el panel usaba de public/js/script.js de la plantilla pcoded (CLAUDE.md §12, P10).
 * El botón de pantalla completa se quitó en la auditoría UI del 2026-10-14.
 * Ese archivo traía además chat, tooltips de jQuery UI, tarjetas plegables y Waves, que el
 * panel no usa; al quitarlo se dejó de cargar jQuery UI (248 KB), Waves y widget.css.
 */
export function initPanelTemplate() {
    initMobileCollapseIcon();

    // Botones "Imprimir" (p. ej. la ficha individual del seguimiento): sin onclick en línea.
    document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
}

/** Mismo comportamiento que la plantilla: el ícono del botón de menú alterna al abrir/cerrar. */
function initMobileCollapseIcon() {
    const button = document.getElementById('mobile-collapse');
    const icon = button?.querySelector('i');

    if (!icon) return;

    icon.classList.add('icon-toggle-right');
    button.addEventListener('click', () => {
        icon.classList.toggle('icon-toggle-right');
        icon.classList.toggle('icon-toggle-left');
    });
}
