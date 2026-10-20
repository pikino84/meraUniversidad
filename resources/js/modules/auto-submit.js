/**
 * Formularios de filtros que se aplican solos al cambiar un campo (data-auto-submit), p. ej. los del
 * seguimiento anual. El botón "Aplicar" sigue para quien no tiene JS; con JS se oculta.
 */
export function initAutoSubmit() {
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.classList.add('is-auto');
        // URL limpia: los filtros vacíos no viajan (?localidad=Monterrey en vez de ?curso=&area=…).
        form.addEventListener('submit', () => {
            form.querySelectorAll('select, input').forEach((field) => {
                if (field.name && field.value === '' && field.type !== 'checkbox') field.disabled = true;
            });
        });

        form.addEventListener('change', (event) => {
            if (event.target.matches('select, input[type="checkbox"]')) {
                form.requestSubmit();
            }
        });
    });
}
