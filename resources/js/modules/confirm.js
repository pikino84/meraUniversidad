/**
 * Confirmación genérica antes de enviar un formulario:
 *
 *   <form method="POST" data-confirm="¿Eliminar el módulo?" data-confirm-strong="Nombre largo"
 *         data-confirm-detail="…" data-confirm-button="Eliminar" data-confirm-tone="danger">
 *
 * - data-confirm: título CORTO (los datos largos, como un correo, rompen el modal: van en
 *   data-confirm-strong, que se muestra en el cuerpo y se parte en varias líneas si hace falta).
 * - data-confirm-tone: "danger" (por defecto; ícono de advertencia y botón rojo) o "info"
 *   (acciones que no destruyen nada: enviar link, publicar, restaurar, quitar de carpeta).
 *
 * Auditoría UI 2026-10-14 (#1-#3): sin desbordes, "Cancelar" legible y semántica correcta.
 */
export function initConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        const danger = (form.dataset.confirmTone || 'danger') === 'danger';

        window.swal({
            title: form.dataset.confirm,
            content: buildContent(form, danger),
            icon: danger ? 'warning' : 'info',
            className: danger ? 'mera-swal mera-swal-danger' : 'mera-swal',
            buttons: {
                cancel: { text: 'Cancelar', value: false, visible: true, className: 'mera-swal-cancel', closeModal: true },
                confirm: {
                    text: form.dataset.confirmButton || (danger ? 'Eliminar' : 'Aceptar'),
                    value: true,
                    className: danger ? 'mera-swal-confirm-danger' : 'mera-swal-confirm',
                    closeModal: true,
                },
            },
            dangerMode: danger,
        }).then((confirmed) => {
            if (!confirmed) return;

            form.dataset.confirmed = '1';
            // Evita envíos dobles mientras el servidor responde.
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            });
            form.submit();
        });
    });
}

/** Cuerpo del modal con textContent (nunca innerHTML: los datos vienen del usuario). */
function buildContent(form, danger) {
    const wrap = document.createElement('div');
    wrap.className = 'mera-swal-body';

    if (form.dataset.confirmStrong) {
        const strong = document.createElement('strong');
        strong.className = 'mera-swal-strong';
        strong.textContent = form.dataset.confirmStrong;
        wrap.appendChild(strong);
    }

    const detail = form.dataset.confirmDetail || (danger ? 'Esta acción no se puede deshacer.' : '');

    if (detail) {
        const p = document.createElement('p');
        p.textContent = detail;
        wrap.appendChild(p);
    }

    return wrap;
}
