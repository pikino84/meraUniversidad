/**
 * Arrastrar y soltar para ordenar los módulos de una carpeta (CLAUDE.md §12, U18).
 *
 * Se activa en <ol data-sortable-url="…"> (la URL ya indica la versión), con un <li data-id>
 * por módulo y un asa [data-sortable-handle]. Usa drag & drop nativo (sin dependencias) y
 * guarda con PATCH. Las flechas ↑/↓ siguen funcionando como alternativa accesible y en
 * pantallas táctiles, donde el arrastre nativo no está disponible.
 */
export function initSortableModules() {
    document.querySelectorAll('ol[data-sortable-url]').forEach(initList);
}

function initList(list) {
    const status = list.parentElement.querySelector('[data-sortable-status]');
    const ids = () => [...list.querySelectorAll(':scope > li[data-id]')].map((li) => li.dataset.id);
    let dragging = null;
    let before = [];

    list.classList.add('is-sortable');

    list.querySelectorAll(':scope > li[data-id]').forEach((item) => {
        const handle = item.querySelector('[data-sortable-handle]');

        if (!handle) return;

        // Solo se arrastra desde el asa, para no interferir con los botones y la selección de texto.
        handle.addEventListener('mousedown', () => { item.draggable = true; });
        handle.addEventListener('mouseup', () => { item.draggable = false; });

        item.addEventListener('dragstart', (event) => {
            dragging = item;
            before = ids();
            item.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.id);
        });

        item.addEventListener('dragend', () => {
            item.draggable = false;
            item.classList.remove('is-dragging');
            dragging = null;

            const after = ids();

            if (after.join() !== before.join()) {
                refresh(list);
                save(list, status, after, before);
            }
        });
    });

    list.addEventListener('dragover', (event) => {
        if (!dragging) return;

        event.preventDefault();
        const target = event.target.closest('li[data-id]');

        if (!target || target === dragging || target.parentElement !== list) return;

        const rect = target.getBoundingClientRect();
        const below = event.clientY > rect.top + rect.height / 2;
        list.insertBefore(dragging, below ? target.nextSibling : target);
    });

    list.addEventListener('drop', (event) => event.preventDefault());
}

/** Renumera la lista y habilita/deshabilita las flechas del primero y el último. */
function refresh(list) {
    const items = [...list.querySelectorAll(':scope > li[data-id]')];

    items.forEach((item, index) => {
        const handle = item.querySelector('[data-sortable-handle]');
        const label = item.querySelector('[data-sortable-label]');

        if (handle) handle.textContent = String(index + 1);
        if (label) label.textContent = `Módulo ${index + 1} de ${items.length}`;

        // F3: aviso si el número del título no coincide con la nueva posición.
        const mismatch = item.querySelector('[data-title-number]');
        if (mismatch) mismatch.hidden = Number(mismatch.dataset.titleNumber) === index + 1;

        item.querySelector('[data-sortable-arrow="up"] button')?.toggleAttribute('disabled', index === 0);
        item.querySelector('[data-sortable-arrow="down"] button')?.toggleAttribute('disabled', index === items.length - 1);
    });
}

async function save(list, status, order, previous) {
    list.classList.add('is-saving');

    try {
        await window.axios.patch(list.dataset.sortableUrl, {
            order: order.map(Number),
        }, {
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        });

        if (status) status.textContent = 'Orden guardado.';
    } catch (error) {
        // Regresa la lista al orden anterior para que lo que se ve coincida con lo guardado.
        previous.forEach((id) => list.appendChild(list.querySelector(`:scope > li[data-id="${id}"]`)));
        refresh(list);

        const message = error.response?.data?.message || 'No se pudo guardar el orden. Inténtalo de nuevo.';
        window.swal('No se guardó el orden', message, 'error');
    } finally {
        list.classList.remove('is-saving');
    }
}
