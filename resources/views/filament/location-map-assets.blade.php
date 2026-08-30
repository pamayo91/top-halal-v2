<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    window.topHalalInitLocationMaps = () => document.querySelectorAll('[data-top-halal-location-map]').forEach((container) => {
        if (!window.L || !container.offsetParent) return;
        if (container.dataset.initialized) {
            container._topHalalMap?.invalidateSize();
            return;
        }
        const latField = document.getElementById('location-latitude'), lngField = document.getElementById('location-longitude');
        if (!latField || !lngField) return;
        const hasCoordinates = latField.value !== '' && lngField.value !== '';
        const attempts = Number(container.dataset.mapAttempts || '0');
        if (!hasCoordinates && attempts < 20) {
            container.dataset.mapAttempts = String(attempts + 1);
            window.setTimeout(window.topHalalInitLocationMaps, 100);
            return;
        }
        container.dataset.initialized = '1';
        const lat = hasCoordinates ? Number(latField.value) : 46.2276, lng = hasCoordinates ? Number(lngField.value) : 2.2137;
        const map = L.map(container.querySelector('div:last-child')).setView([lat, lng], hasCoordinates ? 16 : 5);
        container._topHalalMap = map;
        L.tileLayer(@js(config('location.map_tile_url')), { maxZoom: 19, attribution: @js(config('location.map_tile_attribution')) }).addTo(map);
        let marker;
        const syncMarker = (point) => {
            latField.value = point.lat.toFixed(7); lngField.value = point.lng.toFixed(7);
            latField.dispatchEvent(new Event('input', { bubbles: true })); lngField.dispatchEvent(new Event('input', { bubbles: true }));
            const source = document.querySelector('[wire\\:model$="location_update_source"]');
            if (source) { source.value = 'admin_map'; source.dispatchEvent(new Event('input', { bubbles: true })); }
        };
        const putMarker = (point) => { if (marker) map.removeLayer(marker); marker = L.marker(point, { draggable: true }).addTo(map); marker.on('dragend', (event) => syncMarker(event.target.getLatLng())); };
        if (hasCoordinates) putMarker([lat, lng]);
        map.on('click', (event) => { putMarker(event.latlng); syncMarker(event.latlng); });
    });
    document.addEventListener('DOMContentLoaded', window.topHalalInitLocationMaps);
    document.addEventListener('livewire:navigated', window.topHalalInitLocationMaps);
    document.addEventListener('click', (event) => {
        if (event.target.closest('[role="tab"]')) window.setTimeout(window.topHalalInitLocationMaps, 50);
    });
    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('morph.added', ({ el }) => {
            if (el.matches?.('[data-top-halal-location-map]') || el.querySelector?.('[data-top-halal-location-map]')) {
                window.setTimeout(window.topHalalInitLocationMaps, 50);
            }
        });
    });
    if (document.readyState !== 'loading') window.topHalalInitLocationMaps();
    window.setTimeout(window.topHalalInitLocationMaps, 100);
    window.setTimeout(window.topHalalInitLocationMaps, 750);
</script>
