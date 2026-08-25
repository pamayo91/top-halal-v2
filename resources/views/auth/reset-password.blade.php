<x-layouts.app title="Réinitialiser le mot de passe">
    <h1>Réinitialiser le mot de passe</h1>
    <form method="post" action="{{ route('password.store') }}">
        @csrf
        <input name="token" type="hidden" value="{{ $request->route('token') }}">
        <label>E-mail <input name="email" type="email" required autocomplete="email" value="{{ old('email', $request->email) }}"></label>
        <label>Nouveau mot de passe <input name="password" type="password" required minlength="12" autocomplete="new-password"></label>
        <label>Confirmation <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
        <button type="submit">Réinitialiser</button>
    </form>
</x-layouts.app>
