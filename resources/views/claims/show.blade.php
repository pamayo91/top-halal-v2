<x-layouts.app title="Demande de revendication">
    <h1>Demande de revendication</h1>
    <p>Restaurant : {{ $claim->restaurant->name }}</p>
    <p data-claim-status>Statut : {{ $claim->status }}</p>
    @if($claim->admin_note)<p>Note de modération : {{ $claim->admin_note }}</p>@endif
</x-layouts.app>
