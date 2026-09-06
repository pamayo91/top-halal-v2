<x-filament-panels::page>
    {{ $this->table }}
    <form id="facet-seo-editor" wire:submit="save" class="mt-8" tabindex="-1">
        <p class="mb-3">@if($cityName)Modification : <strong>{{ $cityName }} + {{ $specialtyName }}</strong>@else Sélectionnez « Modifier » dans le tableau pour configurer une facette.@endif</p>
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-6">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
