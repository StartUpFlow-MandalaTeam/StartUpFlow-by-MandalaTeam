document.addEventListener('DOMContentLoaded', () => {
    const openTrigger = document.querySelector('[data-profile-modal-open]');
    const modal = document.getElementById('profile-modal');
    const closeTrigger = document.querySelector('[data-profile-modal-close]');

    if (!openTrigger || !modal) {
        return;
    }

    const syncExpandedState = (isOpen) => {
        openTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    const openModal = () => {
        if (!modal.open) {
            modal.showModal();
        }

        syncExpandedState(true);
    };

    const closeModal = () => {
        if (modal.open) {
            modal.close();
        }

        syncExpandedState(false);
        openTrigger.focus();
    };

    openTrigger.addEventListener('click', openModal);

    if (closeTrigger) {
        closeTrigger.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    modal.addEventListener('close', () => {
        syncExpandedState(false);
    });
});