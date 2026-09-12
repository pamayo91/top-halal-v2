<x-layouts.app title="Connexion | Top Halal" :hide-flash="true">
    <section class="contact-page auth-page">
        <div class="shell auth-shell">
            <div class="contact-form-wrap auth-form-wrap">
                <aside class="contact-sticker"><span>Votre espace personnel</span></aside>
                <div class="contact-form-card auth-form-card">
                    <header>
                        <p class="eyebrow">Mon compte</p>
                        <h1>Content de vous revoir.</h1>
                        <p>Connectez-vous pour gérer vos fiches et suivre vos demandes.</p>
                    </header>
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Vérifiez vos identifiants puis réessayez.</p></div>@endif
                    <form method="post" action="{{ route('login.store') }}" class="contact-form">
                        @csrf
                        <div class="contact-field"><label for="login-email">Votre e-mail</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="login-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="Ex. votre@email.fr"></div>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                        <div class="contact-field"><label for="login-password">Mot de passe</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="login-password" name="password" type="password" required autocomplete="current-password" placeholder="Votre mot de passe"></div>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
                        <label class="auth-remember"><input name="remember" type="checkbox" value="1" @checked(old('remember'))><span>Rester connecté</span></label>
                        <button class="button contact-submit" type="submit">Se connecter <span aria-hidden="true">→</span></button>
                    </form>
                    <p class="auth-secondary"><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
