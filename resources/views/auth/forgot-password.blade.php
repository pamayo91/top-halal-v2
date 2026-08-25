<x-layouts.app title="Mot de passe oublié">
    <h1>Mot de passe oublié</h1>
    <form method="post" action="{{ route('password.email') }}">
        @csrf
        <label>E-mail <input name="email" type="email" required autocomplete="email" value="{{ old('email') }}"></label>
        <button type="submit">Envoyer le lien</button>
    </form>
</x-layouts.app>
