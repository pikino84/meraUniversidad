/**
 * Menús hechos con <details class="mera-report-more"> ("Más acciones" del corte): se cierran con
 * Escape o al hacer clic fuera. Un enlace con data-open-details="<id>" abre ese <details> (p. ej.
 * "Compartir sin sesión") y lo lleva a la vista. Sin JS, <details> sigue funcionando.
 */
export function initDetailsMenus() {
    const menus = [...document.querySelectorAll('details.mera-report-more')];

    if (menus.length) {
        document.addEventListener('click', (event) => {
            menus.forEach((menu) => {
                if (menu.open && !menu.contains(event.target)) menu.open = false;
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            menus.filter((menu) => menu.open).forEach((menu) => {
                menu.open = false;
                menu.querySelector('summary')?.focus();
            });
        });
    }

    document.querySelectorAll('[data-open-details]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const target = document.getElementById(link.dataset.openDetails);

            if (!target) return;

            event.preventDefault();
            target.open = true;
            link.closest('details')?.removeAttribute('open');
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target.querySelector('summary')?.focus({ preventScroll: true });
        });
    });
}
