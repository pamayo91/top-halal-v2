@php($featuredAsset = $isArticle ? $content->featuredMedia?->asset : null)
@php($schema = ['@context' => 'https://schema.org', '@type' => $isArticle ? 'Article' : 'WebPage', 'headline' => $content->title, 'datePublished' => optional($content->legacy_published_at)->toIso8601String(), ...($featuredAsset ? ['image' => $featuredAsset->deliveryUrl()] : [])])
<x-layouts.app title="{{ $content->seo_title ?: $content->title }} | Top Halal" description="{{ $content->seo_description }}" canonical="{{ route('editorial.show', $content->slug) }}" robots="{{ $content->seo_robots ?: 'index,follow' }}" :admin-edit-url="$adminEditUrl"><x-slot:head><script type="application/ld+json">@json($schema)</script>@if($sidebar['quickMap'] && ! app()->environment('testing')) @vite('resources/js/quick-restaurants-map.js') @endif</x-slot:head>
<article class="editorial shell {{ $sidebar['enabled'] ? 'editorial-with-sidebar' : '' }}">
    <div class="editorial-main">
        <nav class="breadcrumbs" aria-label="Fil d’Ariane"><a href="{{ route('home') }}">Accueil</a>@if($isArticle)<span>/</span><a href="{{ route('blog.index') }}">Le guide</a>@endif<span>/</span><span aria-current="page">{{ $content->title }}</span></nav>
        <header><p class="eyebrow accented-eyebrow">{{ $isArticle ? 'Article' : 'Information' }}</p><h1>{{ $content->title }}</h1>@if($content->legacy_published_at)<p class="muted">Publié le {{ $content->legacy_published_at->translatedFormat('j F Y') }}</p>@endif</header>
        <x-editorial-sidebar :content="$content" :sidebar="$sidebar" :is-article="$isArticle" placement="mobile-top" />
        @if($featuredAsset)<figure class="article-featured-media"><img src="{{ $featuredAsset->deliveryUrl() }}" width="{{ $featuredAsset->width }}" height="{{ $featuredAsset->height }}" fetchpriority="high" alt="{{ $featuredAsset->alt_text ?: $content->title }}"></figure>@endif
        <div class="prose">{!! $sidebar['html'] !!}</div>
        @if($content->comments_enabled || $commentThreads->isNotEmpty())<section class="comments" id="commentaires">
            <h2>Commentaires ({{ $visibleCommentsCount }})</h2>
            @if(session('comment_submitted'))<p class="flash" role="status">Merci, votre commentaire sera publié après modération.</p>@elseif(session('contribution_verification_sent'))<p class="flash" role="status">Vérifiez votre adresse e-mail pour envoyer votre commentaire à la modération.</p>@endif
            <div id="comment-threads" data-comment-threads>@forelse($commentThreads as $comment)@include('public.partials.comment', ['comment' => $comment, 'content' => $content, 'isReply' => false, 'replyTo' => null])@empty <p>Pas encore de commentaire.</p>@endforelse</div>
            @if($commentThreads->hasMorePages())<p class="comment-load-more"><a class="button button-secondary" data-comments-load-more href="{{ $commentThreads->nextPageUrl().'#commentaires' }}">Afficher 20 commentaires de plus <span class="sr-only">(page suivante)</span></a><span class="muted" data-comments-remaining>{{ $visibleCommentsCount - $commentThreads->count() }} commentaires restants</span></p>@endif
            @if($content->comments_enabled)
                <details><summary>Laisser un commentaire</summary><form class="stack-form" method="post" action="{{ route('editorial.comments.store', $content->slug) }}">@csrf<label>Nom <input name="name" required value="{{ old('name', auth()->user()?->name) }}"></label>@guest<label>E-mail <input name="email" type="email" required value="{{ old('email') }}"></label>@endguest<label>Commentaire <textarea name="content" required maxlength="2000">{{ old('content') }}</textarea></label><input class="hp" name="website" tabindex="-1" autocomplete="off"><button class="button">Envoyer pour modération</button></form></details>
            @endif
        </section>@endif
        <x-editorial-sidebar :content="$content" :sidebar="$sidebar" :is-article="$isArticle" placement="mobile-bottom" />
    </div>
    @if($sidebar['enabled'])<x-editorial-sidebar :content="$content" :sidebar="$sidebar" :is-article="$isArticle" />@endif
</article>
</x-layouts.app>
