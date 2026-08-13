// asstets/js/mobile-nav.js
// Mobile navigation drawer functionality
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-mobile-nav-toggle]');
    const drawer = document.getElementById('mobile-nav-drawer');
    const closeButton = document.querySelector('[data-mobile-nav-close]');
    const backdrop = document.querySelector('[data-mobile-nav-backdrop]');
    const landscapeQuery = window.matchMedia('(orientation: landscape)');
    const closeDelayMs = 220;

    if (!toggle || !drawer || !backdrop) {
        return;
    }

    // Update the drawer and backdrop to match the current open or closed state.
    const setOpenState = (isOpen) => {
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        drawer.classList.toggle('is-open', isOpen);
        backdrop.classList.toggle('is-open', isOpen);
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        drawer.hidden = false;
        backdrop.hidden = !isOpen;
        document.body.style.overflow = isOpen ? 'hidden' : '';
    };

    // Close the drawer and optionally move focus back to the toggle button.
    const closeDrawer = (shouldFocusToggle = true) => {
        setOpenState(false);

        if (shouldFocusToggle) {
            toggle.focus();
        }
    };

    // Keep the drawer hidden in landscape mode and reset the open state.
    const syncLandscapeState = () => {
        if (landscapeQuery.matches) {
            drawer.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            drawer.hidden = true;
            backdrop.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        } else {
            drawer.hidden = false;
        }
    };

    syncLandscapeState();

    // Toggle the drawer when the mobile menu button is clicked.
    toggle.addEventListener('click', () => {
        if (landscapeQuery.matches) {
            syncLandscapeState();
            return;
        }

        const isOpen = drawer.classList.contains('is-open');
        setOpenState(!isOpen);
    });

    if (closeButton) {
        closeButton.addEventListener('click', () => closeDrawer());
    }

    // Close the drawer when the backdrop is clicked.
    backdrop.addEventListener('click', () => closeDrawer());

    // Close the drawer after a short delay when a menu link or action is used.
    drawer.addEventListener('click', (event) => {
        const target = event.target;

        if (target instanceof HTMLElement && target.closest('a, button, form')) {
            if (!target.closest('[data-profile-modal-open]')) {
                setTimeout(() => closeDrawer(), closeDelayMs);
            }
        }
    });

    // Allow Escape to close the drawer.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    // Re-sync the drawer whenever the orientation changes.
    landscapeQuery.addEventListener('change', syncLandscapeState);
});