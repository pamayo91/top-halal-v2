<section class="editorial-quick-map" aria-labelledby="quick-map-title">
    <div class="editorial-quick-map-canvas" data-quick-restaurants-map
        data-points='@json($points)'
        data-tile-url="{{ config('location.map_tile_url') }}"
        data-tile-attribution="{{ config('location.map_tile_attribution') }}"
        role="application" aria-label="Carte des restaurants Quick halal en France">
        <p class="editorial-quick-map-loading">Chargement de la carte des restaurants Quick halal…</p>
    </div>
    <div class="editorial-quick-map-cities">
        <h2 id="quick-map-title">Principales villes</h2>
        @if ($cities !== [])
            <ul>
                @foreach ($cities as $city)
                    <li><a href="{{ $city['url'] }}"><span>{{ $city['name'] }}</span><span>{{ $city['count'] }} restaurant{{ $city['count'] > 1 ? 's' : '' }}</span></a></li>
                @endforeach
            </ul>
        @endif
        <a class="editorial-quick-map-all-cities" href="{{ route('restaurants.index') }}">Voir toutes les villes <span aria-hidden="true">→</span></a>
    </div>
</section>
