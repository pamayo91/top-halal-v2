export const initializeOwnerRestaurantEditor = () => {
    const form = document.querySelector('[data-owner-editor]');
    if (!form) return;

    const slotNames = day => {
        const dayIndex = [...form.querySelectorAll('[data-owner-hours-day]')].indexOf(day);
        day.querySelectorAll('[data-owner-hours-slot]').forEach((slot, slotIndex) => {
            const inputs = slot.querySelectorAll('input[type="time"]');
            inputs[0]?.setAttribute('name', `hours[${dayIndex}][slots][${slotIndex}][opens_at]`);
            inputs[1]?.setAttribute('name', `hours[${dayIndex}][slots][${slotIndex}][closes_at]`);
        });
    };
    const addSlot = day => {
        const template = day.querySelector('[data-owner-slot-template]');
        const slots = day.querySelector('[data-owner-hours-slots]');
        const fragment = template.content.cloneNode(true);
        slots.append(fragment);
        slots.hidden = false;
        slotNames(day);
        slots.lastElementChild.querySelector('input[type="time"]')?.focus();
    };
    form.querySelectorAll('[data-owner-hours-day]').forEach(day => {
        const status = day.querySelector('[data-owner-hours-status]');
        const sync = () => {
            const hasSlots = status.value === 'slots';
            day.querySelector('[data-owner-hours-slots]').hidden = !hasSlots;
            day.querySelector('[data-owner-add-slot]').hidden = !hasSlots;
            if (hasSlots && !day.querySelector('[data-owner-hours-slot]')) addSlot(day);
        };
        status.addEventListener('change', sync);
        day.querySelector('[data-owner-add-slot]').addEventListener('click', () => addSlot(day));
        day.addEventListener('click', event => {
            if (!event.target.closest('[data-owner-remove-slot]')) return;
            event.target.closest('[data-owner-hours-slot]').remove();
            slotNames(day);
        });
        slotNames(day);
        sync();
    });

    const mediaList = form.querySelector('[data-owner-media-list]');
    const order = form.querySelector('[data-owner-media-order]');
    const syncMediaOrder = () => {
        order.replaceChildren(...[...mediaList.querySelectorAll('[data-owner-media-card]')].map(card => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'media_order[]'; input.value = card.dataset.mediaId; return input;
        }));
        mediaList.querySelectorAll('[data-owner-media-card]').forEach((card, index, cards) => {
            card.querySelector('[data-owner-cover-label]').textContent = index === 0 ? 'Couverture' : 'Galerie';
            card.querySelector('[data-owner-media-up]').disabled = index === 0;
            card.querySelector('[data-owner-media-down]').disabled = index === cards.length - 1;
        });
    };
    mediaList?.addEventListener('click', event => {
        const card = event.target.closest('[data-owner-media-card]');
        if (!card) return;
        if (event.target.closest('[data-owner-media-up]') && card.previousElementSibling?.matches('[data-owner-media-card]')) mediaList.insertBefore(card, card.previousElementSibling);
        if (event.target.closest('[data-owner-media-down]') && card.nextElementSibling?.matches('[data-owner-media-card]')) mediaList.insertBefore(card.nextElementSibling, card);
        syncMediaOrder();
    });
    if (mediaList) syncMediaOrder();
};
