<x-layouts.app title="Revendiquer un restaurant">
    <h1>Revendiquer {{ $restaurant->name }}</h1>
    @if($claim)<p role="status">Une demande existe déjà : {{ $claim->status }}.</p>@else
    <p>Pour éviter les revendications frauduleuses, prenez une photo de votre pièce d’identité avec, à côté, une feuille sur laquelle vous avez écrit à la main :</p>
    <p><strong>Top Halal - {{ $restaurant->name }} - {{ now()->format('d/m/Y') }}</strong></p>
    <p>La demande sera vérifiée manuellement par l’équipe Top Halal.</p>
    <form method="post" enctype="multipart/form-data" action="{{ route('claims.store', $restaurant) }}">@csrf
        <label>Nom / prénom <input name="full_name" required maxlength="255" value="{{ old('full_name') }}"></label>
        <label>Société <input name="company" required maxlength="255" value="{{ old('company') }}"></label>
        <label>SIRET <input name="siret" required inputmode="numeric" maxlength="20" value="{{ old('siret') }}"></label>
        <label><input type="checkbox" name="certified" value="1" required @checked(old('certified'))> Je certifie être le propriétaire, le gérant ou être autorisé à gérer cet établissement.</label>
        <label>Photo de la pièce d’identité <input type="file" name="identity_document" accept="image/jpeg,image/png,image/webp" required></label>
        @foreach(['full_name','company','siret','certified','identity_document','claim'] as $field) @error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
        <label>Message pour la modération (facultatif) <textarea name="message" maxlength="1000">{{ old('message') }}</textarea></label>
        <button type="submit">Envoyer la demande</button>
    </form>@endif
</x-layouts.app>
