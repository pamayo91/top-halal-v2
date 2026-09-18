export const initializeManagedRestaurantMedia = () => {
    const form = document.querySelector('[data-owner-editor]');
    const gallery = form?.querySelector('[data-owner-media-list]');
    const order = form?.querySelector('[data-owner-media-order]');
    if (!gallery || !order) return;

    const sync = () => {
        const cards = [...gallery.querySelectorAll('[data-owner-media-card]')];
        order.replaceChildren(...cards.map(card => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'media_order[]'; input.value = card.dataset.mediaId;
            return input;
        }));
        cards.forEach((card, index) => {
            card.querySelector('[data-owner-cover-label]').textContent = index === 0 ? 'Couverture' : 'Galerie';
            card.querySelector('[data-owner-media-up]').hidden = index === 0;
            card.querySelector('[data-owner-media-down]').hidden = index === cards.length - 1;
        });
    };

    const setRemovalState = (card, marked) => {
        card.classList.toggle('is-marked-for-removal', marked);
        card.querySelector('[data-owner-media-remove-input]').disabled = !marked;
        card.querySelector('[data-owner-media-remove]').hidden = marked;
        card.querySelector('[data-owner-media-removal-notice]').hidden = !marked;
    };

    gallery.addEventListener('click', event => {
        const card = event.target.closest('[data-owner-media-card]');
        if (!card) return;
        if (event.target.closest('[data-owner-media-remove]')) setRemovalState(card, true);
        if (event.target.closest('[data-owner-media-remove-cancel]')) setRemovalState(card, false);
        if (event.target.closest('[data-owner-media-up]') && card.previousElementSibling?.matches('[data-owner-media-card]')) gallery.insertBefore(card, card.previousElementSibling);
        if (event.target.closest('[data-owner-media-down]') && card.nextElementSibling?.matches('[data-owner-media-card]')) gallery.insertBefore(card.nextElementSibling, card);
        sync();
    });
    sync();
};
