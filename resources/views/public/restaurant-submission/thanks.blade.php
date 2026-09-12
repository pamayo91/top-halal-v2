<x-layouts.app title="Proposition envoyée | Top Halal" robots="noindex,nofollow">
    <section class="section">
        <div class="shell submission-thanks">
            <p class="eyebrow">Proposition envoyée</p>
            <h1>Merci pour votre aide !</h1>
            <p><b>{{ $restaurantName }}</b> attend d’abord la confirmation de votre adresse e-mail. Il ne sera jamais publié automatiquement.</p>
            <p>Consultez votre boîte e-mail pour confirmer votre adresse, puis notre équipe pourra examiner la proposition.</p>
            <a class="button" href="{{ route('restaurants.index') }}">Voir les restaurants</a>
        </div>
    </section>
</x-layouts.app>
