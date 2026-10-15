/**
 * Carpetas de curso en el panel:
 *  - Formulario de módulo: idioma y posición solo se habilitan si hay carpeta.
 *  - Listado de cursos: selección múltiple → barra "Mover a…".
 */
export function initCourseGroups() {
    initGroupDependentFields();
    initBulkMove();
    initCopyLinks();
}

/**
 * Botones "Copiar link" (data-copy="<url>"). Usa el portapapeles; si el navegador no lo permite,
 * selecciona el texto para copiarlo a mano.
 */
function initCopyLinks() {
    document.querySelectorAll('[data-copy]').forEach((button) => {
        const label = button.querySelector('[data-copy-label]');
        const original = label ? label.textContent : '';

        button.addEventListener('click', async () => {
            const value = button.dataset.copy;
            let ok = false;

            try {
                await navigator.clipboard.writeText(value);
                ok = true;
            } catch {
                const code = button.closest('li')?.querySelector('code');
                if (code) {
                    const range = document.createRange();
                    range.selectNodeContents(code);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }

            if (label) {
                label.textContent = ok ? '¡Copiado!' : 'Selecciónalo y copia';
                setTimeout(() => { label.textContent = original; }, 2000);
            }
        });
    });
}

function initGroupDependentFields() {
    const select = document.querySelector('[data-group-select]');
    if (!select) return;

    const dependents = document.querySelectorAll('[data-group-dependent]');
    const sync = () => {
        const enabled = select.value !== '';
        dependents.forEach((wrap) => {
            wrap.classList.toggle('opacity-50', !enabled);
            wrap.querySelectorAll('select, input').forEach((field) => {
                field.disabled = !enabled;
            });
        });
    };

    select.addEventListener('change', sync);
    sync();
}

function initBulkMove() {
    const form = document.querySelector('[data-bulk-move]');
    if (!form) return;

    const bar = form.querySelector('[data-bulk-bar]');
    const count = form.querySelector('[data-bulk-count]');
    const all = document.querySelectorAll('[data-bulk-all]');
    const group = form.querySelector('[data-bulk-group]');
    const language = form.querySelector('[data-bulk-language]');
    const newName = form.querySelector('[data-bulk-new]');
    const creating = () => group.value === 'new';
    // Hay dos vistas (tabla y tarjetas móviles) con el mismo id de curso: se sincronizan por valor.
    const boxes = () => [...document.querySelectorAll('[data-bulk-item]')];
    const selectedIds = () => [...new Set(boxes().filter((b) => b.checked).map((b) => b.value))];

    const render = () => {
        const ids = selectedIds();
        bar.hidden = ids.length === 0;
        count.textContent = `${ids.length} ${ids.length === 1 ? 'módulo seleccionado' : 'módulos seleccionados'}`;
        all.forEach((a) => {
            a.checked = ids.length > 0 && ids.length === new Set(boxes().map((b) => b.value)).size;
            a.indeterminate = ids.length > 0 && !a.checked;
        });
        language.disabled = group.value === '';

        // "+ Nueva carpeta…": pide el nombre; se crea al mover (sin salir de la lista filtrada).
        if (newName) {
            const wasHidden = newName.hidden;
            newName.hidden = !creating();
            newName.required = creating();
            newName.disabled = !creating();
            if (wasHidden && creating() && !bar.hidden) newName.focus();
        }
    };

    // Si el nombre ya existe en la lista, se avisa aquí (un error del servidor haría perder la selección).
    const existing = () => [...group.options].filter((o) => o.value && o.value !== 'new').map((o) => o.text.trim().toLowerCase());
    newName?.addEventListener('input', () => {
        newName.setCustomValidity(existing().includes(newName.value.trim().toLowerCase())
            ? 'Ya existe una carpeta con ese nombre: elígela en la lista.'
            : '');
    });

    document.addEventListener('change', (event) => {
        const target = event.target;

        if (target.matches('[data-bulk-item]')) {
            boxes().filter((b) => b.value === target.value).forEach((b) => { b.checked = target.checked; });
        } else if (target.matches('[data-bulk-all]')) {
            boxes().forEach((b) => { b.checked = target.checked; });
        }

        render();
    });

    group.addEventListener('change', render);

    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name="course_ids[]"]').forEach((i) => i.remove());
        selectedIds().forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'course_ids[]';
            input.value = id;
            form.appendChild(input);
        });
    });

    form.querySelector('[data-bulk-clear]')?.addEventListener('click', () => {
        boxes().forEach((b) => { b.checked = false; });
        render();
    });

    render();
}
