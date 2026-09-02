@props(['cities' => collect(), 'compact' => false])
<form class="restaurant-search {{ $compact ? 'restaurant-search-compact' : '' }}" data-restaurant-search action="{{ route('restaurants.search') }}" method="get" data-cities-url="{{ route('restaurants.search.cities') }}" data-suggestions-url="{{ route('restaurants.search.suggestions') }}" data-near-me-url="{{ route('restaurants.near-me') }}">
    <div class="restaurant-search-field location-field">
        <label class="sr-only" for="restaurant-search-location">Localisation</label>
        <div class="search-input-wrap"><svg class="search-icon" aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.25"/></svg><input id="restaurant-search-location" data-location-input type="search" value="Paris" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="restaurant-search-cities"><input data-location-value type="hidden" name="ville" value="paris"></div>
        <div id="restaurant-search-cities" class="search-popover" data-cities-list role="listbox" hidden>
            <button type="button" data-near-me>Autour de moi <span aria-hidden="true">›</span></button>
            <p class="search-popover-heading">Suggestions</p>
            @foreach($cities as $city)<button type="button" role="option" data-city-name="{{ $city['name'] }}" data-city-slug="{{ $city['slug'] }}">{{ $city['name'] }}</button>@endforeach
        </div>
    </div>
    <div class="restaurant-search-field query-field">
        <label class="sr-only" for="restaurant-search-query">Spécialité ou nom de restaurant</label>
        <div class="search-input-wrap"><svg class="search-icon" aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.25 4.25"/></svg><input id="restaurant-search-query" data-query-input name="q" type="search" placeholder="Spécialité ou nom de restaurant" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="restaurant-search-suggestions"></div>
        <input data-category-input type="hidden" name="categories[]" disabled>
        <div id="restaurant-search-suggestions" class="search-popover" data-suggestions-list role="listbox" hidden></div>
    </div>
    <button class="button" type="submit">Rechercher</button>
    <p class="search-message" data-search-message role="status" hidden></p>
</form>
