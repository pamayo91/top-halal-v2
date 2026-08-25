<x-layouts.app title="Modération des revendications">
    <h1>Demandes en attente</h1>
    @forelse($claims as $claim)<article data-claim-id="{{ $claim->id }}"><h2>{{ $claim->restaurant->name }}</h2><p>Utilisateur : {{ $claim->user->name }}</p><p>{{ $claim->message }}</p>
        <form method="post" action="{{ route('admin.claims.approve', $claim) }}" style="display:inline">@csrf @method('PATCH')<button type="submit">Approuver</button></form>
        <form method="post" action="{{ route('admin.claims.reject', $claim) }}" style="display:inline">@csrf @method('PATCH')<label>Note <input name="admin_note"></label><button type="submit">Refuser</button></form>
    </article>@empty <p>Aucune demande en attente.</p>@endforelse
</x-layouts.app>
