<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Top Halal V2' }}</title>
</head>
<body>
<main>
    <nav aria-label="Navigation du compte">
        <a href="{{ route('home') }}">Accueil</a>
        @auth
            <a href="{{ route('account.dashboard') }}">Compte</a>
            <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf <button type="submit">Déconnexion</button></form>
        @else
            <a href="{{ route('login') }}">Connexion</a>
            <a href="{{ route('register') }}">Inscription</a>
        @endauth
    </nav>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    {{ $slot }}
</main>
</body>
</html>
