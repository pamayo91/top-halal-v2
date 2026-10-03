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
        <section class="sidebar-card sidebar-toc" @if($placement === 'desktop') data-sticky-toc @endif><div class="sidebar-toc-header"><p class="sidebar-title">{{ $title }}</p><button class="sidebar-toc-collapse" type="button" data-toc-collapse hidden>Réduire <span aria-hidden="true">↑</span></button></div><div class="sidebar-toc-current" data-toc-current hidden><span class="sr-only">Section actuellement lue : </span><a data-toc-current-link href="#{{ $sidebar['toc'][0]['id'] }}">{{ $sidebar['toc'][0]['label'] }}</a></div><button class="sidebar-toc-toggle" type="button" data-toc-toggle aria-expanded="false" hidden>Afficher le sommaire</button><ol data-toc-list>@foreach($sidebar['toc'] as $heading)<li class="toc-level-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a></li>@endforeach</ol></section>
    @elseif($block['type'] === 'search')
        <section class="sidebar-card sidebar-search"><p class="sidebar-title">{{ $title }}</p><x-restaurant-search :compact="true" /></section>
    @elseif($block['type'] === 'restaurants')
        @php($restaurants = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Restaurant::with('media.asset.variants')->where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get() : collect())
        @if($restaurants->isNotEmpty())<section class="sidebar-card sidebar-restaurants"><p class="sidebar-title">{{ $title }}</p><div class="sidebar-compact-list">@foreach($restaurants as $restaurant)<x-editorial-sidebar-restaurant :restaurant="$restaurant" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'articles')
        @php($articles = ($block['mode'] ?? 'auto') === 'manual' ? \App\Models\Article::with(['featuredMedia.asset.variants', 'contentMedia.asset.variants'])->where('status','published')->whereIn('id', $manualIds($block))->whereKeyNot($content->id)->limit($block['limit'])->get() : \App\Models\Article::with(['featuredMedia.asset.variants', 'contentMedia.asset.variants'])->where('status','published')->whereKeyNot($isArticle ? $content->id : 0)->latest('published_at')->limit($block['limit'])->get())
        @if($articles->isNotEmpty())<section class="sidebar-card sidebar-articles"><p class="sidebar-title">{{ $title }}</p><div class="sidebar-compact-list">@foreach($articles as $article)<x-editorial-sidebar-article :article="$article" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'correction')
        <section class="sidebar-card sidebar-correction"><div class="sidebar-heading-icon" aria-hidden="true">✓</div><p class="sidebar-title">{{ $title }}</p>
        @if(session('content_report_submitted'))<p class="flash" role="status">Merci, votre signalement a été transmis à Top Halal.</p>
        @elseif(session('content_report_verification_sent'))<p class="flash" role="status">Vérifiez votre adresse e-mail pour transmettre votre signalement.</p>
        @else
        <p class="muted">Un détail est à mettre à jour ? Dites-nous lequel.</p><details><summary>Nous le signaler <span aria-hidden="true">→</span></summary><form class="stack-form" method="post" action="{{ route('editorial.reports.store', $content->slug) }}">
        @csrf
        @if(auth()->check())
        <p class="muted">Signalement envoyé avec le compte {{ auth()->user()->email }}.</p>
        @else
        <label for="report-email-{{ $placement }}">E-mail <input id="report-email-{{ $placement }}" name="email" type="email" required value="{{ old('email') }}"></label>
        @endif
        <label class="sr-only" for="report-{{ $placement }}">Votre signalement</label><textarea id="report-{{ $placement }}" name="message" maxlength="2000" required>{{ old('message') }}</textarea><input class="hp" name="website" tabindex="-1" autocomplete="off"><button class="button button-small">Envoyer</button></form></details>
        @endif
        </section>
    @elseif($block['type'] === 'near_me')
        <section class="sidebar-card sidebar-near-me"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-12a7 7 0 1 0-14 0c0 6.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2.25"/></svg><p class="sidebar-title">{{ $title }}</p><p>Découvrez les restaurants halal proches de vous.</p><a class="button button-small" href="{{ route('restaurants.index', ['near_me' => 1]) }}">Trouver autour de moi <span aria-hidden="true">→</span></a></section>
    @elseif($block['type'] === 'featured')
        @php($featured = \App\Models\Restaurant::with('media.asset.variants')->where('status','published')->whereIn('id', $manualIds($block))->limit($block['limit'])->get())
        @if($featured->isNotEmpty())<section class="sidebar-card sidebar-restaurants"><p class="sidebar-title">{{ $title }}</p><div class="sidebar-compact-list">@foreach($featured as $restaurant)<x-editorial-sidebar-restaurant :restaurant="$restaurant" />@endforeach</div></section>@endif
    @elseif($block['type'] === 'explore')
        @php($links = collect(preg_split('/\R/', (string) ($block['links'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($line) => array_map('trim', explode('|', $line, 2)))->filter(fn ($link) => count($link) === 2 && str_starts_with($link[1], '/')))
        @if($links->isNotEmpty())<section class="sidebar-card sidebar-explore"><p class="sidebar-title">{{ $title }}</p><div class="sidebar-explore-links">@foreach($links as [$label, $url])<a href="{{ url($url) }}">{{ $label }} <span aria-hidden="true">→</span></a>@endforeach</div></section>@endif
    @elseif($block['type'] === 'share')
        <section class="sidebar-card sidebar-share"><p class="sidebar-title">{{ $title ?: ($isArticle ? 'Partager cet article' : 'Partager cette page') }}</p><div class="share-links"><a class="share-link share-link-facebook" aria-label="Partager sur Facebook" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer"><svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" d="M13.5 22v-8h3l.5-3h-3V9.5c0-.87.28-1.5 1.78-1.5H17V5.14c-.29-.04-1.29-.14-2.49-.14-2.46 0-4.15 1.5-4.15 4.26V11H7.5v3h2.86v8h3.14z"/></svg></a><a class="share-link share-link-x" aria-label="Partager sur X" href="https://twitter.com/intent/tweet?url={{ urlencode(route('editorial.show', $content->slug)) }}" rel="noopener noreferrer"><svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="currentColor" d="M14.234 10.162L22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299l-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg></a><a class="share-link share-link-email" aria-label="Partager par e-mail" href="mailto:?subject={{ rawurlencode($content->title) }}&body={{ urlencode(route('editorial.show', $content->slug)) }}"><svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2.1"/><path d="M4 7L12 13L20 7" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></section>
    @elseif($block['type'] === 'contact')
        <section class="sidebar-card sidebar-contact"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 3V5Z"/><path d="M8 10h8M8 13h5"/></svg><p class="sidebar-title">{{ $title }}</p><p>Une question sur Top-Halal ?</p><a href="{{ url('/contact') }}">Nous contacter <span aria-hidden="true">→</span></a></section>
    @endif
@endforeach
</aside>
@endif
