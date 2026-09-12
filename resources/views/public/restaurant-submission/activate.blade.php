<x-layouts.app title="Activer mon espace | Top Halal" robots="noindex,nofollow">
    <section class="contact-page">
        <div class="shell">
            <div class="contact-form-wrap">
                <div class="contact-form-card">
                    <p class="eyebrow">Votre espace</p>
                    <h1>Activer mon espace</h1>
                    <p>Choisissez votre mot de passe pour gérer la fiche « {{ $submission->restaurant->name }} ».</p>
                    <form method="post" action="{{ route('restaurant-submissions.activate.store', [$submission, $token]) }}">
                        @csrf
                        <label>Mot de passe <input type="password" name="password" required autocomplete="new-password"></label>
                        <label>Confirmation du mot de passe <input type="password" name="password_confirmation" required autocomplete="new-password"></label>
                        @error('password')<p class="field-error">{{ $message }}</p>@enderror
                        <button class="button contact-submit" type="submit">Activer mon espace</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
