<x-layouts.app title="Gestion de fiche indisponible | Top Halal">
    <section class="contact-page auth-page">
        <div class="shell auth-shell">
            <div class="contact-form-wrap auth-form-wrap">
                <div class="contact-form-card auth-form-card">
                    <p class="eyebrow">Gestion de fiche</p>
                    @if($managementTransferred)
                        <h1>Cette fiche n’est plus gérée depuis votre espace.</h1>
                        <p>La gestion de « {{ $restaurant->name }} » a été transférée au gérant dont la revendication a été approuvée.</p>
                        <p>Votre proposition initiale reste conservée dans notre historique, mais elle ne donne plus accès à la modification de cette fiche.</p>
                    @else
                        <h1>Vous ne pouvez pas gérer cette fiche.</h1>
                        <p>Vous ne disposez pas de l’autorisation nécessaire pour modifier « {{ $restaurant->name }} » ou demander sa suppression.</p>
                    @endif
                    <p>Si vous pensez qu’il s’agit d’une erreur, contactez l’équipe Top Halal avec les éléments utiles.</p>
                    <p><a class="button" href="{{ route('contact.create') }}">Contester ou nous contacter</a></p>
                    <p><a href="{{ route('account.dashboard') }}">Retour à mon compte</a></p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
