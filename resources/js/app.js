import '../css/editorial-sidebar-overrides.css';
import '../css/editorial-tables.css';
import '../css/reviews.css';
import '../css/review-anchor.css';
import '../css/review-rating-hover.css';
import '../css/saffron.css';
import '../css/contact.css';
import '../css/auth.css';
import '../css/claim-auth.css';
import '../css/claim-flow.css';
import '../css/contact-responsive.css';
import '../css/contact-proportions.css';
import '../css/contact-asset.css';
import '../css/account.css';
import '../css/owner-restaurant-editor.css';
import '../css/typography.css';
import { initializeAddressSelectors } from './address-selector';
import { initializeManagedRestaurantMedia } from './managed-restaurant-media';
import { initializeRestaurantPhotoPickers } from './restaurant-photo-picker';
import { initializeRestaurantHoursEditors, validateRestaurantHoursEditors } from './restaurant-hours-editor';

const menu = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('#mobile-nav');

const contactForm = document.querySelector('[data-contact-form]');
if (contactForm) {
    const message = contactForm.querySelector('#contact-message');
    const count = contactForm.querySelector('#contact-message-count');
    const updateCount = () => { count.textContent = `${message.value.length} / ${message.maxLength}`; };
    message.addEventListener('input', updateCount);
    updateCount();
}

const submission = document.querySelector('[data-restaurant-submission]');

const syncTaxonomyRequirement = group => {
    const selected = group.querySelector('input[type="checkbox"]:checked');
    const firstOption = group.querySelector('input[type="checkbox"]');
    if (firstOption) firstOption.required = !selected;
    return Boolean(selected);
};

const validateTaxonomyRequirements = scope => {
    let valid = true;
    scope.querySelectorAll('[data-taxonomy-group]').forEach(group => {
        const selected = syncTaxonomyRequirement(group);
        const error = group.querySelector('[data-taxonomy-error]');
        if (error) error.hidden = selected;
        valid = selected && valid;
    });
    return valid;
};

const initializeTaxonomyRequirements = () => {
    document.querySelectorAll('[data-taxonomy-group]').forEach(group => {
        syncTaxonomyRequirement(group);
        group.querySelectorAll('input[type="checkbox"]').forEach(option => {
            option.addEventListener('change', () => syncTaxonomyRequirement(group));
            option.addEventListener('invalid', () => { group.querySelector('[data-taxonomy-error]')?.removeAttribute('hidden'); });
        });
    });
};

if (document.querySelector('[data-address-selector]')) {
    void import('../css/restaurant-submission.css');
    initializeAddressSelectors();
}

initializeRestaurantHoursEditors();
initializeManagedRestaurantMedia();
initializeRestaurantPhotoPickers();
initializeTaxonomyRequirements();

if (submission) {
    void import('../css/restaurant-submission.css');
    const form = submission.querySelector('form');
    const steps = [...submission.querySelectorAll('[data-submission-step]')];
    const currentStepInput = form.querySelector('[data-current-step-input]');
    const currentStepLabel = submission.querySelector('[data-current-step]');
    const progressBar = submission.querySelector('[data-progress-bar]');
    const finalSubmit = form.querySelector('[data-final-submit]');
    const nameInput = form.querySelector('[data-restaurant-name]');
    const halalOptions = [...form.querySelectorAll('[data-halal-option]')];
    const halalError = form.querySelector('[data-halal-error]');
    const addressSelector = form.querySelector('[data-address-selector]');
    const addressQuery = addressSelector.querySelector('[data-address-query]');
    const addressToken = form.querySelector('[data-address-token]');
    const latitude = form.querySelector('[data-latitude]');
    const longitude = form.querySelector('[data-longitude]');
    const nameDuplicates = form.querySelector('[data-name-duplicates]');
    const addressDuplicates = form.querySelector('[data-address-duplicates]');
    const coverInput = form.querySelector('[data-cover-input]');
    const ownerFields = form.querySelector('[data-owner-fields]');
    const ownerSiret = form.querySelector('[data-owner-siret]');
    const ownerSiretError = form.querySelector('[data-owner-siret-error]');
    const ownerChoices = [...form.querySelectorAll('[data-owner-choice]')];
    const ownerEmailHelp = [...form.querySelectorAll('[data-owner-email-help]')];
    const syncOwnerChoice = () => {
        const selectedRole = form.querySelector('[name="submitter_role"]:checked')?.value;
        const isOwner = selectedRole === 'owner';
        if (ownerFields) {
            ownerFields.hidden = !isOwner;
            ownerFields.querySelectorAll('input').forEach(field => { field.required = isOwner; });
        }
        if (!isOwner && ownerSiret) ownerSiret.setCustomValidity('');
        if (!isOwner && ownerSiretError) ownerSiretError.hidden = true;
        ownerChoices.forEach(input => input.closest('.choice-card')?.classList.toggle('is-selected', input.checked));
        ownerEmailHelp.forEach(help => { help.hidden = help.dataset.ownerEmailHelp !== (isOwner ? 'owner' : 'customer'); });
    };
    ownerChoices.forEach(input => input.addEventListener('change', syncOwnerChoice));
    let currentStep = Number.parseInt(submission.dataset.initialStep || '1', 10) || 1;
    let nameTimer;

    const text = (element, value) => { element.textContent = value || 'Non renseigné'; return element; };
    const addressValue = name => addressSelector.querySelector(`[data-address-display="${name}"]`)?.value?.trim() || '';
    const validSiret = value => {
        const digits = value.replace(/\D/g, '');
        if (! /^\d{14}$/.test(digits)) return false;
        return [...digits].reduce((sum, digit, index) => {
            let number = Number(digit);
            if (index % 2 === 0) { number *= 2; if (number > 9) number -= 9; }
            return sum + number;
        }, 0) % 10 === 0;
    };
    const syncSiretValidation = (showError = false) => {
        if (! ownerSiret) return true;
        const isOwner = form.querySelector('[name="submitter_role"]:checked')?.value === 'owner';
        const invalid = isOwner && ownerSiret.value !== '' && !validSiret(ownerSiret.value);
        const message = 'Le SIRET doit comporter 14 chiffres valides.';
        ownerSiret.setCustomValidity(invalid ? message : '');
        if (ownerSiretError) {
            ownerSiretError.textContent = invalid ? message : '';
            ownerSiretError.hidden = !invalid || !showError;
        }
        return !invalid;
    };

    const setStep = (step, focus = true) => {
        currentStep = Math.max(1, Math.min(5, step));
        currentStepInput.value = String(currentStep);
        currentStepLabel.textContent = String(currentStep);
        progressBar.style.width = `${currentStep * 20}%`;
        steps.forEach(section => { section.hidden = Number(section.dataset.submissionStep) !== currentStep; });
        if (currentStep === 2) addressSelector.dispatchEvent(new CustomEvent('address-selector-visible'));
        submission.querySelectorAll('[data-step-indicator]').forEach(indicator => {
            const active = Number(indicator.dataset.stepIndicator) === currentStep;
            indicator.classList.toggle('is-current', active);
            indicator.classList.toggle('is-complete', Number(indicator.dataset.stepIndicator) < currentStep);
            if (active) indicator.setAttribute('aria-current', 'step'); else indicator.removeAttribute('aria-current');
        });
        finalSubmit.hidden = currentStep !== 5;
        if (focus) steps[currentStep - 1].querySelector('h2')?.focus({ preventScroll: true });
        window.scrollTo({ top: submission.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
    };

    const validateHalal = () => {
        const valid = halalOptions.some(option => option.checked);
        halalError.hidden = valid;
        return valid;
    };

    const validateTaxonomy = () => {
        return validateTaxonomyRequirements(form);
    };

    const validateStep = step => {
        const section = steps[step - 1];
        if (step === 5) syncSiretValidation(true);
        if (step === 4 && !coverInput.files.length) {
            coverInput.setCustomValidity('Ajoutez une photo de couverture.');
            coverInput.reportValidity();
            coverInput.setCustomValidity('');
            return false;
        }
        if (section.querySelector('[data-taxonomy-group]') && !validateTaxonomy()) return false;
        if (section.querySelector('[data-hours-editor]') && !validateRestaurantHoursEditors(section)) return false;
        for (const field of section.querySelectorAll('input, select, textarea')) {
            if (field.type === 'hidden' || field.disabled || !field.willValidate) continue;
            if (!field.reportValidity()) return false;
        }
        if (step === 1 && !validateHalal()) return false;
        if (step === 2) {
            if (!addressToken.value) {
                addressQuery.setCustomValidity('Sélectionnez une adresse dans la liste proposée.');
                addressQuery.reportValidity();
                addressQuery.setCustomValidity('');
                return false;
            }
        }
        return true;
    };

    const renderDuplicates = (target, candidates, detailed) => {
        target.replaceChildren();
        if (!candidates.length) return;
        const heading = document.createElement('h3');
        text(heading, detailed ? 'Une fiche très proche existe peut-être déjà' : 'Des noms proches ont été trouvés');
        const introduction = document.createElement('p');
        text(introduction, detailed ? 'Vérifiez cette fiche avant d’envoyer une nouvelle proposition.' : 'Vous pouvez vérifier ces fiches avant de continuer.');
        const list = document.createElement('ul');
        candidates.forEach(candidate => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = candidate.url;
            text(link, `${candidate.name}${candidate.city ? ` — ${candidate.city}` : ''}`);
            if (candidate.claim_url) {
                const claim = document.createElement('a');
                claim.href = candidate.claim_url;
                text(claim, 'Revendiquer');
                item.append(link, document.createTextNode(' · '), claim);
            } else item.append(link);
            list.append(item);
        });
        target.append(heading, introduction, list);
    };

    const refreshDuplicates = async (target, detailed = false) => {
        if (nameInput.value.trim().length < 2) { target.replaceChildren(); return; }
        const params = new URLSearchParams({ name: nameInput.value.trim() });
        if (detailed) {
            ['address_line1', 'city_name'].forEach(field => { if (addressValue(field)) params.set(field, addressValue(field)); });
            if (latitude.value && longitude.value) { params.set('latitude', latitude.value); params.set('longitude', longitude.value); }
        }
        try {
            const response = await fetch(`${submission.dataset.duplicatesEndpoint}?${params.toString()}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            renderDuplicates(target, payload.data || [], detailed);
        } catch (_) {
            // A failed informational lookup never prevents a contribution.
        }
    };

    form.querySelectorAll('[data-next], [data-previous]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            if (button.hasAttribute('data-previous')) { setStep(currentStep - 1); return; }
            if (button.closest('[data-submission-step]')?.querySelector('[data-taxonomy-group]') && !validateTaxonomy()) return;
            if (!validateStep(currentStep)) return;
            if (currentStep === 1) await refreshDuplicates(nameDuplicates, false);
            if (currentStep === 2) await refreshDuplicates(addressDuplicates, true);
            setStep(currentStep + 1);
        });
    });
    halalOptions.forEach(option => option.addEventListener('change', validateHalal));
    nameInput.addEventListener('input', () => { clearTimeout(nameTimer); nameTimer = setTimeout(() => refreshDuplicates(nameDuplicates, false), 350); });
    addressSelector.addEventListener('address-selected', () => refreshDuplicates(addressDuplicates, true));
    addressSelector.addEventListener('address-marker-moved', () => refreshDuplicates(addressDuplicates, true));
    ownerSiret?.addEventListener('blur', () => syncSiretValidation(true));
    ownerSiret?.addEventListener('input', () => syncSiretValidation(!ownerSiretError?.hidden));
    form.addEventListener('submit', event => { if (!validateStep(5)) event.preventDefault(); });
    syncOwnerChoice();
    setStep(currentStep, false);
}
menu?.addEventListener('click', () => { const open = menu.getAttribute('aria-expanded') === 'true'; menu.setAttribute('aria-expanded', String(!open)); mobileNav.hidden = open; });

let lastSubmenuToggle = null;
document.querySelectorAll('[data-submenu-toggle]').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) return;
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open)); panel.hidden = open; lastSubmenuToggle = toggle;
    });
});
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-submenu-toggle][aria-expanded="true"]').forEach(toggle => {
        document.getElementById(toggle.getAttribute('aria-controls'))?.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
    });
    if (lastSubmenuToggle) { lastSubmenuToggle.focus(); lastSubmenuToggle = null; }
    if (menu?.getAttribute('aria-expanded') === 'true') { menu.setAttribute('aria-expanded', 'false'); mobileNav.hidden = true; menu.focus(); }
});

document.querySelectorAll('[data-restaurant-search]').forEach(form => {
    const location = form.querySelector('[data-location-input]'); const cityValue = form.querySelector('[data-location-value]');
    const query = form.querySelector('[data-query-input]'); const category = form.querySelector('[data-category-input]');
    const cities = form.querySelector('[data-cities-list]'); const suggestions = form.querySelector('[data-suggestions-list]'); const message = form.querySelector('[data-search-message]');
    let cityTimer; let queryTimer; let selectedRestaurant = null; let active = -1;
    const buttons = container => [...container.querySelectorAll('button:not([disabled])')];
    const close = container => { container.hidden = true; active = -1; };
    const chooseCity = (name, slug) => { location.value = name; cityValue.value = slug; close(cities); };
    const showMessage = text => { message.textContent = text; message.hidden = false; location.focus(); };
    const cityButton = city => { const button = document.createElement('button'); button.type = 'button'; button.role = 'option'; button.textContent = city.name; button.dataset.cityName = city.name; button.dataset.citySlug = city.slug; return button; };
    const loadCities = async (term = '') => {
        try { const response = await fetch(`${form.dataset.citiesUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } }); if (!response.ok) return; const data = await response.json(); cities.querySelectorAll('[data-city-name]').forEach(el => el.remove()); data.cities.forEach(city => cities.append(cityButton(city))); cities.hidden = false; location.setAttribute('aria-expanded', 'true'); } catch (_) { /* Native form submission remains available. */ }
    };
    const renderSuggestions = data => {
        suggestions.replaceChildren(); selectedRestaurant = null; category.disabled = true;
        if (data.specialties.length) { const heading = document.createElement('p'); heading.className = 'search-group-label'; heading.textContent = 'Spécialités'; suggestions.append(heading); data.specialties.forEach(item => { const button = document.createElement('button'); button.type = 'button'; button.role = 'option'; button.textContent = item.name; button.dataset.category = item.slug; suggestions.append(button); }); }
        if (data.restaurants.length) { const heading = document.createElement('p'); heading.className = 'search-group-label'; heading.textContent = 'Restaurants'; suggestions.append(heading); data.restaurants.forEach(item => { const button = document.createElement('button'); button.type = 'button'; button.role = 'option'; button.dataset.restaurant = item.slug; button.textContent = item.name; if (item.city_name) { const city = document.createElement('small'); city.textContent = item.city_name; button.append(city); } suggestions.append(button); }); }
        suggestions.hidden = !data.specialties.length && !data.restaurants.length; query.setAttribute('aria-expanded', String(!suggestions.hidden));
    };
    const loadSuggestions = async () => {
        if (query.value.trim().length < 2) { close(suggestions); return; }
        try { const response = await fetch(`${form.dataset.suggestionsUrl}?q=${encodeURIComponent(query.value)}&ville=${encodeURIComponent(cityValue.value)}`, { headers: { Accept: 'application/json' } }); if (response.ok) renderSuggestions(await response.json()); } catch (_) { /* Search remains a regular GET form. */ }
    };
    location.addEventListener('focus', () => loadCities()); location.addEventListener('input', () => { clearTimeout(cityTimer); cityTimer = setTimeout(() => loadCities(location.value), 180); });
    query.addEventListener('focus', () => close(cities));
    document.addEventListener('pointerdown', event => { if (!form.contains(event.target)) close(cities); });
    cities.addEventListener('click', event => { const button = event.target.closest('button'); if (!button) return; if (button.matches('[data-near-me]')) { if (!navigator.geolocation) return showMessage('Impossible d’obtenir votre position. Choisissez une ville.'); button.disabled = true; navigator.geolocation.getCurrentPosition(({ coords }) => { const params = new URLSearchParams(new FormData(form)); params.delete('ville'); params.set('lat', coords.latitude); params.set('lng', coords.longitude); window.location.assign(`${form.action.replace('/recherche', '')}?${params.toString()}`); }, () => { button.disabled = false; showMessage('Impossible d’obtenir votre position. Choisissez une ville.'); }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }); return; } chooseCity(button.dataset.cityName, button.dataset.citySlug); });
    query.addEventListener('input', () => { selectedRestaurant = null; category.disabled = true; clearTimeout(queryTimer); queryTimer = setTimeout(loadSuggestions, 220); });
    suggestions.addEventListener('click', event => { const button = event.target.closest('button'); if (!button) return; if (button.dataset.category) { category.value = button.dataset.category; category.disabled = false; query.value = button.textContent; close(suggestions); return; } if (button.dataset.restaurant) { selectedRestaurant = button.dataset.restaurant; query.value = button.childNodes[0].textContent; close(suggestions); } });
    [location, query].forEach(input => input.addEventListener('keydown', event => { const container = input === location ? cities : suggestions; const options = buttons(container); if (event.key === 'Escape') { close(container); return; } if (!options.length || container.hidden) return; if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length; options[active].focus(); } }));
    form.addEventListener('submit', event => { if (selectedRestaurant) { event.preventDefault(); window.location.assign(`/resto/${encodeURIComponent(selectedRestaurant)}`); } });
});

document.querySelectorAll('[data-near-me-cta]').forEach(link => {
    link.addEventListener('click', event => {
        if (!navigator.geolocation) return;
        event.preventDefault();
        link.setAttribute('aria-busy', 'true');
        navigator.geolocation.getCurrentPosition(({ coords }) => {
            const url = new URL(link.href);
            url.searchParams.set('lat', coords.latitude.toFixed(5));
            url.searchParams.set('lng', coords.longitude.toFixed(5));
            url.hash = '';
            window.location.assign(url);
        }, () => {
            link.removeAttribute('aria-busy');
            window.location.assign(link.href);
        }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
    });
});
