/**
 * Confirmación genérica para formularios destructivos:
 *
 *   <form method="POST" data-confirm="¿Eliminar el curso «X»?" data-confirm-detail="…">
 *       … <button type="submit">…</button>
 *   </form>
 *
 * Reemplaza los confirmDelete(id) repetidos en cada vista (y sus IDs duplicados).
 */
export function initConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') {
            return;
        }

        event.preventDefault();

        window.swal({
            title: form.dataset.confirm,
            text: form.dataset.confirmDetail || 'Esta acción no se puede deshacer.',
            icon: 'warning',
            buttons: ['Cancelar', form.dataset.confirmButton || 'Eliminar'],
            dangerMode: true,
        }).then((confirmed) => {
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });
}
