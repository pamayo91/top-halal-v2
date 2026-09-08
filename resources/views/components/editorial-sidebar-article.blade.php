@props(['article'])
@php($asset = $article->featuredMedia?->asset ?: $article->contentMedia->first(fn ($media) => $media->role === 'inline' && $media->asset)?->asset)
<a class="sidebar-content-card sidebar-article-card" href="{{ route('editorial.show', $article->slug) }}">
    <span class="sidebar-thumbnail">@if($asset)<img src="{{ $asset->deliveryUrl(320) }}" width="{{ $asset->width }}" height="{{ $asset->height }}" loading="lazy" alt="">@else<span aria-hidden="true">⌁</span>@endif</span>
    <span><strong>{{ $article->title }}</strong>@if($article->legacy_published_at)<small>{{ $article->legacy_published_at->translatedFormat('j F Y') }}</small>@endif</span>
</a>
