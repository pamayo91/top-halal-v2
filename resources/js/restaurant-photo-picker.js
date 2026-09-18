const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);

const fileCount = count => count === 0
    ? 'Aucune photo sélectionnée.'
    : `${count} photo${count > 1 ? 's' : ''} sélectionnée${count > 1 ? 's' : ''}.`;

const imageWidth = file => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => { URL.revokeObjectURL(url); resolve(image.naturalWidth); };
    image.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
    image.src = url;
});

const validationMessage = async (file, { minWidth, maxBytes }) => {
    if (!acceptedTypes.has(file.type)) return 'Ce format n’est pas accepté.';
    if (file.size > maxBytes) return 'Cette image dépasse la taille maximale autorisée.';
    try {
        if (await imageWidth(file) < minWidth) return `Cette image fait moins de ${minWidth} px de large.`;
    } catch (_) {
        return 'Cette image ne peut pas être lue.';
    }
    return null;
};

const initializePicker = picker => {
    const input = picker.querySelector('[data-photo-input]');
    const preview = picker.querySelector('[data-photo-preview]');
    const count = picker.querySelector('[data-photo-selection-count]');
    const errors = picker.querySelector('[data-photo-picker-errors]');
    const maxFiles = Number(picker.dataset.photoMaxFiles || 10);
    const minWidth = Number(picker.dataset.photoMinWidth || 800);
    const maxBytes = Number(picker.dataset.photoMaxBytes || 10485760);
    const reorderable = picker.dataset.photoReorderable === 'true';
    const presentation = picker.dataset.photoPresentation || 'public';
    const removeLabel = picker.dataset.photoRemoveLabel || 'Retirer';
    const urls = new Map();
    let files = [];
    let destroyed = false;

    const previewUrl = file => {
        if (!urls.has(file)) urls.set(file, URL.createObjectURL(file));
        return urls.get(file);
    };

    const showErrors = messages => {
        errors.textContent = messages.join(' ');
        errors.hidden = messages.length === 0;
    };

    const syncInput = () => {
        const transfer = new DataTransfer();
        files.forEach(file => transfer.items.add(file));
        input.files = transfer.files;
        count.textContent = fileCount(files.length);
    };

    const removeFile = file => {
        files = files.filter(item => item !== file);
        const url = urls.get(file);
        if (url) URL.revokeObjectURL(url);
        urls.delete(file);
        syncInput();
        render();
    };

    const renderOwnerCard = (file, index) => {
        const item = document.createElement('li');
        item.className = 'owner-media-card owner-new-media-card';
        item.dataset.newPhotoCard = '';
        const image = document.createElement('img');
        image.src = previewUrl(file);
        image.alt = `Aperçu de ${file.name}`;
        const meta = document.createElement('div');
        const badge = document.createElement('strong');
        badge.className = 'owner-new-badge';
        badge.textContent = 'Nouvelle';
        const name = document.createElement('p');
        name.className = 'owner-new-photo-name';
        name.textContent = file.name;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'owner-small-button';
        remove.textContent = removeLabel;
        remove.addEventListener('click', () => removeFile(file));
        meta.append(badge, name, remove);
        item.append(image, meta);
        return item;
    };

    const renderPublicCard = (file, index) => {
        const item = document.createElement(preview.tagName === 'OL' ? 'li' : 'div');
        if (preview.tagName !== 'OL') item.className = 'photo-cover-preview-card';
        item.dataset.newPhotoCard = '';
        const image = document.createElement('img');
        image.src = previewUrl(file);
        image.alt = `Aperçu de ${file.name}`;
        const details = document.createElement('span');
        const badge = document.createElement('strong');
        badge.className = 'photo-new-badge';
        badge.textContent = 'Nouvelle';
        const name = document.createElement('small');
        name.textContent = file.name;
        details.append(badge, name);
        const actions = document.createElement('span');
        if (reorderable) {
            const up = document.createElement('button');
            up.type = 'button'; up.textContent = 'Monter'; up.disabled = index === 0;
            up.addEventListener('click', () => {
                [files[index - 1], files[index]] = [files[index], files[index - 1]];
                syncInput(); render();
            });
            const down = document.createElement('button');
            down.type = 'button'; down.textContent = 'Descendre'; down.disabled = index === files.length - 1;
            down.addEventListener('click', () => {
                [files[index], files[index + 1]] = [files[index + 1], files[index]];
                syncInput(); render();
            });
            actions.append(up, down);
        }
        const remove = document.createElement('button');
        remove.type = 'button'; remove.textContent = removeLabel;
        remove.addEventListener('click', () => removeFile(file));
        actions.append(remove);
        item.append(image, details, actions);
        return item;
    };

    const render = () => {
        preview.replaceChildren();
        files.forEach((file, index) => {
            if (presentation === 'owner') preview.append(renderOwnerCard(file, index));
            else preview.append(renderPublicCard(file, index));
        });
    };

    const addFiles = async selected => {
        const messages = [];
        for (const file of selected) {
            if (files.length >= maxFiles) { messages.push(`Vous pouvez ajouter au maximum ${maxFiles} photos.`); break; }
            const message = await validationMessage(file, { minWidth, maxBytes });
            if (message) { messages.push(message); continue; }
            files.push(file);
        }
        showErrors([...new Set(messages)]);
        syncInput();
        render();
    };

    const clear = () => {
        files.forEach(file => {
            const url = urls.get(file);
            if (url) URL.revokeObjectURL(url);
        });
        urls.clear();
        files = [];
        input.value = '';
        count.textContent = fileCount(0);
        preview.replaceChildren();
        showErrors([]);
    };

    const destroy = () => {
        if (destroyed) return;
        destroyed = true;
        clear();
    };

    input.addEventListener('change', async () => {
        const selected = [...input.files];
        input.value = '';
        await addFiles(selected);
    });
    picker.addEventListener('photo-picker-destroy', destroy, { once: true });
    window.addEventListener('pagehide', destroy, { once: true });
    syncInput();
};

export const initializeRestaurantPhotoPickers = () => {
    document.querySelectorAll('[data-photo-picker]').forEach(initializePicker);
};
