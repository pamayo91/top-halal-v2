const COOKIE_NAME = 'top_halal_analytics_consent_v1';
const CONSENT_MAX_AGE_SECONDS = 60 * 60 * 24 * 183;
const validStates = new Set(['granted', 'denied']);

export const analyticsConsentState = () => {
    const value = document.cookie.split('; ').find(cookie => cookie.startsWith(`${COOKIE_NAME}=`))?.split('=').slice(1).join('=');

    return validStates.has(value) ? value : 'unknown';
};

export const setAnalyticsConsent = state => {
    if (!validStates.has(state)) return 'unknown';
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${COOKIE_NAME}=${state}; Max-Age=${CONSENT_MAX_AGE_SECONDS}; Path=/; SameSite=Lax${secure}`;
    document.dispatchEvent(new CustomEvent('top-halal:analytics-consent-changed', { detail: { state } }));

    return state;
};

export const initializeAnalyticsConsent = () => {
    const banner = document.querySelector('[data-analytics-consent-banner]');
    const dialog = document.querySelector('[data-analytics-consent-dialog]');
    const manage = document.querySelector('[data-analytics-consent-manage]');
    if (!banner && !dialog && !manage) return;

    let opener = null;
    const status = dialog?.querySelector('[data-analytics-consent-status]');
    const labels = { granted: 'autorisé', denied: 'refusé', unknown: 'non défini' };
    const update = state => {
        if (status) status.textContent = `Votre choix actuel : ${labels[state]}.`;
        if (state !== 'unknown') banner?.remove();
    };
    const close = () => { if (dialog?.open) dialog.close(); };

    document.querySelectorAll('[data-analytics-consent-choice]').forEach(button => button.addEventListener('click', () => {
        const state = setAnalyticsConsent(button.dataset.analyticsConsentChoice);
        update(state);
        close();
    }));
    manage?.addEventListener('click', () => {
        opener = manage;
        update(analyticsConsentState());
        dialog?.showModal();
    });
    dialog?.querySelector('[data-analytics-consent-close]')?.addEventListener('click', close);
    dialog?.addEventListener('close', () => opener?.focus());
    update(analyticsConsentState());
};
