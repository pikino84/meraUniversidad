import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('keydown', function (e) {
    if (e.key === 'PrintScreen') {
        navigator.clipboard.writeText('');
        alert('Captura de pantalla deshabilitada.');
    }
    if (e.ctrlKey && (e.key === 'u' || e.key === 's')) {
        e.preventDefault();
        alert('Acción no permitida.');
    }
    if (e.key === 'F12') {
        e.preventDefault();
    }
});

// Overlay cuando pierde foco
document.addEventListener('visibilitychange', function () {
    const overlay = document.getElementById('anti-screenshot-overlay');
    if (document.hidden) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
});
