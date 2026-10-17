@props(['state'])

@if($state === \App\Services\AnalyticsConsent::UNKNOWN)
    <section class="analytics-consent-banner" data-analytics-consent-banner aria-labelledby="analytics-consent-title" role="region">
        <div class="analytics-consent-copy">
            <h2 id="analytics-consent-title">Mesure d'audience</h2>
            <p>Nous utilisons Google Analytics uniquement pour comprendre comment Top Halal est utilisé et améliorer le site. Vous pouvez accepter ou refuser cette mesure d'audience.</p>
        </div>
        <div class="analytics-consent-actions" aria-label="Votre choix pour la mesure d'audience">
            <button class="button button-secondary" type="button" data-analytics-consent-choice="denied">Refuser</button>
            <button class="button" type="button" data-analytics-consent-choice="granted">Accepter</button>
        </div>
    </section>
@endif

<dialog class="analytics-consent-dialog" data-analytics-consent-dialog aria-labelledby="analytics-consent-dialog-title">
    <div class="analytics-consent-dialog-content">
        <h2 id="analytics-consent-dialog-title">Gérer les cookies</h2>
        <p data-analytics-consent-status>Votre choix actuel : {{ $state === \App\Services\AnalyticsConsent::GRANTED ? 'autorisé' : ($state === \App\Services\AnalyticsConsent::DENIED ? 'refusé' : 'non défini') }}.</p>
        <p>Vous pouvez autoriser ou refuser la mesure d'audience à tout moment.</p>
        <div class="analytics-consent-actions" aria-label="Modifier votre choix pour la mesure d'audience">
            <button class="button button-secondary" type="button" data-analytics-consent-choice="denied">Refuser</button>
            <button class="button" type="button" data-analytics-consent-choice="granted">Accepter</button>
        </div>
        <button class="analytics-consent-close link-button" type="button" data-analytics-consent-close>Fermer</button>
    </div>
</dialog>
