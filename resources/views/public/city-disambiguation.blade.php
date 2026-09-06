<x-layouts.app title="Restaurants halal : {{ $cityName }} | Top Halal" description="Choisissez la commune de {{ $cityName }} recherchée." canonical="{{ url()->current() }}" robots="noindex,follow">
    <section class="page-header">
        <div class="shell">
            <x-breadcrumbs :items="$breadcrumbs" />
            <h1>Restaurants halal : {{ $cityName }}</h1>
            <p>Choisissez la commune recherchée pour voir ses restaurants.</p>
        </div>
    </section>
    <section class="section shell">
        <ul class="link-list city-disambiguation-list">
            @foreach($cities as $city)
                <li><a href="{{ route('cities.show', $city->slug) }}">{{ $city->city_name }} — {{ $city->department['name'] }} ({{ $city->region['name'] }})</a></li>
            @endforeach
        </ul>
    </section>
</x-layouts.app>
