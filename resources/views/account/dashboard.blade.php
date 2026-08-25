<x-layouts.app title="Mon compte">
    <h1>Mon compte</h1>
    <p>Statut : {{ auth()->user()->status }}. Rôle : {{ auth()->user()->role }}.</p>
    <p><a href="{{ route('password.change') }}">Changer le mot de passe</a></p>
    <h2>Mes restaurants</h2>
    @forelse($restaurants as $restaurant)<article><h3>{{ $restaurant->name }}</h3><a href="{{ route('owner.restaurants.edit', $restaurant) }}">Modifier</a></article>@empty <p>Aucun restaurant approuvé.</p>@endforelse
    @if(auth()->user()->role === 'admin')<p><a href="{{ route('admin.claims.index') }}">Demandes de revendication</a></p>@endif
</x-layouts.app>
