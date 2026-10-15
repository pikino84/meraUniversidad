/**
 * Formulario de corte de reporte (resources/views/reports/_form.blade.php).
 * Sugiere la fecha de corte con la fecha AAAA-MM-DD del nombre de un Excel
 * (p. ej. "inscripciones_2026-10-13.xlsx"), solo si el campo sigue vacío.
 */
export function initReportForm() {
    const form = document.querySelector('[data-report-form]');
    const date = form?.querySelector('[data-cut-date]');

    if (!date) return;

    form.querySelectorAll('[data-report-file]').forEach((input) => {
        input.addEventListener('change', () => {
            const match = input.files?.[0]?.name.match(/(20\d{2})[-_.]?(0[1-9]|1[0-2])[-_.]?(0[1-9]|[12]\d|3[01])/);

            if (!match || date.value) return;

            const value = `${match[1]}-${match[2]}-${match[3]}`;
            if (!date.max || value <= date.max) {
                date.value = value;
                date.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });
}
