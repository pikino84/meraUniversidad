/**
 * Aviso de cambios sin guardar (CLAUDE.md §12, F4) para <form data-warn-unsaved>.
 *
 * Marca el formulario como modificado al escribir o elegir un archivo y, si se intenta salir
 * de la página, el navegador pide confirmación. No avisa al enviar: en un envío normal se
 * desactiva en el submit, y en las subidas por XHR (upload.js) se marca data-saving="1"
 * justo antes de redirigir.
 */
export function initUnsavedWarning() {
    const forms = [...document.querySelectorAll('form[data-warn-unsaved]')];

    if (forms.length === 0) return;

    forms.forEach((form) => {
        const markDirty = () => { form.dataset.dirty = '1'; };
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        form.addEventListener('submit', (event) => {
            if (!event.defaultPrevented) form.dataset.saving = '1';
        });
    });

    const hasChanges = () => forms.some((form) => form.dataset.dirty === '1' && form.dataset.saving !== '1');

    // Enlaces de la propia página ("Volver", paginación, pestañas…): aviso con la ventana del sistema
    // (2026-10-23; antes salía la de Chrome). Cerrar la pestaña o recargar solo lo puede avisar el navegador.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || !hasChanges() || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey
            || link.target === '_blank' || link.hasAttribute('download') || link.getAttribute('href').startsWith('#')) {
            return;
        }

        event.preventDefault();
        window.swal({
            title: '¿Salir sin guardar?',
            text: 'Tienes cambios en esta página que no has guardado. Si sales, se pierden.',
            icon: 'warning',
            buttons: {
                cancel: { text: 'Seguir aquí', value: false, visible: true, className: 'mera-swal-cancel', closeModal: true },
                confirm: { text: 'Salir sin guardar', value: true, visible: true, className: 'mera-swal-confirm-danger', closeModal: true },
            },
            className: 'mera-swal-danger',
        }).then((leave) => {
            if (!leave) return;
            forms.forEach((form) => { form.dataset.saving = '1'; });
            window.location.href = link.href;
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (hasChanges()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

/**
 * Casilla [data-show-passwords]: muestra u oculta las contraseñas de su formulario
 * (auditoría UI 2026-10-14, #15). Se exporta aquí para no crear otro módulo por 10 líneas.
 */
export function initShowPasswords() {
    document.querySelectorAll('[data-show-passwords]').forEach((checkbox) => {
        const form = checkbox.closest('form');

        checkbox.addEventListener('change', () => {
            form?.querySelectorAll('input[data-password], input[type="password"]').forEach((input) => {
                input.dataset.password = '1';
                input.type = checkbox.checked ? 'text' : 'password';
            });
        });
    });
}
