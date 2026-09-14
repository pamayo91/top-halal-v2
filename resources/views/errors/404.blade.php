@php($page404 = app(\App\Services\ErrorPageSettings::class)->values())
<x-layouts.app title="Page introuvable | Top Halal" robots="noindex,follow">
    <section class="error-404-page">
    <div class="error-404 shell" aria-labelledby="error-404-title">
        <div class="error-404-main">
            <div class="error-404-visual" aria-hidden="true">
                @if($page404['illustration'])
                    @php($variants = $page404['illustration']->variants)
                    <img src="{{ $variants->last()?->width ? $page404['illustration']->deliveryUrl($variants->last()->width) : $page404['illustration']->deliveryUrl() }}" srcset="{{ $variants->map(fn ($variant) => $page404['illustration']->deliveryUrl($variant->width).' '.$variant->width.'w')->implode(', ') }}" sizes="(max-width: 760px) calc(100vw - 2rem), 520px" width="{{ $page404['illustration']->width }}" height="{{ $page404['illustration']->height }}" alt="">
                @else
                    <div class="error-404-placeholder" aria-hidden="true"><span>404</span></div>
                @endif
            </div>
            <div class="error-404-copy">
                <p class="error-404-code" aria-hidden="true">404</p>
                <h1 id="error-404-title">{{ $page404['title'] }}</h1>
                <p>{{ $page404['text'] }}</p>
                <div class="error-404-actions">
                    <a class="button" href="{{ route('home') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>Retour à l’accueil</a>
                    <a class="button button-secondary" href="{{ route('restaurants.index') }}"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.25"/></svg> Trouver un restaurant halal</a>
                </div>
            </div>
        </div>
        <section class="error-404-reassurance" aria-label="Les avantages Top Halal">
            <div class="error-404-benefit"><span class="error-404-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.25 4.25"/></svg></span><p><strong>Des milliers de restaurants</strong><span>Partout en France</span></p></div>
            <div class="error-404-benefit"><span class="error-404-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 3v8M8 3v8M4 7h4M6 11v10M15 3v18M15 3c3 0 5 2 5 5v4h-5"/></svg></span><p><strong>Toutes vos cuisines préférées</strong><span>Orientale, asiatique, burger...</span></p></div>
            <div class="error-404-benefit"><span class="error-404-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/></svg></span><p><strong>Des avis authentiques</strong><span>Par une communauté de gourmands</span></p></div>
            <div class="error-404-benefit"><span class="error-404-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20.8 5.9a5.3 5.3 0 0 0-7.5 0L12 7.2l-1.3-1.3a5.3 5.3 0 0 0-7.5 7.5L12 22l8.8-8.6a5.3 5.3 0 0 0 0-7.5Z"/></svg></span><p><strong>Une recherche en toute confiance</strong><span>100% halal</span></p></div>
        </section>
    </div>
    </section>
</x-layouts.app>
