<x-layouts.app :title="'Revendiquer '. $restaurant->name .' - Top Halal'">
    <section class="contact-page claim-auth-page">
        <div class="shell claim-auth-layout">
            <div class="contact-form-wrap claim-auth-wrap">
                <div class="contact-form-card claim-auth-card">
                    <header>
                        <h1>Revendiquer ce restaurant</h1>
                        <p class="claim-auth-restaurant">Établissement sélectionné : <strong>{{ $restaurant->name }}</strong></p>
                        <p>Pour revendiquer cet établissement, vous devez disposer d’un compte Top Halal.</p>
                        <p>Cela nous permet de rattacher votre demande à votre compte et de vous donner accès à la fiche après validation.</p>
                    </header>
                    <div class="claim-auth-actions">
                        <a class="button contact-submit" href="{{ route('claims.login', $restaurant) }}">Se connecter</a>
                        <a class="button button-secondary contact-submit" href="{{ route('claims.register', $restaurant) }}">Créer un compte</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
