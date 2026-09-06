<x-filament-panels::page>
    <div class="max-w-xs"><label for="facet-type" class="text-sm font-medium">Type de facette</label><select id="facet-type" wire:model.live="facetType" class="fi-select-input block w-full"><option value="specialty">Spécialité</option><option value="service">Service</option></select></div>
    {{ $this->table }}
    <form id="facet-seo-editor" wire:submit="save" class="mt-8" tabindex="-1">
        <p class="mb-3">@if($cityName)Modification : <strong>{{ $cityName }} + {{ $facetName }}</strong>@else Sélectionnez « Modifier » dans le tableau pour configurer une facette.@endif</p>
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-6">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
