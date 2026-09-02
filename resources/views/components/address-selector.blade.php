@props([
    'label' => 'Adresse du restaurant',
    'endpoint' => null,
    'token' => null,
    'addressLine1' => null,
    'postalCode' => null,
    'cityName' => null,
    'latitude' => null,
    'longitude' => null,
    'mapMoved' => false,
    'showExisting' => false,
])

<div class="address-selector" data-address-selector data-address-endpoint="{{ $endpoint ?? route('restaurant-submissions.addresses') }}">
    <label for="address-query">{{ $label }}</label>
    <input id="address-query" type="search" autocomplete="street-address" placeholder="Ex. 46 boulevard du Temple, Paris" data-address-query aria-describedby="address-help address-results">
    <p id="address-help" class="form-help">Votre adresse exacte n’apparaît pas ? Sélectionnez l’adresse la plus proche proposée, puis ajustez précisément la position du restaurant sur la carte.</p>
    <div id="address-results" class="address-results" data-address-results aria-live="polite"></div>

    <input type="hidden" name="address_suggestion_token" value="{{ old('address_suggestion_token', $token) }}" data-address-token>
    <p class="address-selected" data-address-selected @if(!old('address_suggestion_token', $token)) hidden @endif>Adresse sélectionnée. Vous pouvez déplacer le marqueur pour corriger la position.</p>

    <div class="address-fields" data-address-fields @if(!old('address_suggestion_token', $token) && !$showExisting) hidden @endif>
        <label>Adresse <input readonly tabindex="-1" value="{{ old('address_line1', $addressLine1) }}" data-address-display="address_line1"></label>
        <label>Code postal <input readonly tabindex="-1" value="{{ old('postal_code', $postalCode) }}" data-address-display="postal_code"></label>
        <label>Ville <input readonly tabindex="-1" value="{{ old('city_name', $cityName) }}" data-address-display="city_name"></label>
    </div>

    <div class="submission-map-wrap">
        <div class="submission-map" data-address-map data-tile-url="{{ config('location.map_tile_url') }}" data-tile-attribution="{{ config('location.map_tile_attribution') }}" aria-label="Carte de la position du restaurant"></div>
        <p class="form-help" data-map-help>Choisissez une adresse proposée pour afficher la carte et déplacer le marqueur.</p>
    </div>
    <input type="hidden" name="latitude" value="{{ old('latitude', $latitude) }}" data-latitude>
    <input type="hidden" name="longitude" value="{{ old('longitude', $longitude) }}" data-longitude>
    <input type="hidden" name="map_moved" value="{{ old('map_moved', $mapMoved ? 1 : 0) }}" data-map-moved>

    @foreach(['address_suggestion_token', 'latitude', 'longitude'] as $field)
        @error($field)<p class="field-error">{{ $message }}</p>@enderror
    @endforeach
</div>
