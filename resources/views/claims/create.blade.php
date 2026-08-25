<x-layouts.app title="Revendiquer un restaurant">
    <h1>Revendiquer {{ $restaurant->name }}</h1>
    @if($claim)<p role="status">Une demande existe déjà : {{ $claim->status }}.</p>@else
    <form method="post" action="{{ route('claims.store', $restaurant) }}">@csrf
        <label>Message pour la modération <textarea name="message" maxlength="1000">{{ old('message') }}</textarea></label>
        <button type="submit">Envoyer la demande</button>
    </form>@endif
</x-layouts.app>
