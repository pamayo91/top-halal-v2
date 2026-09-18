const dayRows = editor => [...editor.querySelectorAll('[data-hours-day]')];

const slotTemplate = row => row.querySelector('[data-hours-slot-template]');

const renumberSlots = (row, index) => {
    row.querySelectorAll('[data-hours-slot]').forEach((slot, slotIndex) => {
        const id = slot.querySelector('[data-hours-slot-id]');
        const open = slot.querySelector('[data-hours-slot-open]');
        const close = slot.querySelector('[data-hours-slot-close]');
        if (id) {
            id.name = `hours[${index}][slots][${slotIndex}][id]`;
            id.disabled = !id.value;
        }
        if (open) open.name = `hours[${index}][slots][${slotIndex}][opens_at]`;
        if (close) close.name = `hours[${index}][slots][${slotIndex}][closes_at]`;
    });
};

const addSlot = (row, index, focus = false) => {
    const slots = row.querySelector('[data-hours-slots]');
    const fragment = slotTemplate(row).content.cloneNode(true);
    slots.append(fragment);
    renumberSlots(row, index);
    if (focus) slots.lastElementChild.querySelector('[data-hours-slot-open]')?.focus();
};

const syncRow = (editor, row, focus = false) => {
    const rows = dayRows(editor);
    const index = rows.indexOf(row);
    const slots = row.querySelector('[data-hours-slots]');
    const isSlots = row.querySelector('[data-hours-status]').value === 'slots';
    slots.hidden = !isSlots;
    if (isSlots && !slots.querySelector('[data-hours-slot]')) addSlot(row, index, focus);
    const items = [...slots.querySelectorAll('[data-hours-slot]')];
    const maxSlots = Number(editor.dataset.hoursMaxSlots || 8);
    row.querySelector('[data-add-hours-slot]').hidden = !isSlots || items.length >= maxSlots;
    items.forEach((slot, slotIndex) => {
        slot.querySelectorAll('input[type="time"]').forEach(input => { input.required = isSlots; });
        slot.querySelector('[data-remove-hours-slot]').hidden = slotIndex === 0;
    });
    renumberSlots(row, index);
};

const validationMessage = row => {
    if (row.querySelector('[data-hours-status]').value !== 'slots') return '';
    let previousClose = null;
    for (const slot of row.querySelectorAll('[data-hours-slot]')) {
        const open = slot.querySelector('[data-hours-slot-open]');
        const close = slot.querySelector('[data-hours-slot-close]');
        if (!open.value || !close.value) continue;
        if (close.value <= open.value) return 'La fermeture doit être postérieure à l’ouverture.';
        if (previousClose && open.value <= previousClose) return 'Chaque plage doit commencer après la précédente.';
        previousClose = close.value;
    }
    return '';
};

export const validateRestaurantHoursEditors = scope => {
    let valid = true;
    const editors = scope.matches?.('[data-hours-editor]')
        ? [scope, ...scope.querySelectorAll('[data-hours-editor]')]
        : [...(scope.querySelectorAll?.('[data-hours-editor]') || [])];
    editors.forEach(editor => {
        dayRows(editor).forEach(row => {
            const message = validationMessage(row);
            row.querySelectorAll('[data-hours-slot-close]').forEach(input => input.setCustomValidity(''));
            if (message) {
                row.querySelectorAll('[data-hours-slot-close]').item(row.querySelectorAll('[data-hours-slot-close]').length - 1)?.setCustomValidity(message);
                valid = false;
            }
        });
    });
    return valid;
};

export const initializeRestaurantHoursEditors = () => {
    document.querySelectorAll('[data-hours-editor]').forEach(editor => {
        const form = editor.closest('form');
        dayRows(editor).forEach(row => {
            row.querySelector('[data-hours-status]').addEventListener('change', () => syncRow(editor, row, true));
            row.querySelector('[data-add-hours-slot]').addEventListener('click', () => {
                addSlot(row, dayRows(editor).indexOf(row), true);
                syncRow(editor, row);
            });
            row.addEventListener('click', event => {
                const button = event.target.closest('[data-remove-hours-slot]');
                if (!button) return;
                button.closest('[data-hours-slot]').remove();
                syncRow(editor, row);
            });
            row.addEventListener('input', () => validateRestaurantHoursEditors(editor));
            syncRow(editor, row);
        });

        editor.querySelector('[data-copy-hours]').addEventListener('click', () => {
            const source = editor.querySelector(`[data-hours-day="${editor.querySelector('[data-copy-source]').value}"]`);
            editor.querySelectorAll('[data-copy-target]:checked').forEach(target => {
                const destination = editor.querySelector(`[data-hours-day="${target.value}"]`);
                destination.querySelector('[data-hours-status]').value = source.querySelector('[data-hours-status]').value;
                const sourceSlots = [...source.querySelectorAll('[data-hours-slot]')];
                const destinationSlots = [...destination.querySelectorAll('[data-hours-slot]')];
                while (destinationSlots.length > sourceSlots.length && destinationSlots.length > 1) destinationSlots.pop().remove();
                while (destination.querySelectorAll('[data-hours-slot]').length < sourceSlots.length) addSlot(destination, dayRows(editor).indexOf(destination));
                destination.querySelectorAll('[data-hours-slot]').forEach((slot, index) => {
                    const sourceSlot = sourceSlots[index];
                    slot.querySelector('[data-hours-slot-open]').value = sourceSlot?.querySelector('[data-hours-slot-open]')?.value || '';
                    slot.querySelector('[data-hours-slot-close]').value = sourceSlot?.querySelector('[data-hours-slot-close]')?.value || '';
                });
                syncRow(editor, destination);
            });
            validateRestaurantHoursEditors(editor);
        });

        form?.addEventListener('submit', event => {
            if (validateRestaurantHoursEditors(form)) return;
            event.preventDefault();
            editor.querySelector(':invalid')?.reportValidity();
        });
    });
};
