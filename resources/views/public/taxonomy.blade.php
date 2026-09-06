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
        @if(filled($cityPage?->config?->content_bottom))<div class="city-seo-content">{!! $cityPage->config->content_bottom !!}</div>@endif
    </section>
</x-layouts.app>
