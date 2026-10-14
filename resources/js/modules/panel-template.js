/**
 * Lo único que el panel usaba de public/js/script.js de la plantilla pcoded (CLAUDE.md §12, P10).
 * Ese archivo traía además chat, tooltips de jQuery UI, tarjetas plegables y Waves, que el
 * panel no usa; al quitarlo se dejó de cargar jQuery UI (248 KB), Waves y widget.css.
 */
export function initPanelTemplate() {
    initMobileCollapseIcon();
    initFullScreen();
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

/** Botón [data-fullscreen]: entra o sale de pantalla completa y cambia su ícono. */
function initFullScreen() {
    document.querySelectorAll('[data-fullscreen]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();

            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else {
                    await document.documentElement.requestFullscreen();
                }
            } catch {
                return; // el navegador no lo permite (p. ej. dentro de un iframe)
            }
        });
    });

    document.addEventListener('fullscreenchange', () => {
        document.querySelectorAll('[data-fullscreen] .full-screen').forEach((icon) => {
            icon.classList.toggle('icon-maximize', !document.fullscreenElement);
            icon.classList.toggle('icon-minimize', Boolean(document.fullscreenElement));
        });
    });
}
