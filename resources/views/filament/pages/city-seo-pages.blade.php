<x-filament-panels::page>
    {{ $this->table }}
    <form wire:submit="save" class="mt-8">
        <p class="mb-3">@if($cityName)Modification : <strong>{{ $cityName }}</strong>@else Sélectionnez « Modifier » dans le tableau pour personnaliser une ville.@endif</p>
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-6">Enregistrer</x-filament::button>
    </form>
</x-filament-panels::page>
