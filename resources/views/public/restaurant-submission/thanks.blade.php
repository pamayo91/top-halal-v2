<x-layouts.app title="Proposition envoyée | Top Halal" robots="noindex,nofollow">
    <section class="section">
        <div class="shell submission-thanks">
            <p class="eyebrow">Proposition envoyée</p>
            <h1>Merci pour votre aide !</h1>
            <p><b>{{ $restaurantName }}</b> est maintenant en attente de vérification. Il ne sera jamais publié automatiquement.</p>
            <p>Nous vous contacterons uniquement si un complément est nécessaire.</p>
            <a class="button" href="{{ route('restaurants.index') }}">Voir les restaurants</a>
        </div>
    </section>
</x-layouts.app>
