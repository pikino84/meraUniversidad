/**
 * Envío de formularios (CLAUDE.md §6, pedido del usuario 2026-10-14):
 *  - Muestra el loader de página ("Procesando…") mientras el servidor responde, p. ej. al enviar
 *    un correo (SMTP síncrono) o al procesar los Excel de un reporte.
 *  - Bloquea de forma general los botones de envío para evitar solicitudes dobles.
 *
 * Aplica a formularios POST (incluye PUT/PATCH/DELETE vía _method). No aplica a:
 *  - formularios GET (filtros y búsquedas: son rápidos y no cambian nada);
 *  - subidas con barra propia (data-upload-form, ver upload.js);
 *  - formularios con data-no-loader o target distinto de _self (p. ej. descargas).
 *
 * data-loader-message="…" cambia el texto del loader para ese formulario.
 *
 * confirm.js llama a lockForm() después de confirmar, porque form.submit() no dispara "submit".
 */
export function initSubmitGuard() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !guarded(form)) {
            return;
        }

        lockForm(form);
    });

    // Al volver con "Atrás" el navegador puede restaurar la página tal como quedó (bloqueada).
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) unlockAll();
    });
}

/** Bloquea los botones del formulario y muestra el loader. Idempotente. */
export function lockForm(form) {
    if (form.dataset.submitting === '1') return;
    form.dataset.submitting = '1';

    // Un instante después: si se deshabilita durante el evento "submit", el navegador deja de
    // enviar el name/value del botón que se pulsó.
    setTimeout(() => {
        submitButtons(form).forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.dataset.locked = '1';
        });
    }, 0);

    // Mensaje propio del formulario (data-loader-message), p. ej. "Procesando los Excel…".
    const msg = document.querySelector('.mera-loader__msg');
    if (msg) {
        msg.dataset.default ??= msg.textContent;
        msg.textContent = form.dataset.loaderMessage || msg.dataset.default;
    }

    // Si el servidor responde muy rápido, el loader ni se alcanza a ver (evita parpadeos).
    form.dataset.loaderTimer = String(setTimeout(() => document.documentElement.classList.add('mera-busy'), 150));
}

function unlockAll() {
    document.documentElement.classList.remove('mera-busy');

    document.querySelectorAll('form[data-submitting="1"]').forEach((form) => {
        clearTimeout(Number(form.dataset.loaderTimer));
        delete form.dataset.submitting;
        delete form.dataset.loaderTimer;
    });

    document.querySelectorAll('[data-locked="1"]').forEach((button) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        delete button.dataset.locked;
    });
}

function guarded(form) {
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    const target = form.getAttribute('target');

    return method === 'post'
        && !form.hasAttribute('data-upload-form')
        && !form.hasAttribute('data-no-loader')
        && (!target || target === '_self');
}

function submitButtons(form) {
    const inside = [...form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')];
    const outside = form.id ? [...document.querySelectorAll(`[form="${CSS.escape(form.id)}"][type="submit"]`)] : [];

    return [...inside, ...outside];
}
