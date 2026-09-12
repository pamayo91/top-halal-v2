<x-layouts.app title="Adresse e-mail confirmée | Top Halal" robots="noindex,nofollow">
    <section class="section">
        <div class="shell submission-thanks">
            <p class="eyebrow">Adresse e-mail confirmée</p>
            <h1>{{ $alreadyConfirmed ? 'Votre adresse était déjà confirmée.' : 'Votre adresse e-mail est confirmée.' }}</h1>
            <p>Votre demande est maintenant en attente de vérification par l’équipe Top Halal. Le restaurant ne sera publié qu’après cette validation. Consultez aussi l’e-mail de confirmation pour activer votre espace et pouvoir adapter cette fiche.</p>
            <a class="button" href="{{ route('restaurants.index') }}">Voir les restaurants</a>
        </div>
    </section>
</x-layouts.app>
