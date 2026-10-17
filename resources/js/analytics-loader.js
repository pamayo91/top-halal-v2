import { analyticsConsentState } from './analytics-consent';

const SCRIPT_ID = 'top-halal-ga4';
const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 183;
const grantedConsent = {
    analytics_storage: 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
};

let scheduled = false;
let initialized = false;
let loaded = false;
let pageViewSent = false;
let reloading = false;

const measurementId = () => document.documentElement.dataset.analyticsMeasurementId || null;
const analyticsAvailable = () => measurementId() !== null;
const safely = callback => { try { callback(); } catch (_) { /* Analytics must remain non-critical. */ } };

const removeAnalyticsCookies = () => {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    const domains = [window.location.hostname];
    const labels = window.location.hostname.split('.');
    if (labels.length > 2) domains.push(`.${labels.slice(-2).join('.')}`);

    document.cookie.split(';').map(cookie => cookie.trim().split('=')[0]).filter(name => name === '_ga' || name.startsWith('_ga_')).forEach(name => {
        document.cookie = `${name}=; Max-Age=0; Path=/; SameSite=Lax${secure}`;
        domains.forEach(domain => { document.cookie = `${name}=; Max-Age=0; Path=/; Domain=${domain}; SameSite=Lax${secure}`; });
    });
};

const pageLocation = () => `${window.location.origin}${window.location.pathname}`;
const pageReferrer = () => {
    if (!document.referrer) return '';
    try {
        const referrer = new URL(document.referrer);
        return `${referrer.origin}${referrer.pathname}`;
    } catch (_) {
        return '';
    }
};

const gtag = (...args) => window.dataLayer.push(args);

const initialize = () => {
    scheduled = false;
    if (initialized || !analyticsAvailable() || analyticsConsentState() !== 'granted') return;

    initialized = true;
    window.dataLayer = [];
    window.gtag = gtag;
    window.gtag('js', new Date());
    window.gtag('consent', 'default', grantedConsent);
    window.gtag('consent', 'update', grantedConsent);
    window.gtag('config', measurementId(), {
        send_page_view: false,
        cookie_expires: COOKIE_MAX_AGE_SECONDS,
        cookie_path: '/',
        allow_google_signals: false,
    });
    if (!pageViewSent) {
        pageViewSent = true;
        window.gtag('event', 'page_view', {
            page_location: pageLocation(),
            page_referrer: pageReferrer(),
            page_title: document.title,
        });
    }

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId())}`;
    script.onload = () => { loaded = true; };
    script.onerror = () => { loaded = false; };
    document.head.append(script);
};

const schedule = () => {
    if (scheduled || initialized || !analyticsAvailable() || analyticsConsentState() !== 'granted') return;
    scheduled = true;
    const defer = () => {
        if ('requestIdleCallback' in window) window.requestIdleCallback(() => safely(initialize), { timeout: 2000 });
        else window.setTimeout(() => safely(initialize), 800);
    };

    if (document.readyState === 'complete') defer();
    else window.addEventListener('load', defer, { once: true });
};

const revoke = () => {
    scheduled = false;
    removeAnalyticsCookies();
    if (!initialized || reloading) return;

    reloading = true;
    if (typeof window.gtag === 'function') window.gtag('consent', 'update', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    window.location.reload();
};

export const initializeAnalyticsLoader = () => {
    if (!analyticsAvailable()) return;
    if (analyticsConsentState() === 'granted') schedule();
    else removeAnalyticsCookies();

    document.addEventListener('top-halal:analytics-consent-changed', event => {
        if (event.detail?.state === 'granted') schedule();
        else revoke();
    });
};

export const analyticsLoaderStatus = () => ({ scheduled, initialized, loaded, pageViewSent });
