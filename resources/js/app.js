import { initializeAddressSelectors } from './address-selector';

const menu = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('#mobile-nav');

const submission = document.querySelector('[data-restaurant-submission]');

if (document.querySelector('[data-address-selector]')) {
    void import('../css/restaurant-submission.css');
    initializeAddressSelectors();
}

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
    const coverPreview = form.querySelector('[data-cover-preview]');
    const galleryInput = form.querySelector('[data-gallery-input]');
    const galleryPreview = form.querySelector('[data-gallery-preview]');
    const summary = form.querySelector('[data-submission-summary]');
    let currentStep = Number.parseInt(submission.dataset.initialStep || '1', 10) || 1;
    let nameTimer;
    let galleryFiles = [];
    let coverUrl;
    const galleryUrls = new WeakMap();

    const text = (element, value) => { element.textContent = value || 'Non renseigné'; return element; };
    const inputValue = name => form.querySelector(`[name="${name}"]`)?.value?.trim() || '';
    const addressValue = name => addressSelector.querySelector(`[data-address-display="${name}"]`)?.value?.trim() || '';
    const selectedLabels = name => [...form.querySelectorAll(`[name="${name}[]"]:checked`)].map(input => input.parentElement.textContent.trim());

    const setStep = (step, focus = true) => {
        currentStep = Math.max(1, Math.min(5, step));
        currentStepInput.value = String(currentStep);
        currentStepLabel.textContent = String(currentStep);
        progressBar.style.width = `${currentStep * 20}%`;
        steps.forEach(section => { section.hidden = Number(section.dataset.submissionStep) !== currentStep; });
        submission.querySelectorAll('[data-step-indicator]').forEach(indicator => {
            const active = Number(indicator.dataset.stepIndicator) === currentStep;
            indicator.classList.toggle('is-current', active);
            indicator.classList.toggle('is-complete', Number(indicator.dataset.stepIndicator) < currentStep);
            if (active) indicator.setAttribute('aria-current', 'step'); else indicator.removeAttribute('aria-current');
        });
        finalSubmit.hidden = currentStep !== 5;
        if (currentStep === 5) renderSummary();
        if (focus) steps[currentStep - 1].querySelector('h2')?.focus({ preventScroll: true });
        window.scrollTo({ top: submission.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
    };

    const validateHalal = () => {
        const valid = halalOptions.some(option => option.checked);
        halalError.hidden = valid;
        return valid;
    };

    const validateStep = step => {
        const section = steps[step - 1];
        if (step === 4 && !coverInput.files.length) {
            coverInput.setCustomValidity('Ajoutez une photo de couverture.');
            coverInput.reportValidity();
            coverInput.setCustomValidity('');
            return false;
        }
        for (const field of section.querySelectorAll('input, select, textarea')) {
            if (field.type === 'hidden' || field.disabled || !field.willValidate) continue;
            if (!field.reportValidity()) return false;
        }
        if (step === 1 && !validateHalal()) return false;
        if (step === 2) {
            if (!addressToken.value) {
                addressQuery.setCustomValidity('Sélectionnez une adresse proposée par la Géoplateforme.');
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
            const claim = document.createElement('a');
            claim.href = candidate.claim_url;
            text(claim, 'Revendiquer');
            item.append(link, document.createTextNode(' · '), claim);
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


    const updateHoursVisibility = day => {
        const row = form.querySelector(`[data-hours-day="${day}"]`);
        row.querySelector('[data-hours-slots]').hidden = row.querySelector('[data-hours-status]').value !== 'slots';
    };

    const renderCover = () => {
        if (coverUrl) URL.revokeObjectURL(coverUrl);
        coverPreview.replaceChildren();
        const file = coverInput.files[0];
        if (!file) return;
        coverUrl = URL.createObjectURL(file);
        const image = document.createElement('img');
        image.src = coverUrl;
        image.alt = 'Aperçu de la photo de couverture';
        coverPreview.append(image);
    };

    const galleryUrl = file => {
        if (!galleryUrls.has(file)) galleryUrls.set(file, URL.createObjectURL(file));
        return galleryUrls.get(file);
    };

    const syncGalleryInput = () => {
        const transfer = new DataTransfer();
        galleryFiles.forEach(file => transfer.items.add(file));
        galleryInput.files = transfer.files;
    };

    const renderGallery = () => {
        galleryPreview.replaceChildren();
        galleryFiles.forEach((file, index) => {
            const row = document.createElement('li');
            const image = document.createElement('img');
            image.src = galleryUrl(file);
            image.alt = `Aperçu de la photo ${index + 1}`;
            const title = document.createElement('span');
            text(title, file.name);
            const up = document.createElement('button'); up.type = 'button'; text(up, 'Monter'); up.disabled = index === 0;
            up.addEventListener('click', () => { [galleryFiles[index - 1], galleryFiles[index]] = [galleryFiles[index], galleryFiles[index - 1]]; syncGalleryInput(); renderGallery(); });
            const down = document.createElement('button'); down.type = 'button'; text(down, 'Descendre'); down.disabled = index === galleryFiles.length - 1;
            down.addEventListener('click', () => { [galleryFiles[index], galleryFiles[index + 1]] = [galleryFiles[index + 1], galleryFiles[index]]; syncGalleryInput(); renderGallery(); });
            const remove = document.createElement('button'); remove.type = 'button'; text(remove, 'Supprimer');
            remove.addEventListener('click', () => { const [removed] = galleryFiles.splice(index, 1); URL.revokeObjectURL(galleryUrls.get(removed)); syncGalleryInput(); renderGallery(); });
            row.append(image, title, up, down, remove);
            galleryPreview.append(row);
        });
    };

    const renderSummary = () => {
        summary.replaceChildren();
        const list = document.createElement('dl');
        const add = (label, value) => { const term = document.createElement('dt'); text(term, label); const definition = document.createElement('dd'); text(definition, value); list.append(term, definition); };
        add('Restaurant', nameInput.value.trim());
        add('Offre halal', halalOptions.filter(option => option.checked).map(option => option.parentElement.textContent.trim().split('\n')[0]).join(', '));
        add('Adresse', [addressValue('address_line1'), addressValue('postal_code'), addressValue('city_name')].filter(Boolean).join(', '));
        add('Catégories', selectedLabels('categories').join(', '));
        add('Services', selectedLabels('features').join(', '));
        add('Photos', `1 couverture${galleryFiles.length ? ` + ${galleryFiles.length} galerie` : ''}`);
        summary.append(list);
    };

    form.querySelectorAll('[data-next], [data-previous]').forEach(button => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            if (button.hasAttribute('data-previous')) { setStep(currentStep - 1); return; }
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
    form.querySelectorAll('[data-hours-status]').forEach(select => select.addEventListener('change', () => updateHoursVisibility(select.closest('[data-hours-day]').dataset.hoursDay)));
    form.querySelector('[data-copy-hours]').addEventListener('click', () => {
        const source = form.querySelector(`[data-hours-day="${form.querySelector('[data-copy-source]').value}"]`);
        form.querySelectorAll('[data-copy-target]:checked').forEach(target => {
            const row = form.querySelector(`[data-hours-day="${target.value}"]`);
            row.querySelector('[data-hours-status]').value = source.querySelector('[data-hours-status]').value;
            row.querySelectorAll('input[type="time"]').forEach((input, index) => { input.value = source.querySelectorAll('input[type="time"]')[index].value; });
            updateHoursVisibility(target.value);
        });
    });
    coverInput.addEventListener('change', renderCover);
    galleryInput.addEventListener('change', () => { galleryFiles = [...galleryFiles, ...galleryInput.files].slice(0, 10); syncGalleryInput(); renderGallery(); });
    form.addEventListener('submit', event => { if (!validateStep(5)) event.preventDefault(); });
    setStep(currentStep, false);
}
menu?.addEventListener('click', () => { const open = menu.getAttribute('aria-expanded') === 'true'; menu.setAttribute('aria-expanded', String(!open)); mobileNav.hidden = open; });

document.querySelectorAll('[data-restaurant-search]').forEach(form => {
    const location = form.querySelector('[data-location-input]'); const cityValue = form.querySelector('[data-location-value]');
    const query = form.querySelector('[data-query-input]'); const category = form.querySelector('[data-category-input]');
    const cities = form.querySelector('[data-cities-list]'); const suggestions = form.querySelector('[data-suggestions-list]'); const message = form.querySelector('[data-search-message]');
    let cityTimer; let queryTimer; let selectedRestaurant = null; let active = -1;
    const buttons = container => [...container.querySelectorAll('button:not([disabled])')];
    const close = container => { container.hidden = true; active = -1; };
    const chooseCity = (name, slug) => { location.value = name; cityValue.value = slug; close(cities); };
    const showMessage = text => { message.textContent = text; message.hidden = false; location.focus(); };
    const cityButton = city => { const button = document.createElement('button'); button.type = 'button'; button.role = 'option'; button.textContent = `${city.name}, France`; button.dataset.cityName = city.name; button.dataset.citySlug = city.slug; return button; };
    const loadCities = async () => {
        try { const response = await fetch(`${form.dataset.citiesUrl}?q=${encodeURIComponent(location.value)}`, { headers: { Accept: 'application/json' } }); if (!response.ok) return; const data = await response.json(); cities.querySelectorAll('[data-city-name]').forEach(el => el.remove()); data.cities.forEach(city => cities.append(cityButton(city))); cities.hidden = false; location.setAttribute('aria-expanded', 'true'); } catch (_) { /* Native form submission remains available. */ }
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
    location.addEventListener('focus', loadCities); location.addEventListener('input', () => { clearTimeout(cityTimer); cityTimer = setTimeout(loadCities, 180); });
    query.addEventListener('focus', () => close(cities));
    document.addEventListener('pointerdown', event => { if (!form.contains(event.target)) close(cities); });
    cities.addEventListener('click', event => { const button = event.target.closest('button'); if (!button) return; if (button.matches('[data-near-me]')) { if (!navigator.geolocation) return showMessage('Impossible d’obtenir votre position. Choisissez une ville.'); button.disabled = true; navigator.geolocation.getCurrentPosition(({ coords }) => { const params = new URLSearchParams(new FormData(form)); params.delete('ville'); params.set('lat', coords.latitude); params.set('lng', coords.longitude); window.location.assign(`${form.action.replace('/recherche', '')}?${params.toString()}`); }, () => { button.disabled = false; showMessage('Impossible d’obtenir votre position. Choisissez une ville.'); }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 }); return; } chooseCity(button.dataset.cityName, button.dataset.citySlug); });
    query.addEventListener('input', () => { selectedRestaurant = null; category.disabled = true; clearTimeout(queryTimer); queryTimer = setTimeout(loadSuggestions, 220); });
    suggestions.addEventListener('click', event => { const button = event.target.closest('button'); if (!button) return; if (button.dataset.category) { category.value = button.dataset.category; category.disabled = false; query.value = button.textContent; close(suggestions); return; } if (button.dataset.restaurant) { selectedRestaurant = button.dataset.restaurant; query.value = button.childNodes[0].textContent; close(suggestions); } });
    [location, query].forEach(input => input.addEventListener('keydown', event => { const container = input === location ? cities : suggestions; const options = buttons(container); if (event.key === 'Escape') { close(container); return; } if (!options.length || container.hidden) return; if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length; options[active].focus(); } }));
    form.addEventListener('submit', event => { if (selectedRestaurant) { event.preventDefault(); window.location.assign(`/resto/${encodeURIComponent(selectedRestaurant)}`); } });
});
