@php($restaurant = $getRecord())
@php($photos = collect($restaurant?->media ?? [])->filter(fn ($media) => $media->role !== 'fallback_thumbnail')->values())
@php($fallbacks = collect($restaurant?->media ?? [])->where('role', 'fallback_thumbnail')->values())

<div class="space-y-5">
    <p class="text-sm text-gray-600 dark:text-gray-300">Ajoutez des photos avec le bouton « Ajouter des photos » en haut de la fiche. La première photo est la couverture de la fiche et apparaît sur le site. Utilisez les boutons pour changer son ordre ou retirer une photo de cette fiche. Les aperçus conservent l’image entière.</p>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse($photos as $media)
        @if($asset = $media->asset)
            <figure class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @php($variant = $asset->variants->where('width', '>=', 480)->sortBy('width')->first() ?? $asset->variants->sortByDesc('width')->first())
                <div class="flex aspect-[4/3] items-center justify-center bg-gray-100 p-2 dark:bg-gray-800"><img src="{{ $asset->deliveryUrl($variant?->width) }}" width="{{ $variant?->width ?? $asset->width }}" height="{{ $variant?->height ?? $asset->height }}" loading="lazy" class="max-h-full max-w-full object-contain" alt="{{ $asset->alt_text ?: $restaurant->name }}"></div>
                <figcaption class="space-y-3 p-3 text-sm text-gray-600 dark:text-gray-300">
                    <div><strong class="text-gray-950 dark:text-white">{{ $loop->first ? 'Couverture · position 1' : 'Galerie · position '.($loop->index + 1) }}</strong>@if($asset->caption || $asset->alt_text)<span class="mt-1 block">{{ $asset->caption ?: $asset->alt_text }}</span>@endif</div>
                    <div class="flex gap-2" role="group" aria-label="Réorganiser la photo en position {{ $loop->index + 1 }}">
                        <button type="button" wire:click="moveRestaurantMedia({{ $media->id }}, 'up')" wire:loading.attr="disabled" @disabled($loop->first) class="fi-btn fi-btn-size-sm fi-btn-color-gray">Monter</button>
                        <button type="button" wire:click="moveRestaurantMedia({{ $media->id }}, 'down')" wire:loading.attr="disabled" @disabled($loop->last) class="fi-btn fi-btn-size-sm fi-btn-color-gray">Descendre</button>
                        <button type="button" wire:click="detachRestaurantMedia({{ $media->id }})" wire:confirm="Retirer cette photo de la fiche ? Elle restera disponible dans la médiathèque." wire:loading.attr="disabled" class="fi-btn fi-btn-size-sm fi-btn-color-danger">Retirer</button>
                    </div>
                </figcaption>
            </figure>
        @endif
    @empty
        <p class="text-sm text-gray-600 dark:text-gray-300">Aucune photo n’est associée à cette fiche.</p>
    @endforelse
    </div>

    @if($fallbacks->isNotEmpty())
        <div class="border-t border-gray-200 pt-5 dark:border-gray-700">
            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">Miniatures de spécialité : utilisées seulement lorsqu’une fiche n’a aucune photo, elles ne peuvent pas devenir une couverture.</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($fallbacks as $media)
                    @if($asset = $media->asset)
                        @php($variant = $asset->variants->where('width', '>=', 480)->sortBy('width')->first() ?? $asset->variants->sortByDesc('width')->first())
                        <figure class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"><div class="flex aspect-[4/3] items-center justify-center bg-gray-100 p-2 dark:bg-gray-800"><img src="{{ $asset->deliveryUrl($variant?->width) }}" width="{{ $variant?->width ?? $asset->width }}" height="{{ $variant?->height ?? $asset->height }}" loading="lazy" class="max-h-full max-w-full object-contain" alt="{{ $asset->alt_text ?: $restaurant->name }}"></div><figcaption class="p-3 text-sm text-gray-600 dark:text-gray-300">Miniature de spécialité (sans couverture)</figcaption></figure>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>
