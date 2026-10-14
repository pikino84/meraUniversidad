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

    window.addEventListener('beforeunload', (event) => {
        const pending = forms.some((form) => form.dataset.dirty === '1' && form.dataset.saving !== '1');

        if (pending) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}
