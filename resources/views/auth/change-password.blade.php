<x-layouts.app title="Changer le mot de passe">
    <h1>Choisissez votre mot de passe</h1>
    @if(auth()->user()->must_change_password)<p>Le changement de mot de passe est obligatoire avant d’accéder au compte.</p>@endif
    <form method="post" action="{{ route('password.change.store') }}">
        @csrf @method('PUT')
        <label>Mot de passe actuel <input name="current_password" type="password" required autocomplete="current-password"></label>
        <label>Nouveau mot de passe <input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirmation <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        <button type="submit">Mettre à jour</button>
    </form>
</x-layouts.app>
