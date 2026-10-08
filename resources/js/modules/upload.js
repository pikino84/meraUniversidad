/**
 * Subida de cursos con barra de progreso, cancelación y validación previa de tamaño.
 *
 *   <form data-upload-form data-max-bytes="1048576000"> … </form>
 *   <div data-upload-overlay> … [data-upload-bar] [data-upload-percent] [data-upload-status] [data-upload-cancel]
 *
 * Al usar XHR el formulario NO se recarga si hay errores: el usuario no pierde el ZIP elegido.
 */
const formatBytes = (bytes) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${(bytes / 1024 ** i).toFixed(i ? 1 : 0)} ${units[i]}`;
};

export function initUploadForms() {
    document.querySelectorAll('form[data-upload-form]').forEach(setupForm);
}

function setupForm(form) {
    const overlay = document.querySelector('[data-upload-overlay]');
    const bar = overlay?.querySelector('[data-upload-bar]');
    const percent = overlay?.querySelector('[data-upload-percent]');
    const status = overlay?.querySelector('[data-upload-status]');
    const cancelButton = overlay?.querySelector('[data-upload-cancel]');
    const errorBox = form.querySelector('[data-upload-errors]');
    const maxBytes = Number(form.dataset.maxBytes || 0);
    let controller = null;

    const showOverlay = (visible) => {
        if (overlay) overlay.hidden = !visible;
    };

    const setProgress = (value, text) => {
        if (bar) {
            bar.style.width = `${value}%`;
            bar.setAttribute('aria-valuenow', String(value));
        }
        if (percent) percent.textContent = `${value}%`;
        if (status && text) status.textContent = text;
    };

    const showErrors = (messages) => {
        if (!errorBox) {
            window.swal('No se pudo guardar', messages.join('\n'), 'error');
            return;
        }
        errorBox.innerHTML = '';
        const list = document.createElement('ul');
        list.className = 'mb-0';
        messages.forEach((message) => {
            const item = document.createElement('li');
            item.textContent = message; // textContent: nunca HTML del servidor
            list.appendChild(item);
        });
        errorBox.appendChild(list);
        errorBox.hidden = false;
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    cancelButton?.addEventListener('click', () => controller?.abort());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (errorBox) errorBox.hidden = true;

        const zip = form.querySelector('input[type="file"][name="zip_file"]')?.files?.[0];

        if (zip && maxBytes && zip.size > maxBytes) {
            showErrors([`El archivo ZIP pesa ${formatBytes(zip.size)}; el máximo permitido es ${formatBytes(maxBytes)}.`]);
            return;
        }

        controller = new AbortController();
        setProgress(0, zip ? `Subiendo ${formatBytes(zip.size)}…` : 'Guardando…');
        showOverlay(true);

        try {
            const { data } = await window.axios.post(form.action, new FormData(form), {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
                onUploadProgress: (e) => {
                    if (!e.total) return;
                    const value = Math.round((e.loaded / e.total) * 100);
                    setProgress(value, value < 100
                        ? `Subiendo ${formatBytes(e.loaded)} de ${formatBytes(e.total)}…`
                        : 'Procesando el curso en el servidor, esto puede tardar unos minutos…');
                },
            });

            window.location.assign(data.redirect);
        } catch (error) {
            showOverlay(false);

            if (window.axios.isCancel?.(error) || error.name === 'CanceledError') {
                showErrors(['Subida cancelada.']);
                return;
            }

            const response = error.response;

            if (!response) {
                showErrors(['No hay conexión con el servidor. Revisa tu red e intenta de nuevo.']);
            } else if (response.status === 422 && response.data?.errors) {
                showErrors(Object.values(response.data.errors).flat());
            } else if (response.status === 413) {
                showErrors(['El archivo excede el tamaño máximo que acepta el servidor.']);
            } else if (response.status === 419) {
                showErrors(['Tu sesión expiró. Recarga la página e intenta de nuevo.']);
            } else {
                showErrors([response.data?.message || 'Ocurrió un error inesperado. Intenta de nuevo.']);
            }
        } finally {
            controller = null;
        }
    });
}
