<div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" data-top-halal-location-map>
    <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">Déplacez le marqueur pour corriger précisément la position. Les coordonnées sont alors marquées comme corrigées manuellement.</p>
    <div x-ref="map" style="height: 360px" class="rounded-md bg-gray-100"></div>
</div>
@once
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
            window.topHalalInitLocationMaps = () => document.querySelectorAll('[data-top-halal-location-map]').forEach((container) => {
                        if (!window.L || container.dataset.initialized) return;
                        const latField = document.getElementById('location-latitude'), lngField = document.getElementById('location-longitude');
                        if (!latField || !lngField) return;
                        container.dataset.initialized = '1';
                        const hasCoordinates = latField.value !== '' && lngField.value !== '';
                        const lat = hasCoordinates ? Number(latField.value) : 46.2276, lng = hasCoordinates ? Number(lngField.value) : 2.2137;
                        const map = L.map(container.querySelector('[data-map]') || container.querySelector('div:last-child')).setView([lat, lng], hasCoordinates ? 16 : 5);
                        L.tileLayer(@js($tileUrl), { maxZoom: 19, attribution: @js($tileAttribution) }).addTo(map);
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
            if (document.readyState !== 'loading') window.topHalalInitLocationMaps();
    </script>
@endonce
