@php($restaurant = $getRecord())

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse($restaurant?->media ?? [] as $media)
        @if($asset = $media->asset)
            <figure class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <img src="{{ $asset->deliveryUrl(480) }}" width="{{ $asset->width }}" height="{{ $asset->height }}" loading="lazy" class="aspect-[4/3] w-full object-cover" alt="{{ $asset->alt_text ?: $restaurant->name }}">
                @if($asset->caption || $asset->alt_text)
                    <figcaption class="p-3 text-sm text-gray-600 dark:text-gray-300">{{ $asset->caption ?: $asset->alt_text }}</figcaption>
                @endif
            </figure>
        @endif
    @empty
        <p class="text-sm text-gray-600 dark:text-gray-300">Aucune photo n’est associée à cette fiche.</p>
    @endforelse
</div>
