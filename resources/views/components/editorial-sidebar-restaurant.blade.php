@props(['restaurant'])
@php($asset = $restaurant->media->first(fn ($media) => $media->role !== 'fallback_thumbnail' && $media->asset?->isRestaurantImage())?->asset ?: $restaurant->media->first(fn ($media) => $media->role === 'fallback_thumbnail' && $media->asset?->isRestaurantImage())?->asset)
@php($variant = $asset?->variants->sortBy('width')->firstWhere('width', '>=', 480) ?? $asset?->variants->sortByDesc('width')->first())
<a class="sidebar-content-card sidebar-restaurant-card" href="{{ route('restaurants.show', $restaurant->slug) }}">
    <span class="sidebar-thumbnail">@if($asset)<img src="{{ $asset->deliveryUrl($variant?->width) }}" width="{{ $asset->width }}" height="{{ $asset->height }}" loading="lazy" alt="">@else<span aria-hidden="true">⌁</span>@endif</span>
    <span><strong>{{ $restaurant->name }}</strong>@if($restaurant->city_name)<small>{{ $restaurant->city_name }}</small>@endif</span><span class="sidebar-chevron" aria-hidden="true">›</span>
</a>
