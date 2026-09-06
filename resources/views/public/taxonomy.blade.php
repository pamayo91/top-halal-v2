@php
    $cityPage = $kind === 'ville' ? ($citySeo ?? null) : null;
    $robots = request('page') || (isset($open) && ! $open) ? 'noindex,follow' : 'index,follow';
@endphp
<x-layouts.app title="{{ $title ?? ($cityPage?->config?->seo_title ?: ($term->name.' | Top Halal')) }}" description="{{ $description ?? $cityPage?->config?->seo_description }}" canonical="{{ url()->current() }}" robots="{{ $robots }}">
    <section class="page-header">
        <div class="shell">
            <x-breadcrumbs :items="$breadcrumbs" />
            <p class="eyebrow">Par {{ $kind }}</p>
            <h1>{{ $cityPage?->config?->h1 ?: 'Restaurants halal : '.$term->name }}</h1>
        </div>
    </section>
    <section class="section shell">
        @if(filled($cityPage?->config?->content_top))<div class="city-seo-content">{!! $cityPage->config->content_top !!}</div>@endif
        <p class="results-count">{{ $restaurants->total() }} résultat{{ $restaurants->total() > 1 ? 's' : '' }}</p>
        <div class="cards-grid">
            @forelse($restaurants as $restaurant)
                <x-restaurant-card :restaurant="$restaurant" />
            @empty
                <div class="empty-state"><h2>Aucune adresse disponible.</h2><a class="button" href="{{ route('restaurants.index') }}">Rechercher ailleurs</a></div>
            @endforelse
        </div>
        {{ $restaurants->links() }}
        @if($kind === 'ville' && ($citySpecialties ?? collect())->isNotEmpty())
            <section class="nearby-cities" aria-labelledby="city-specialties-title">
                <h2 id="city-specialties-title">Restaurants halal par spécialité à {{ $term->name }}</h2>
                <ul class="nearby-cities-grid">
                    @foreach($citySpecialties as $specialty)
                        <li><a href="{{ route('city-specialties.show', ['city' => $city->slug, 'facet' => $specialty->slug]) }}">{{ $specialty->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if($kind === 'ville' && ($cityServices ?? collect())->isNotEmpty())
            <section class="nearby-cities" aria-labelledby="city-services-title">
                <h2 id="city-services-title">Restaurants halal par service à {{ $term->name }}</h2>
                <ul class="nearby-cities-grid">
                    @foreach($cityServices as $service)
                        <li><a href="{{ route('city-specialties.show', ['city' => $city->slug, 'facet' => $service->slug]) }}">{{ $service->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if($kind === 'ville' && ($nearbyCities ?? collect())->isNotEmpty())
            <section class="nearby-cities" aria-labelledby="nearby-cities-title">
                <h2 id="nearby-cities-title">Villes aux alentours</h2>
                <ul class="nearby-cities-grid">
                    @foreach($nearbyCities as $nearbyCity)
                        <li><a href="{{ route('cities.show', $nearbyCity->slug) }}">{{ $nearbyCity->city_name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
        @if(filled($cityPage?->config?->content_bottom))<div class="city-seo-content">{!! $cityPage->config->content_bottom !!}</div>@endif
    </section>
</x-layouts.app>
