<x-layouts.app title="Mon compte">
    <section class="account-page">
        <div class="shell account-shell">
            <header class="account-header">
                <div>
                    <p class="eyebrow">Espace personnel</p>
                    <h1>Mon compte</h1>
                    <p class="account-welcome">Bonjour{{ auth()->user()->name ? ' '.auth()->user()->name : '' }}. Retrouvez ici les fiches que vous pouvez gérer.</p>
                </div>
                <div class="account-identity">
                    <span class="account-profile-badge">{{ $profileLabel }}</span>
                    <span>{{ auth()->user()->email }}</span>
                </div>
            </header>

            <nav class="account-nav" aria-label="Navigation de mon compte">
                <a href="#mes-restaurants">Mes restaurants</a>
                <a href="#securite">Mon profil et sécurité</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Déconnexion</button>
                </form>
            </nav>

            <section id="mes-restaurants" class="account-restaurants" aria-labelledby="mes-restaurants-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Mes établissements</p>
                        <h2 id="mes-restaurants-title">Mes restaurants</h2>
                    </div>
                    <a class="button button-secondary" href="{{ route('restaurant-submissions.create') }}">Ajouter un restaurant</a>
                </div>

                @forelse($restaurants as $restaurant)
                    @php($media = $restaurant->media->first(fn ($item) => $item->role !== 'fallback_thumbnail' && $item->asset?->isRestaurantImage())?->asset ?? $restaurant->media->first(fn ($item) => $item->role === 'fallback_thumbnail' && $item->asset?->isRestaurantImage())?->asset)
                    @php($variant = $media?->variants->sortBy('width')->firstWhere('width', '>=', 480) ?? $media?->variants->sortByDesc('width')->first())
                    @php($relationship = $restaurant->claims->isNotEmpty() || $restaurant->legacyAuthorships->isNotEmpty() ? 'Restaurateur' : 'Déposant')
                    @php($status = match ($restaurant->status) { 'published' => 'Publié', 'archived' => 'Archivé', 'rejected' => 'Refusé', default => 'En attente' })
                    <article class="account-restaurant-card">
                        <div class="account-restaurant-image">
                            @if($media && $variant)
                                <img src="{{ $media->deliveryUrl($variant->width) }}" width="{{ $media->width }}" height="{{ $media->height }}" loading="lazy" alt="">
                            @else
                                <span aria-hidden="true">🍽</span>
                            @endif
                        </div>
                        <div class="account-restaurant-content">
                            <div class="account-restaurant-heading">
                                <div>
                                    <h3>{{ $restaurant->name }}</h3>
                                    @if($restaurant->city_name)<p class="muted">{{ $restaurant->city_name }}</p>@endif
                                </div>
                                <div class="account-badges">
                                    <span class="account-status account-status-{{ $restaurant->status }}">{{ $status }}</span>
                                    <span class="account-relation">{{ $relationship }}</span>
                                </div>
                            </div>
                            @if($relationship === 'Déposant')
                                <p class="account-context">Vous avez proposé cette fiche et pouvez modifier ses informations. Un gérant peut toujours revendiquer l’établissement.</p>
                            @elseif($restaurant->legacyAuthorships->isNotEmpty())
                                <p class="account-context">Vous gérez cet établissement en tant que restaurateur.</p>
                            @endif
                            <div class="account-actions">
                                <a class="button" href="{{ route('owner.restaurants.edit', $restaurant) }}">Modifier la fiche</a>
                                @if($restaurant->status === 'published')
                                    <a class="button button-secondary" href="{{ route('restaurants.show', $restaurant->slug) }}">Voir la fiche</a>
                                @endif
                                @if($restaurant->pending_removal_requests_count)
                                    <span class="account-removal-pending">Demande de suppression en cours</span>
                                @else
                                    <a class="account-removal-link" href="{{ route('owner.restaurants.removal.create', $restaurant) }}">Demander la suppression</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="account-empty-state">
                        <span aria-hidden="true">⌁</span>
                        <h3>Vous n’avez aucun restaurant à gérer pour le moment.</h3>
                        <p>Ajoutez une fiche pour la proposer à Top Halal. Vous pourrez ensuite suivre son parcours depuis votre espace.</p>
                        <a class="button" href="{{ route('restaurant-submissions.create') }}">Ajouter un restaurant</a>
                    </div>
                @endforelse
            </section>

            <section id="securite" class="account-security" aria-labelledby="securite-title">
                <div>
                    <p class="eyebrow">Sécurité</p>
                    <h2 id="securite-title">Mon profil et sécurité</h2>
                    <p>Votre adresse de connexion : {{ auth()->user()->email }}</p>
                </div>
                <div class="account-security-actions">
                    <a class="button button-secondary" href="{{ route('password.change') }}">Changer le mot de passe</a>
                    @if(auth()->user()->role === 'admin')
                        <a class="account-admin-link" href="{{ \App\Filament\Resources\RestaurantClaimResource::getUrl() }}">Administration des revendications</a>
                    @endif
                </div>
            </section>
        </div>
    </section>
</x-layouts.app>
