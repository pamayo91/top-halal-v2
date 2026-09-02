@props(['cities' => collect(), 'compact' => false])
<form class="restaurant-search {{ $compact ? 'restaurant-search-compact' : '' }}" data-restaurant-search action="{{ route('restaurants.search') }}" method="get" data-cities-url="{{ route('restaurants.search.cities') }}" data-suggestions-url="{{ route('restaurants.search.suggestions') }}" data-near-me-url="{{ route('restaurants.near-me') }}">
    <div class="restaurant-search-field location-field">
        <label for="restaurant-search-location">Localisation</label>
        <div class="search-input-wrap"><span aria-hidden="true">⌖</span><input id="restaurant-search-location" data-location-input type="search" value="Paris" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="restaurant-search-cities"><input data-location-value type="hidden" name="ville" value="paris"></div>
        <div id="restaurant-search-cities" class="search-popover" data-cities-list role="listbox" hidden>
            <button type="button" data-near-me>Autour de moi</button>
            @foreach($cities as $city)<button type="button" role="option" data-city-name="{{ $city['name'] }}" data-city-slug="{{ $city['slug'] }}">{{ $city['name'] }}</button>@endforeach
        </div>
    </div>
    <div class="restaurant-search-field query-field">
        <label for="restaurant-search-query">Spécialité ou nom de restaurant</label>
        <div class="search-input-wrap"><span aria-hidden="true">⌕</span><input id="restaurant-search-query" data-query-input name="q" type="search" placeholder="Spécialité ou nom de restaurant" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="restaurant-search-suggestions"></div>
        <input data-category-input type="hidden" name="categories[]" disabled>
        <div id="restaurant-search-suggestions" class="search-popover" data-suggestions-list role="listbox" hidden></div>
    </div>
    <button class="button" type="submit">Rechercher</button>
    <p class="search-message" data-search-message role="status" hidden></p>
</form>
