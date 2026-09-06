@props(['items'])
@php($structuredItems = collect($items)->values()->map(fn (array $item, int $index): array => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $item['label'], 'item' => $item['url'] ?? url()->current()]))
<nav class="breadcrumbs" aria-label="Fil d’Ariane">
    @foreach($items as $item)
        @if(! $loop->first)<span aria-hidden="true">/</span>@endif
        @if($item['url'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else<span aria-current="page">{{ $item['label'] }}</span>@endif
    @endforeach
</nav>
<script type="application/ld+json">{!! json_encode(['@'.'context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $structuredItems], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
