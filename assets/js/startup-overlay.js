//assets/js/startup-overlay.js
// JavaScript for handling the startup overlay functionality
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('startupOverlay');
    const powerBtn = document.getElementById('startupPowerBtn');
    const authButtons = document.getElementById('authButtons');

    if (!overlay || !powerBtn || !authButtons) {
        return;
    }

// Event listener for the power button to hide the overlay and show auth buttons
    powerBtn.addEventListener('click', () => {
        overlay.style.opacity = '0';
        overlay.style.pointerEvents = 'none';
        overlay.style.transition = 'opacity .35s ease';

        setTimeout(() => {
            overlay.style.display = 'none';
            authButtons.hidden = false;
        }, 350);
    });
});