<x-layouts.app title="Changer le mot de passe | Top Halal" :hide-flash="true">
    <section class="contact-page auth-page">
        <div class="shell auth-shell">
            <div class="contact-form-wrap auth-form-wrap">
                <aside class="contact-sticker"><span>Accès sécurisé</span></aside>
                <div class="contact-form-card auth-form-card">
                    <header>
                        <p class="eyebrow">Sécurité du compte</p>
                        <h1>Choisissez votre mot de passe.</h1>
                        <p>@if(auth()->user()->must_change_password)Le changement est nécessaire avant d’accéder à votre compte.@else Mettez à jour votre mot de passe en toute sécurité.@endif</p>
                    </header>
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Vérifiez les informations saisies puis réessayez.</p></div>@endif
                    <form method="post" action="{{ route('password.change.store') }}" class="contact-form">
                        @csrf @method('PUT')
                        <div class="contact-field"><label for="current-password">Mot de passe actuel</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="current-password" name="current_password" type="password" required autocomplete="current-password" placeholder="Votre mot de passe actuel"></div>@error('current_password')<p class="field-error">{{ $message }}</p>@enderror</div>
                        <div class="contact-field"><label for="new-password">Nouveau mot de passe</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="new-password" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Au moins 12 caractères"></div>@error('password')<p class="field-error">{{ $message }}</p>@enderror</div>
                        <div class="contact-field"><label for="password-confirmation">Confirmation du nouveau mot de passe</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="password-confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password" placeholder="Répétez votre nouveau mot de passe"></div></div>
                        <button class="button contact-submit" type="submit">Mettre à jour <span aria-hidden="true">→</span></button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
