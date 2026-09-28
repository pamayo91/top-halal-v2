@props(['restaurants', 'categories', 'features', 'selectedCity' => null, 'searchState', 'locationError' => false])
@php
    $selectedCategories = $searchState['categories'];
    $selectedFeatures = $searchState['features'];
    $activeFilterCount = count($selectedCategories) + count($selectedFeatures);
    $hasFilters = $searchState['q'] !== '' || $selectedCategories !== [] || $selectedFeatures !== [] || $searchState['nearby'];
    $categoryFilterExpanded = $categories->values()->slice(8)->contains(fn ($category) => in_array($category->slug, $selectedCategories, true));
    $featureFilterExpanded = $features->values()->slice(8)->contains(fn ($feature) => in_array($feature->slug, $selectedFeatures, true));
    $locationParameters = array_filter(['city_code' => $searchState['city_code'], 'lat' => $searchState['nearby'] ? $searchState['lat'] : null, 'lng' => $searchState['nearby'] ? $searchState['lng'] : null], fn ($value) => $value !== null);
@endphp
<div class="shell directory-mobile-filter-bar"><button class="button button-secondary filters-trigger" type="button" data-filters-trigger aria-controls="directory-filters" aria-expanded="false">Filtres{{ $activeFilterCount ? " ({$activeFilterCount})" : '' }}</button></div>
<div class="shell layout-with-aside">
    <aside class="filters" id="directory-filters" data-filters-drawer aria-labelledby="directory-filters-title">
        <div class="filters-drawer-heading"><h2 id="directory-filters-title">Filtres</h2><button type="button" class="filters-close" data-filters-close aria-label="Fermer les filtres">×</button></div>
        <form action="{{ route('restaurants.index') }}" method="get">
            @if($searchState['q'] !== '')<input type="hidden" name="q" value="{{ $searchState['q'] }}">@endif
            @if($searchState['city_code'])<input type="hidden" name="city_code" value="{{ $searchState['city_code'] }}">@endif
            @if($searchState['nearby'])<input type="hidden" name="lat" value="{{ $searchState['lat'] }}"><input type="hidden" name="lng" value="{{ $searchState['lng'] }}">@endif
            <fieldset data-filter-group data-filter-limit="8"><legend>Spécialités</legend>@foreach($categories as $index => $category)<label data-filter-option @if(! $categoryFilterExpanded && $index >= 8) hidden @endif><input type="checkbox" name="categories[]" value="{{ $category->slug }}" @checked(in_array($category->slug, $selectedCategories, true))> {{ $category->name }}</label>@endforeach<button type="button" class="filter-list-toggle" data-filter-toggle data-expand-label="Voir toutes les spécialités" aria-expanded="{{ $categoryFilterExpanded ? 'true' : 'false' }}">{{ $categoryFilterExpanded ? 'Voir moins' : 'Voir toutes les spécialités' }}</button></fieldset>
            <fieldset data-filter-group data-filter-limit="8"><legend>Services</legend>@foreach($features as $index => $feature)<label data-filter-option @if(! $featureFilterExpanded && $index >= 8) hidden @endif><input type="checkbox" name="features[]" value="{{ $feature->slug }}" @checked(in_array($feature->slug, $selectedFeatures, true))> {{ $feature->name }}</label>@endforeach<button type="button" class="filter-list-toggle" data-filter-toggle data-expand-label="Voir tous les services" aria-expanded="{{ $featureFilterExpanded ? 'true' : 'false' }}">{{ $featureFilterExpanded ? 'Voir moins' : 'Voir tous les services' }}</button></fieldset>
            <button class="button" type="submit">Appliquer les filtres</button>@if($hasFilters)<a class="button button-secondary" href="{{ route('restaurants.index', $locationParameters) }}">Réinitialiser les filtres</a>@endif
        </form>
    </aside>
    <section aria-live="polite">
        @if($locationError)<p class="flash flash-error">Nous n'avons pas trouvé cette ville. Vérifiez l'orthographe ou sélectionnez une suggestion.</p>@endif
        <p class="results-count">{{ number_format($restaurants->total(), 0, ',', ' ') }} restaurant{{ $restaurants->total() > 1 ? 's' : '' }} halal</p>
        <div class="cards-grid restaurant-listing-grid">@forelse($restaurants as $restaurant)<x-restaurant-card :restaurant="$restaurant" />@empty <div class="empty-state"><h2>Aucun restaurant ne correspond.</h2><p>{{ $selectedCity ? "Aucun restaurant halal référencé à {$selectedCity->city_name} pour le moment." : 'Essayez une autre ville, un autre terme ou retirez un filtre.' }}</p><a class="button" href="{{ route('restaurants.index') }}">Voir toutes les adresses</a></div>@endforelse</div>
        {{ $restaurants->onEachSide(1)->links() }}
    </section>
</div>
