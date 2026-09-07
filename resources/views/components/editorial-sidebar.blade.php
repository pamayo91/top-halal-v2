@props(['content', 'sidebar', 'isArticle' => false, 'placement' => 'desktop'])
@php
    $blocks = collect($sidebar['blocks'])->where('enabled', true);
    $blocks = match ($placement) {'mobile-top' => $blocks->where('type', 'toc'), 'mobile-bottom' => $blocks->reject(fn ($block) => $block['type'] === 'toc'), default => $blocks};
    $manualIds = fn ($block) => collect(preg_split('/[,\s]+/', (string) ($block['ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($id) => (int) $id)->filter()->values();
@endphp
@if($blocks->isNotEmpty())
<aside class="editorial-sidebar editorial-sidebar-{{ $placement }}" aria-label="Compléments de lecture">
@foreach($blocks as $block)
    @php($title = $block['title'] ?: app(\App\Services\EditorialSidebar::class)->defaultBlock($block['type'])['title'])
    @if($block['type'] === 'toc' && count($sidebar['toc']) >= 2)
        <section class="sidebar-card sidebar-toc"><h2>{{ $title }}</h2><ol>@foreach($sidebar['toc'] as $heading)<li class="toc-level-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a></li>@endforeach</ol></section>
    @elseif($block['type'] === 'search')
        <section class="sidebar-card"><h2>{{ $title }}</h2><x-restaurant-search :compact="true" /></section>
    @elseif($block['type'] === 'restaurants')
        @php($restaurants = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Restaurant::where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get() : collect())
        @if($restaurants->isNotEmpty())<section class="sidebar-card"><h2>{{ $title }}</h2><div class="sidebar-list">@foreach($restaurants as $restaurant)<a href="{{ route('restaurants.show', $restaurant->slug) }}">{{ $restaurant->name }}@if($restaurant->city_name)<small>{{ $restaurant->city_name }}</small>@endif</a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'articles')
        @php($articles = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Article::where('status','published')->whereIn('id', $manualIds($block))->whereKeyNot($content->id)->limit($block['limit'])->get() : \App\Models\Article::where('status','published')->whereKeyNot($isArticle ? $content->id : 0)->latest('published_at')->limit($block['limit'])->get())
        @if($articles->isNotEmpty())<section class="sidebar-card"><h2>{{ $title }}</h2><div class="sidebar-list">@foreach($articles as $article)<a href="{{ route('editorial.show', $article->slug) }}">{{ $article->title }}</a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'correction')
        <section class="sidebar-card"><h2>{{ $title }}</h2>@if(session('editorial_report_submitted'))<p class="flash" role="status">Merci, votre signalement a été transmis.</p>@else<p class="muted">Un détail est à mettre à jour ? Dites-nous lequel.</p><details><summary>Nous le signaler</summary><form class="stack-form" method="post" action="{{ route('editorial.reports.store', $content->slug) }}">@csrf<label class="sr-only" for="report-{{ $placement }}">Votre signalement</label><textarea id="report-{{ $placement }}" name="message" maxlength="2000" required></textarea><input class="hp" name="website" tabindex="-1" autocomplete="off"><button class="button button-small">Envoyer</button></form></details>@endif</section>
    @elseif($block['type'] === 'near_me')
        <section class="sidebar-card sidebar-cta"><h2>{{ $title }}</h2><p>Découvrez les restaurants halal proches de vous.</p><x-restaurant-search :compact="true" /></section>
    @elseif($block['type'] === 'featured')
        @php($featured = \App\Models\Restaurant::where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get())
        @if($featured->isNotEmpty())<section class="sidebar-card"><h2>{{ $title }}</h2><div class="sidebar-list">@foreach($featured as $restaurant)<a href="{{ route('restaurants.show', $restaurant->slug) }}">{{ $restaurant->name }}</a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'explore')
        @php($links = collect(preg_split('/\R/', (string) ($block['links'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($line) => array_map('trim', explode('|', $line, 2)))->filter(fn ($link) => count($link) === 2 && str_starts_with($link[1], '/')))
        @if($links->isNotEmpty())<section class="sidebar-card"><h2>{{ $title }}</h2><div class="sidebar-list">@foreach($links as [$label, $url])<a href="{{ url($url) }}">{{ $label }}</a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'share')
        <section class="sidebar-card"><h2>{{ $title ?: ($isArticle ? 'Partager cet article' : 'Partager cette page') }}</h2><div class="share-links"><a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer">Facebook</a><a href="https://twitter.com/intent/tweet?url={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer">X</a><a href="mailto:?subject={{ rawurlencode($content->title) }}&body={{ urlencode(route('editorial.show', $content->slug)) }}">E-mail</a></div></section>
    @elseif($block['type'] === 'contact')
        <section class="sidebar-card sidebar-cta"><h2>{{ $title }}</h2><p>Une question sur Top-Halal ?</p><a class="button button-small" href="{{ url('/contact') }}">Nous contacter</a></section>
    @endif
@endforeach
</aside>
@endif
