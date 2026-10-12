/**
 * Avance de un programa (p. ej. /proceso-de-manipulacion-de-alimentos).
 *
 * Marca como "visto" cada módulo que la persona abre, resalta el siguiente y avisa cuando
 * terminó todos. Se guarda SOLO en este navegador (localStorage), sin cuentas ni datos
 * personales. Si el almacenamiento no está disponible (modo privado, bloqueos), la página
 * funciona igual, solo sin marcar avance.
 */
const PREFIX = 'mera:program:';

function storage() {
    try {
        const probe = '__mera_probe__';
        window.localStorage.setItem(probe, '1');
        window.localStorage.removeItem(probe);
        return window.localStorage;
    } catch {
        return null;
    }
}

function read(store, key) {
    try {
        const value = JSON.parse(store.getItem(key) || '[]');
        return Array.isArray(value) ? value.filter((v) => typeof v === 'string') : [];
    } catch {
        return [];
    }
}

export function initProgramProgress() {
    const root = document.querySelector('[data-program-progress]');
    const store = root && storage();

    if (!root || !store) return;

    const key = PREFIX + root.dataset.programProgress;
    const items = [...root.querySelectorAll('[data-module]')];
    const known = new Set(items.map((li) => li.dataset.module));
    const total = items.length;

    const box = root.querySelector('[data-progress-box]');
    const count = root.querySelector('[data-progress-count]');
    const bar = root.querySelector('[data-progress-bar]');
    const fill = root.querySelector('[data-progress-fill]');
    const ready = root.querySelector('[data-progress-ready]');
    const hint = root.querySelector('[data-progress-hint]');
    const resetWrap = root.querySelector('[data-progress-reset-wrap]');
    const resetButton = root.querySelector('[data-progress-reset]');

    const render = () => {
        // Ignora claves de módulos que ya no existen en el programa.
        const seen = new Set(read(store, key).filter((k) => known.has(k)));
        let nextMarked = false;

        items.forEach((li) => {
            const done = seen.has(li.dataset.module);
            const link = li.querySelector('a');
            const isNext = !done && !nextMarked && !!link && seen.size > 0;

            li.classList.toggle('is-done', done);
            li.classList.toggle('is-next', isNext);
            if (isNext) nextMarked = true;

            if (link) {
                const base = link.dataset.label || link.getAttribute('aria-label') || '';
                link.dataset.label = base;
                link.setAttribute('aria-label', done ? `${base}. Ya lo viste.` : base);
            }
        });

        const n = seen.size;
        if (box) box.hidden = false;
        if (hint) hint.hidden = false;
        if (count) count.textContent = `${n} de ${total} ${total === 1 ? 'módulo' : 'módulos'}`;
        if (bar) bar.setAttribute('aria-valuenow', String(n));
        if (fill) fill.style.width = `${total ? (n / total) * 100 : 0}%`;
        if (ready) ready.hidden = n < total;
        if (resetWrap) resetWrap.hidden = n === 0;
        root.classList.toggle('is-complete', total > 0 && n >= total);
    };

    items.forEach((li) => {
        const link = li.querySelector('a');
        if (!link) return;

        // "click" cubre mouse, toque y Enter; "auxclick" cubre el clic central (pestaña nueva).
        const mark = () => {
            const seen = new Set(read(store, key));
            seen.add(li.dataset.module);
            try {
                store.setItem(key, JSON.stringify([...seen]));
            } catch {
                /* almacenamiento lleno o bloqueado: no pasa nada */
            }
            render();
        };

        link.addEventListener('click', mark);
        link.addEventListener('auxclick', (e) => e.button === 1 && mark());
    });

    resetButton?.addEventListener('click', () => {
        try {
            store.removeItem(key);
        } catch {
            /* sin acción */
        }
        render();
        root.querySelector('[data-module] a')?.focus();
    });

    // Al volver a esta pestaña (después de ver un módulo), refresca por si cambió en otra pestaña.
    window.addEventListener('storage', (e) => e.key === key && render());
    document.addEventListener('visibilitychange', () => !document.hidden && render());

    render();
}
