//assets/js/profile-modal.js
// JavaScript for profile modal handling
document.addEventListener('DOMContentLoaded', () => {
    const openTriggers = Array.from(document.querySelectorAll('[data-profile-modal-open]'));
    const modal = document.getElementById('profile-modal');
    const closeTrigger = document.querySelector('[data-profile-modal-close]');
    const countrySelect = document.querySelector('[data-countries-select]');
    const profileForm = document.getElementById('profile-form');
    const profileSave = profileForm ? profileForm.querySelector('.profile-modal__save') : null;
    const countriesUrl = 'assets/data/countries.json';
    const selectedCountry = countrySelect?.dataset.selectedCountry?.trim() || '';

    let countriesCache = null;
    let countriesPromise = null;

    if (openTriggers.length === 0 || !modal) {
        return;
    }
// Function to render country options in the select element
    const renderCountries = (countries) => {
        if (!countrySelect || countrySelect.dataset.countriesLoaded === 'true') {
            return;
        }

        const placeholderOption = new Option('Seleziona uno Stato', '', true, true);
        placeholderOption.disabled = true;

        countrySelect.replaceChildren(placeholderOption);

        countries.forEach((country) => {
            const option = new Option(country.name, country.code);
            countrySelect.append(option);
        });

        const savedCountry = countries.find((country) =>
            country.code.toLowerCase() === selectedCountry.toLowerCase()
            || country.name.toLowerCase() === selectedCountry.toLowerCase()
        );
        countrySelect.value = savedCountry?.code || '';

        countrySelect.dataset.countriesLoaded = 'true';
        countrySelect.disabled = false;
    };
// Function to load countries from the JSON file, with caching
    const loadCountries = () => {
        if (countriesCache) {
            return Promise.resolve(countriesCache);
        }

        if (!countriesPromise) {
            countriesPromise = fetch(countriesUrl)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Impossibile caricare l\'elenco dei paesi');
                    }

                    return response.json();
                })
                .then((countries) => {
                    countriesCache = countries;
                    renderCountries(countries);
                    return countries;
                })
                .catch((error) => {
                    countriesPromise = null;
                    console.error(error);
                    return [];
                });
        }

        return countriesPromise;
    };
// Disable the country select until the countries are loaded
    if (countrySelect) {
        countrySelect.disabled = true;
    }
// Function to synchronize the aria-expanded state of all open triggers
    const syncExpandedState = (isOpen) => {
        openTriggers.forEach((trigger) => {
            trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    };
// Remember the last trigger used to open the modal for focus management when closing
    let lastTrigger = openTriggers[0];
// Function to open the modal and load countries if needed
    const openModal = (trigger) => {
        lastTrigger = trigger;

        if (!modal.open) {
            modal.showModal();
        }

        if (countrySelect && countrySelect.dataset.countriesLoaded !== 'true') {
            loadCountries();
        }

        syncExpandedState(true);
    };
// Function to close the modal and return focus to the last trigger
    const closeModal = () => {
        if (modal.open) {
            modal.close();
        }

        syncExpandedState(false);

        if (lastTrigger) {
            lastTrigger.focus();
        }
    };
// Event listeners for opening and closing the modal
    openTriggers.forEach((trigger) => {
        trigger.addEventListener('click', () => openModal(trigger));
    });

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

    const updateAvatarImages = (avatarUrl) => {
        document.querySelectorAll('[data-profile-avatar]').forEach((avatar) => {
            avatar.replaceChildren();

            if (avatarUrl) {
                const image = document.createElement('img');
                image.src = avatarUrl;
                image.alt = '';
                avatar.append(image);
                return;
            }

            const placeholder = document.createElement('span');
            placeholder.className = 'material-symbols-outlined';
            placeholder.textContent = 'person';
            avatar.append(placeholder);
        });
    };

    if (profileForm && profileSave) {
        profileSave.addEventListener('click', () => profileForm.requestSubmit());
        profileForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const formData = new FormData(profileForm);
            const avatarFile = profileForm.elements.avatar?.files[0];
            const requestOptions = {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            };

            fetch('/auth/process-profile.php', {
                ...requestOptions,
                body: formData
            })
                .then((response) => response.json())
                .then((profileData) => {
                    if (!profileData.success) {
                        throw new Error(profileData.message || 'Impossibile salvare il profilo');
                    }

                    if (!avatarFile) {
                        return null;
                    }

                    const avatarData = new FormData();
                    avatarData.append('csrf_token', formData.get('csrf_token'));
                    avatarData.append('avatar', avatarFile);

                    return fetch('/auth/process-avatar.php', {
                        ...requestOptions,
                        body: avatarData
                    }).then((response) => response.json());
                })
                .then((avatarData) => {
                    if (avatarData && !avatarData.success) {
                        throw new Error(avatarData.message || 'Impossibile salvare l’immagine');
                    }

                    if (avatarData?.avatar_url) {
                        updateAvatarImages(avatarData.avatar_url);
                    }
                    alert('Profilo aggiornato');
                    closeModal();
                })
                .catch((error) => alert(error.message || 'Impossibile aggiornare il profilo'));
        });
    }

    // --- Change Email modal handling (top-level dialog) ---
    const changeEmailModal = document.getElementById('change-email-modal');
    const changeEmailOpenTriggers = Array.from(document.querySelectorAll('[data-change-email-open]'));
    const changeEmailCancel = changeEmailModal ? changeEmailModal.querySelector('[data-change-email-cancel]') : null;
    const changeEmailForm = document.getElementById('change-email-form');

    // Helper to set aria-hidden on main page regions while modal is open
    const setPageHidden = (hidden) => {
        const els = document.querySelectorAll('header, main, aside, footer, .dashboard-navbar__drawer');
        els.forEach((el) => {
            if (hidden) el.setAttribute('aria-hidden', 'true');
            else el.removeAttribute('aria-hidden');
        });
    };

    function openChangeEmailModal() {
        // close profile modal if open
        if (modal && modal.open) modal.close();

        if (changeEmailModal && !changeEmailModal.open) {
            changeEmailModal.showModal();
            setPageHidden(true);
            const first = changeEmailModal.querySelector('input, button, [tabindex]:not([tabindex="-1"])');
            if (first) first.focus();
        }
    }

    function closeChangeEmailModal() {
        if (changeEmailModal && changeEmailModal.open) {
            changeEmailModal.close();
            setPageHidden(false);
            if (lastTrigger) lastTrigger.focus();
        }
    }

    changeEmailOpenTriggers.forEach((t) => t.addEventListener('click', () => openChangeEmailModal()));
    if (changeEmailCancel) changeEmailCancel.addEventListener('click', closeChangeEmailModal);

    // Submit via fetch to provide neutral response and keep modal UX
    if (changeEmailForm) {
        changeEmailForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(changeEmailForm);

            fetch(changeEmailForm.action, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then((res) => res.json())
            .then((data) => {
                // show a neutral success/error message
                if (data && data.success) {
                    alert(data.message || 'Se la richiesta è valida, riceverai una email di verifica.');
                } else {
                    alert(data.message || 'Si è verificato un errore.');
                }
                closeChangeEmailModal();
            })
            .catch((err) => {
                console.error(err);
                alert('Errore inviando la richiesta. Riprova.');
            });
        });
    }
});