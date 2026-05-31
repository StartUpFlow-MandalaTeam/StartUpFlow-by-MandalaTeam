//assets/js/profile-modal.js
// JavaScript for profile modal handling
document.addEventListener('DOMContentLoaded', () => {
    const openTriggers = Array.from(document.querySelectorAll('[data-profile-modal-open]'));
    const modal = document.getElementById('profile-modal');
    const closeTrigger = document.querySelector('[data-profile-modal-close]');
    const countrySelect = document.querySelector('[data-countries-select]');
    const countriesUrl = 'assets/data/countries.json';

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

        countrySelect.value = '';

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
});