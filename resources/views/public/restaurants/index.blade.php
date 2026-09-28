<x-layouts.app title="Restaurants halal | Top Halal" canonical="{{ route('restaurants.index') }}" robots="{{ $hasFilters || request('page') || request()->boolean('near_me') ? 'noindex,follow' : 'index,follow' }}">
    <x-slot:head><style>.restaurant-listing-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}.restaurant-listing-grid .card-image{height:clamp(180px,16vw,210px);aspect-ratio:auto!important;min-height:0;overflow:hidden;flex:none}.restaurant-listing-grid .card-image img{width:100%!important;height:100%!important;object-fit:cover!important}@media(max-width:760px){.restaurant-listing-grid{grid-template-columns:1fr}.restaurant-listing-grid .card-image{height:220px}}</style></x-slot:head>
    <section class="page-header"><div class="shell"><nav class="breadcrumbs" aria-label="Fil d’Ariane"><a href="{{ route('home') }}">Accueil</a><span aria-hidden="true">/</span><span aria-current="page">Restaurants</span></nav><h1>Restaurants halal</h1><p>Trouvez une adresse par ville, spécialité ou service.</p><x-restaurant-search :cities="collect()" compact :selected-city="$selectedCity?->city_name ?? ''" :selected-city-code="$selectedCity?->city_code ?? ''" :query="request('q', '')" /></div></section>
    <div class="shell directory-mobile-filter-bar"><button class="button button-secondary filters-trigger" type="button" data-filters-trigger aria-controls="directory-filters" aria-expanded="false">Filtres{{ $activeFilterCount ? " ({$activeFilterCount})" : '' }}</button></div>
    <div class="shell layout-with-aside">
        <aside class="filters" id="directory-filters" data-filters-drawer aria-labelledby="directory-filters-title">
            <div class="filters-drawer-heading"><h2 id="directory-filters-title">Filtres</h2><button type="button" class="filters-close" data-filters-close aria-label="Fermer les filtres">×</button></div>
            <form action="{{ route('restaurants.index') }}" method="get">
                @php
                    $selectedCategories = (array) request('categories', []);
                    $selectedFeatures = (array) request('features', []);
                    $categoryFilterExpanded = $categories->values()->slice(8)->contains(fn ($category) => in_array($category->slug, $selectedCategories, true));
                    $featureFilterExpanded = $features->values()->slice(8)->contains(fn ($feature) => in_array($feature->slug, $selectedFeatures, true));
                @endphp
                @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                @if(request('city_code'))<input type="hidden" name="city_code" value="{{ request('city_code') }}">@endif
                <fieldset data-filter-group data-filter-limit="8"><legend>Spécialités</legend>@foreach($categories as $index => $category)<label data-filter-option @if(! $categoryFilterExpanded && $index >= 8) hidden @endif><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $selectedCategories, true))> {{ $category->name }}</label>@endforeach<button type="button" class="filter-list-toggle" data-filter-toggle data-expand-label="Voir toutes les spécialités" aria-expanded="{{ $categoryFilterExpanded ? 'true' : 'false' }}">{{ $categoryFilterExpanded ? 'Voir moins' : 'Voir toutes les spécialités' }}</button></fieldset>
                <fieldset data-filter-group data-filter-limit="8"><legend>Services</legend>@foreach($features as $index => $feature)<label data-filter-option @if(! $featureFilterExpanded && $index >= 8) hidden @endif><input type="checkbox" name="features[]" value="{{ $feature->slug }}" @checked(in_array($feature->slug, $selectedFeatures, true))> {{ $feature->name }}</label>@endforeach<button type="button" class="filter-list-toggle" data-filter-toggle data-expand-label="Voir tous les services" aria-expanded="{{ $featureFilterExpanded ? 'true' : 'false' }}">{{ $featureFilterExpanded ? 'Voir moins' : 'Voir tous les services' }}</button></fieldset>
                <button class="button" type="submit">Appliquer les filtres</button>@if($hasFilters)<a class="button button-secondary" href="{{ route('restaurants.index') }}">Réinitialiser</a>@endif
            </form>
        </aside>
        <section aria-live="polite">
            @if(request()->boolean('near_me'))<p class="flash" data-near-me-request>Recherche des restaurants proches de vous…</p><noscript><p class="flash flash-error">Activez JavaScript pour utiliser votre position.</p></noscript>@endif
            @if($locationError)<p class="flash flash-error">Nous n'avons pas trouvé cette ville. Vérifiez l'orthographe ou sélectionnez une suggestion.</p>@endif
            <p class="results-count">{{ number_format($restaurants->total(), 0, ',', ' ') }} restaurant{{ $restaurants->total() > 1 ? 's' : '' }} halal</p>
            <div class="cards-grid restaurant-listing-grid">@forelse($restaurants as $restaurant)<x-restaurant-card :restaurant="$restaurant" />@empty <div class="empty-state"><h2>Aucun restaurant ne correspond.</h2><p>{{ $selectedCity ? "Aucun restaurant halal référencé à {$selectedCity->city_name} pour le moment." : 'Essayez une autre ville, un autre terme ou retirez un filtre.' }}</p><a class="button" href="{{ route('restaurants.index') }}">Voir toutes les adresses</a></div>@endforelse</div>
            {{ $restaurants->onEachSide(1)->links() }}
        </section>
    </div>
</x-layouts.app>
