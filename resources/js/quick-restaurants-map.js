import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

document.querySelectorAll('[data-quick-restaurants-map]').forEach((container) => {
    if (container.dataset.initialized) return;
    container.dataset.initialized = 'true';

    const points = JSON.parse(container.dataset.points || '[]');
    const map = L.map(container, { scrollWheelZoom: false }).setView([46.6034, 1.8883], 5);
    L.tileLayer(container.dataset.tileUrl, {
        maxZoom: 19,
        attribution: container.dataset.tileAttribution,
    }).addTo(map);

    const bounds = [];
    points.forEach((point) => {
        const marker = L.marker([point.latitude, point.longitude], {
            icon: L.divIcon({
                className: 'quick-map-marker',
                html: '<span aria-hidden="true"></span>',
                iconSize: [20, 20],
                iconAnchor: [10, 10],
            }),
        }).addTo(map);
        marker.bindPopup(`<a href="${point.url}">${escapeHtml(point.name)}</a>${point.city ? `<br><span>${escapeHtml(point.city)}</span>` : ''}`);
        bounds.push([point.latitude, point.longitude]);
    });

    if (bounds.length === 1) map.setView(bounds[0], 12);
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 11 });
    container.querySelector('.editorial-quick-map-loading')?.remove();
});

function escapeHtml(value) {
    const element = document.createElement('span');
    element.textContent = value;
    return element.innerHTML;
}
