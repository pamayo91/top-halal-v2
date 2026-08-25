<x-layouts.app title="Inscription">
    <h1>Créer un compte</h1>
    <form method="post" action="{{ route('register.store') }}">
        @csrf
        <label>Nom <input name="name" required maxlength="100" autocomplete="name" value="{{ old('name') }}"></label>
        <label>E-mail <input name="email" type="email" required autocomplete="email" value="{{ old('email') }}"></label>
        <label>Mot de passe <input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirmation du mot de passe <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        <button type="submit">Créer mon compte</button>
    </form>
</x-layouts.app>
