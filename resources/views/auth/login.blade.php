<x-layouts.app title="Connexion">
    <h1>Connexion</h1>
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <label>E-mail <input name="email" type="email" required autocomplete="email" value="{{ old('email') }}"></label>
        <label>Mot de passe <input name="password" type="password" required autocomplete="current-password"></label>
        <label><input name="remember" type="checkbox" value="1"> Rester connecté</label>
        <button type="submit">Se connecter</button>
    </form>
    <p><a href="{{ route('password.request') }}">Mot de passe oublié</a></p>
</x-layouts.app>
