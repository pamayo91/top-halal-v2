<x-layouts.app title="{{ $title }}" description="{{ $description }}" canonical="{{ url()->current() }}" robots="{{ request('page') ? 'noindex,follow' : 'index,follow' }}">
    <section class="page-header">
        <div class="shell">
            <x-breadcrumbs :items="$breadcrumbs" />
            <p class="eyebrow">Par spécialité</p>
            <h1>{{ $h1 }}</h1>
        </div>
    </section>
    <section class="section shell">
        @if(filled($facet->content_top))<div class="city-seo-content">{!! $facet->content_top !!}</div>@endif
        <p class="results-count">{{ $restaurants->total() }} résultat{{ $restaurants->total() > 1 ? 's' : '' }}</p>
        <div class="cards-grid">
            @forelse($restaurants as $restaurant)<x-restaurant-card :restaurant="$restaurant" />
            @empty <div class="empty-state"><h2>Aucune adresse disponible.</h2><a class="button" href="{{ route('cities.show', $city->slug) }}">Voir les restaurants de {{ $city->city_name }}</a></div>
            @endforelse
        </div>
        {{ $restaurants->links() }}
        @if(filled($facet->content_bottom))<div class="city-seo-content">{!! $facet->content_bottom !!}</div>@endif
    </section>
</x-layouts.app>
