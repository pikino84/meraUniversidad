/**
 * Muestra los mensajes flash de sesión. El layout los imprime como JSON dentro de
 * <script type="application/json" id="flash-messages"> (sin interpolar en JS → sin XSS
 * ni errores de sintaxis por comillas o saltos de línea).
 */
export function initFlashMessages() {
    const node = document.getElementById('flash-messages');

    if (!node) return;

    let flash;

    try {
        flash = JSON.parse(node.textContent || '{}');
    } catch {
        return;
    }

    if (flash.success) {
        window.swal('¡Listo!', flash.success, 'success');
    } else if (flash.error) {
        window.swal('Ocurrió un problema', flash.error, 'error');
    }
}
