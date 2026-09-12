<x-layouts.app title="Mot de passe oublié | Top Halal" :hide-flash="true">
    <section class="contact-page auth-page">
        <div class="shell auth-shell">
            <div class="contact-form-wrap auth-form-wrap">
                <aside class="contact-sticker"><span>Accès sécurisé</span></aside>
                <div class="contact-form-card auth-form-card">
                    <header>
                        <p class="eyebrow">Mot de passe oublié</p>
                        <h1>Retrouvez votre espace.</h1>
                        <p>Indiquez votre e-mail : si un compte existe, nous vous enverrons un lien de réinitialisation.</p>
                    </header>
                    @if(session('status'))<div class="auth-success" role="status">{{ session('status') }}</div>@endif
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Vérifiez votre adresse e-mail puis réessayez.</p></div>@endif
                    <form method="post" action="{{ route('password.email') }}" class="contact-form">
                        @csrf
                        <div class="contact-field"><label for="forgot-password-email">Votre e-mail</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="forgot-password-email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}" placeholder="Ex. votre@email.fr"></div>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                        <button class="button contact-submit" type="submit">Envoyer le lien <span aria-hidden="true">→</span></button>
                    </form>
                    <p class="auth-secondary"><a href="{{ route('login') }}">← Retour à la connexion</a></p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
