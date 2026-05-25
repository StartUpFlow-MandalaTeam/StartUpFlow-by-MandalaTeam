document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('startupOverlay');
    const powerBtn = document.getElementById('startupPowerBtn');

    if (!overlay || !powerBtn) {
        return;
    }

    powerBtn.addEventListener('click', () => {
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        overlay.style.transition = 'opacity .35s ease';

        setTimeout(() => {
            overlay.remove();
        }, 350);
    });
});