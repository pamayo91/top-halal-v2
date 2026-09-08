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
        <section class="sidebar-card sidebar-search"><h2>{{ $title }}</h2><x-restaurant-search :compact="true" /></section>
    @elseif($block['type'] === 'restaurants')
        @php($restaurants = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Restaurant::with('media.asset.variants')->where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get() : collect())
        @if($restaurants->isNotEmpty())<section class="sidebar-card sidebar-restaurants"><h2>{{ $title }}</h2><div class="sidebar-compact-list">@foreach($restaurants as $restaurant)<x-editorial-sidebar-restaurant :restaurant="$restaurant" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'articles')
        @php($articles = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Article::with(['featuredMedia.asset', 'contentMedia.asset'])->where('status','published')->whereIn('id', $manualIds($block))->whereKeyNot($content->id)->limit($block['limit'])->get() : \App\Models\Article::with(['featuredMedia.asset', 'contentMedia.asset'])->where('status','published')->whereKeyNot($isArticle ? $content->id : 0)->latest('published_at')->limit($block['limit'])->get())
        @if($articles->isNotEmpty())<section class="sidebar-card sidebar-articles"><h2>{{ $title }}</h2><div class="sidebar-compact-list">@foreach($articles as $article)<x-editorial-sidebar-article :article="$article" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'correction')
        <section class="sidebar-card sidebar-correction"><div class="sidebar-heading-icon" aria-hidden="true">✓</div><h2>{{ $title }}</h2>@if(session('editorial_report_submitted'))<p class="flash" role="status">Merci, votre signalement a été transmis.</p>@else<p class="muted">Un détail est à mettre à jour ? Dites-nous lequel.</p><details><summary>Nous le signaler <span aria-hidden="true">→</span></summary><form class="stack-form" method="post" action="{{ route('editorial.reports.store', $content->slug) }}">@csrf<label class="sr-only" for="report-{{ $placement }}">Votre signalement</label><textarea id="report-{{ $placement }}" name="message" maxlength="2000" required></textarea><input class="hp" name="website" tabindex="-1" autocomplete="off"><button class="button button-small">Envoyer</button></form></details>@endif</section>
    @elseif($block['type'] === 'near_me')
        <section class="sidebar-card sidebar-near-me"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.25"/></svg><h2>{{ $title }}</h2><p>Découvrez les restaurants halal proches de vous.</p><a class="button button-small" data-near-me-cta href="{{ route('restaurants.index') }}#recherche">Trouver autour de moi <span aria-hidden="true">→</span></a></section>
    @elseif($block['type'] === 'featured')
        @php($featured = \App\Models\Restaurant::with('media.asset.variants')->where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get())
        @if($featured->isNotEmpty())<section class="sidebar-card sidebar-restaurants"><h2>{{ $title }}</h2><div class="sidebar-compact-list">@foreach($featured as $restaurant)<x-editorial-sidebar-restaurant :restaurant="$restaurant" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'explore')
        @php($links = collect(preg_split('/\R/', (string) ($block['links'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($line) => array_map('trim', explode('|', $line, 2)))->filter(fn ($link) => count($link) === 2 && str_starts_with($link[1], '/')))
        @if($links->isNotEmpty())<section class="sidebar-card sidebar-explore"><h2>{{ $title }}</h2><div class="sidebar-explore-links">@foreach($links as [$label, $url])<a href="{{ url($url) }}">{{ $label }} <span aria-hidden="true">→</span></a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'share')
        <section class="sidebar-card sidebar-share"><h2>{{ $title ?: ($isArticle ? 'Partager cet article' : 'Partager cette page') }}</h2><div class="share-links"><a aria-label="Partager sur Facebook" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer"><span aria-hidden="true">f</span></a><a aria-label="Partager sur X" href="https://twitter.com/intent/tweet?url={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer"><span aria-hidden="true">𝕏</span></a><a aria-label="Partager par e-mail" href="mailto:?subject={{ rawurlencode($content->title) }}&body={{ urlencode(route('editorial.show', $content->slug)) }}"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a></div></section>
    @elseif($block['type'] === 'contact')
        <section class="sidebar-card sidebar-contact"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 3V5Z"/><path d="M8 10h8M8 13h5"/></svg><h2>{{ $title }}</h2><p>Une question sur Top-Halal ?</p><a href="{{ url('/contact') }}">Nous contacter <span aria-hidden="true">→</span></a></section>
    @endif
@endforeach
</aside>
@endif
