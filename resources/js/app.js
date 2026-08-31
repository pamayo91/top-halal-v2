const menu = document.querySelector('.menu-toggle');
const mobileNav = document.querySelector('#mobile-nav');

const submission = document.querySelector('[data-restaurant-submission]');

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
    const addressQuery = form.querySelector('[data-address-query]');
    const addressResults = form.querySelector('[data-address-results]');
    const addressToken = form.querySelector('[data-address-token]');
    const selectedAddress = form.querySelector('[data-address-selected]');
    const manualAddress = form.querySelector('[data-manual-address]');
    const addressFields = [...form.querySelectorAll('[data-address-field]')];
    const latitude = form.querySelector('[data-latitude]');
    const longitude = form.querySelector('[data-longitude]');
    const mapMoved = form.querySelector('[data-map-moved]');
    const mapContainer = form.querySelector('[data-submission-map]');
    const mapHelp = form.querySelector('[data-map-help]');
    const nameDuplicates = form.querySelector('[data-name-duplicates]');
    const addressDuplicates = form.querySelector('[data-address-duplicates]');
    const coverInput = form.querySelector('[data-cover-input]');
    const coverPreview = form.querySelector('[data-cover-preview]');
    const galleryInput = form.querySelector('[data-gallery-input]');
    const galleryPreview = form.querySelector('[data-gallery-preview]');
    const summary = form.querySelector('[data-submission-summary]');
    let currentStep = Number.parseInt(submission.dataset.initialStep || '1', 10) || 1;
    let addressTimer;
    let nameTimer;
    let map;
    let marker;
    let leaflet;
    let addressApplying = false;
    let galleryFiles = [];
    let coverUrl;
    const galleryUrls = new WeakMap();

    const text = (element, value) => { element.textContent = value || 'Non renseigné'; return element; };
    const inputValue = name => form.querySelector(`[name="${name}"]`)?.value?.trim() || '';
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
        if (currentStep === 2 && latitude.value && longitude.value) showMap(Number(latitude.value), Number(longitude.value));
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
            const hasManualAddress = ['address_line1', 'postal_code', 'city_name'].every(field => inputValue(field));
            if (!addressToken.value && !hasManualAddress) {
                const first = form.querySelector('[name="address_line1"]');
                manualAddress.open = true;
                first.setCustomValidity('Sélectionnez une adresse ou complétez l’adresse manuellement.');
                first.reportValidity();
                first.setCustomValidity('');
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
            ['address_line1', 'city_name'].forEach(field => { if (inputValue(field)) params.set(field, inputValue(field)); });
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

    const applyAddress = address => {
        addressApplying = true;
        Object.entries(address).forEach(([field, value]) => {
            const input = form.querySelector(`[data-address-field="${field}"]`);
            if (input && value !== null && value !== undefined) input.value = value;
        });
        latitude.value = address.latitude ?? '';
        longitude.value = address.longitude ?? '';
        mapMoved.value = '0';
        addressApplying = false;
        selectedAddress.hidden = false;
        manualAddress.open = false;
        if (latitude.value && longitude.value) showMap(Number(latitude.value), Number(longitude.value));
        refreshDuplicates(addressDuplicates, true);
    };

    const showAddressResults = data => {
        addressResults.replaceChildren();
        if (!data.length) {
            if (addressQuery.value.trim().length >= 3) text(addressResults.appendChild(document.createElement('p')), 'Aucune adresse trouvée. Vous pouvez la renseigner manuellement.');
            return;
        }
        const list = document.createElement('ul');
        data.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            text(button, item.label);
            button.addEventListener('click', () => {
                addressToken.value = item.token;
                addressQuery.value = item.label;
                addressResults.replaceChildren();
                applyAddress(item.address || {});
            });
            const row = document.createElement('li');
            row.append(button);
            list.append(row);
        });
        addressResults.append(list);
    };

    const loadLeaflet = async () => {
        if (leaflet) return leaflet;
        const module = await import('leaflet');
        await import('leaflet/dist/leaflet.css');
        leaflet = module.default || module;
        return leaflet;
    };

    const showMap = async (lat, lng) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        try {
            const L = await loadLeaflet();
            if (!map) {
                map = L.map(mapContainer, { scrollWheelZoom: false }).setView([lat, lng], 16);
                L.tileLayer(mapContainer.dataset.tileUrl, { maxZoom: 19, attribution: mapContainer.dataset.tileAttribution }).addTo(map);
                marker = L.marker([lat, lng], {
                    draggable: true,
                    icon: L.divIcon({ className: 'submission-map-marker', html: '<span aria-hidden="true">⌖</span>', iconSize: [34, 34], iconAnchor: [17, 17] }),
                }).addTo(map);
                marker.on('dragend', () => {
                    const position = marker.getLatLng();
                    latitude.value = position.lat.toFixed(7);
                    longitude.value = position.lng.toFixed(7);
                    mapMoved.value = '1';
                    text(mapHelp, 'Position ajustée manuellement. Elle sera vérifiée par la modération.');
                    refreshDuplicates(addressDuplicates, true);
                });
            } else {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);
            }
            requestAnimationFrame(() => map.invalidateSize());
            text(mapHelp, 'Déplacez le marqueur si la position doit être affinée. Cette correction sera vérifiée par la modération.');
        } catch (_) {
            text(mapHelp, 'La carte est indisponible pour le moment. L’adresse reste utilisable et sera vérifiée par la modération.');
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
        add('Adresse', [inputValue('address_line1'), inputValue('postal_code'), inputValue('city_name')].filter(Boolean).join(', '));
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
    addressQuery.addEventListener('input', () => {
        clearTimeout(addressTimer);
        const query = addressQuery.value.trim();
        if (query.length < 3) { addressResults.replaceChildren(); return; }
        addressTimer = setTimeout(async () => {
            try {
                const response = await fetch(`${submission.dataset.addressEndpoint}?${new URLSearchParams({ q: query })}`, { headers: { Accept: 'application/json' } });
                if (response.ok) showAddressResults((await response.json()).data || []);
            } catch (_) { showAddressResults([]); }
        }, 250);
    });
    addressFields.forEach(field => field.addEventListener('input', () => {
        if (addressApplying) return;
        addressToken.value = '';
        selectedAddress.hidden = true;
        manualAddress.open = true;
    }));
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
document.querySelector('#near-me')?.addEventListener('click', () => { const button = document.querySelector('#near-me'); if (!navigator.geolocation) { button.textContent = 'Position non disponible'; return; } button.disabled = true; button.textContent = 'Localisation…'; navigator.geolocation.getCurrentPosition(({coords}) => { const form = document.querySelector('#near-me-form'); form.querySelector('[name=lat]').value = coords.latitude; form.querySelector('[name=lng]').value = coords.longitude; form.submit(); }, () => { button.disabled = false; button.textContent = 'Position indisponible'; }, {enableHighAccuracy:false, timeout:8000, maximumAge:300000}); });
