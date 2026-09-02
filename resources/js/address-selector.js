let leaflet;

const loadLeaflet = async () => {
    if (leaflet) return leaflet;
    const module = await import('leaflet');
    await import('leaflet/dist/leaflet.css');
    leaflet = module.default || module;
    return leaflet;
};

export const initializeAddressSelectors = () => document.querySelectorAll('[data-address-selector]').forEach(container => {
    if (container.dataset.initialized) return;
    container.dataset.initialized = '1';

    const query = container.querySelector('[data-address-query]');
    const results = container.querySelector('[data-address-results]');
    const token = container.querySelector('[data-address-token]');
    const selected = container.querySelector('[data-address-selected]');
    const fields = container.querySelector('[data-address-fields]');
    const latitude = container.querySelector('[data-latitude]');
    const longitude = container.querySelector('[data-longitude]');
    const mapMoved = container.querySelector('[data-map-moved]');
    const locationChanged = container.closest('form')?.querySelector('[data-location-changed]');
    const mapContainer = container.querySelector('[data-address-map]');
    const mapHelp = container.querySelector('[data-map-help]');
    let timer;
    let map;
    let marker;

    const clearSelection = () => {
        token.value = '';
        selected.hidden = true;
        fields.hidden = true;
    };
    const showMap = async (lat, lng) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        try {
            const L = await loadLeaflet();
            if (!map) {
                map = L.map(mapContainer, { scrollWheelZoom: false }).setView([lat, lng], 16);
                L.tileLayer(mapContainer.dataset.tileUrl, { maxZoom: 19, attribution: mapContainer.dataset.tileAttribution }).addTo(map);
                marker = L.marker([lat, lng], { draggable: true, icon: L.divIcon({ className: 'submission-map-marker', html: '<span aria-hidden="true">⌖</span>', iconSize: [34, 34], iconAnchor: [17, 17] }) }).addTo(map);
                marker.on('dragend', () => {
                    const point = marker.getLatLng();
                    latitude.value = point.lat.toFixed(7);
                    longitude.value = point.lng.toFixed(7);
                    mapMoved.value = '1';
                    if (locationChanged) locationChanged.value = '1';
                    mapHelp.textContent = 'Position ajustée manuellement. Seules les coordonnées seront modifiées.';
                    container.dispatchEvent(new CustomEvent('address-marker-moved', { bubbles: true }));
                });
            } else {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 16);
            }
            requestAnimationFrame(() => map.invalidateSize());
            if (mapMoved.value !== '1') mapHelp.textContent = 'Déplacez le marqueur si la position doit être affinée. L’adresse sélectionnée ne sera pas modifiée.';
        } catch (_) {
            mapHelp.textContent = 'La carte est indisponible pour le moment. L’adresse sélectionnée reste utilisable.';
        }
    };
    const applyAddress = address => {
        ['address_line1', 'postal_code', 'city_name'].forEach(field => {
            const input = container.querySelector(`[data-address-display="${field}"]`);
            if (input) input.value = address[field] || '';
        });
        latitude.value = address.latitude ?? '';
        longitude.value = address.longitude ?? '';
        mapMoved.value = '0';
        if (locationChanged) locationChanged.value = '1';
        selected.hidden = false;
        fields.hidden = false;
        void showMap(Number(latitude.value), Number(longitude.value));
        container.dispatchEvent(new CustomEvent('address-selected', { bubbles: true }));
    };
    const renderResults = data => {
        results.replaceChildren();
        if (!data.length) {
            if (query.value.trim().length >= 3) results.textContent = 'Aucune adresse trouvée. Essayez une adresse voisine, puis ajustez le marqueur.';
            return;
        }
        const list = document.createElement('ul');
        data.forEach(item => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = item.label;
            button.addEventListener('click', () => {
                token.value = item.token;
                query.value = item.label;
                results.replaceChildren();
                applyAddress(item.address || {});
            });
            const row = document.createElement('li');
            row.append(button);
            list.append(row);
        });
        results.append(list);
    };

    query.addEventListener('input', () => {
        clearSelection();
        clearTimeout(timer);
        const value = query.value.trim();
        if (value.length < 3) {
            results.replaceChildren();
            return;
        }
        timer = setTimeout(async () => {
            try {
                const response = await fetch(`${container.dataset.addressEndpoint}?${new URLSearchParams({ q: value })}`, { headers: { Accept: 'application/json' } });
                if (response.ok) renderResults((await response.json()).data || []);
            } catch (_) { renderResults([]); }
        }, 250);
    });

    if (latitude.value && longitude.value) void showMap(Number(latitude.value), Number(longitude.value));
});
