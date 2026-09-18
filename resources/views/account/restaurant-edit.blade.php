<x-layouts.app :title="'Modifier '. $restaurant->name .' | Top Halal'" :hide-flash="true">
    @php
        $selectedCategories = collect(old('categories', $restaurant->categories->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        $selectedFeatures = collect(old('features', $restaurant->features->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        $links = $restaurant->outboundLinks->keyBy('label');
        $photos = $restaurant->media->filter(fn ($media) => $media->role !== 'fallback_thumbnail' && $media->asset)->values();
    @endphp

    <section class="owner-editor-page">
        <div class="shell owner-editor-shell">
            <header class="owner-editor-header">
                <a class="owner-editor-back" href="{{ route('account.dashboard') }}">← Retour à mon compte</a>
                <p class="eyebrow">Gérer ma fiche</p>
                <h1>Modifier {{ $restaurant->name }}</h1>
                <p>Modifiez les informations visibles sur votre fiche. Votre adresse e-mail se gère uniquement depuis <a href="{{ route('account.dashboard') }}">Mon profil et sécurité</a>.</p>
            </header>

            @if(session('status'))<div class="auth-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="owner-editor-errors" role="alert"><p>Certains champs demandent votre attention.</p></div>@endif

            <form class="owner-restaurant-editor" method="post" enctype="multipart/form-data" action="{{ route('owner.restaurants.update', $restaurant) }}" data-owner-editor>
                @csrf
                @method('PUT')
                <input type="hidden" name="editor_complete" value="1">

                <section class="owner-editor-section" aria-labelledby="owner-general-title">
                    <h2 id="owner-general-title">Informations générales</h2>
                    <label class="owner-field" for="restaurant-name">Nom<input id="restaurant-name" name="name" required value="{{ old('name', $restaurant->name) }}"></label>
                    @error('name')<p class="field-error">{{ $message }}</p>@enderror
                    <x-restaurant.halal-options :meat="old('halal_meat', $restaurant->has_halal_meat)" :chicken="old('halal_chicken', $restaurant->has_halal_chicken)" :include-false-values="true" />
                    <label class="owner-field" for="restaurant-description">Description<textarea id="restaurant-description" name="description" rows="7" maxlength="3000">{{ old('description', $restaurant->description) }}</textarea></label>
                    <p class="form-help">Les URL ne sont pas acceptées dans la description ; utilisez les liens dédiés plus bas.</p>
                    @error('description')<p class="field-error">{{ $message }}</p>@enderror
                </section>

                <section class="owner-editor-section" aria-labelledby="owner-taxonomies-title">
                    <h2 id="owner-taxonomies-title">Spécialités et services</h2>
                    <p class="form-help">Choisissez au moins une spécialité et un service.</p>
                    <x-restaurant.taxonomy-selector kind="categories" :items="$categories" :selected="$selectedCategories" :required="true" />
                    <x-restaurant.taxonomy-selector kind="features" :items="$features" :selected="$selectedFeatures" :required="true" />
                </section>

                <section class="owner-editor-section" aria-label="Horaires">
                    <x-restaurant.hours-editor :hours="$hours" />
                </section>

                <section class="owner-editor-section" aria-labelledby="owner-address-title">
                    <h2 id="owner-address-title">Adresse et localisation</h2>
                    <input type="hidden" name="location_changed" value="0" data-location-changed>
                    <x-address-selector label="Adresse du restaurant" :address-line1="$restaurant->address_line1" :postal-code="$restaurant->postal_code" :city-name="$restaurant->city_name" :latitude="$restaurant->latitude" :longitude="$restaurant->longitude" :show-existing="true" />
                </section>

                <section class="owner-editor-section" aria-labelledby="owner-contact-title">
                    <h2 id="owner-contact-title">Contact et réseaux sociaux</h2>
                    <div class="form-grid submission-contact-grid owner-contact-grid">
                        <label class="submission-phone-field" for="restaurant-phone">Téléphone<input id="restaurant-phone" name="phone" type="tel" inputmode="tel" maxlength="100" value="{{ old('phone', $restaurant->phone) }}"></label>
                        <label for="restaurant-website">Site web<input id="restaurant-website" type="url" name="website_url" inputmode="url" placeholder="https://…" value="{{ old('website_url', $links->get('Site web')?->destination_url) }}"></label>
                        <label for="restaurant-instagram">Instagram<input id="restaurant-instagram" type="url" name="instagram_url" inputmode="url" placeholder="https://…" value="{{ old('instagram_url', $links->get('Instagram')?->destination_url) }}"></label>
                        <label for="restaurant-facebook">Facebook<input id="restaurant-facebook" type="url" name="facebook_url" inputmode="url" placeholder="https://…" value="{{ old('facebook_url', $links->get('Facebook')?->destination_url) }}"></label>
                        <label for="restaurant-tiktok">TikTok<input id="restaurant-tiktok" type="url" name="tiktok_url" inputmode="url" placeholder="https://…" value="{{ old('tiktok_url', $links->get('TikTok')?->destination_url) }}"></label>
                    </div>
                    @foreach(['phone', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url'] as $field) @error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
                </section>

                <section class="owner-editor-section" aria-labelledby="owner-photos-title">
                    <h2 id="owner-photos-title">Photos</h2>
                    <p class="form-help">La première photo de la galerie est utilisée comme photo de couverture. Vous pouvez réorganiser ou retirer les photos existantes.</p>
                    <div class="owner-media-gallery" data-owner-media-list>
                        @forelse($photos as $media)
                            @php
                                $asset = $media->asset;
                                $variant = $asset->variants->where('width', '>=', 480)->sortBy('width')->first() ?? $asset->variants->sortByDesc('width')->first();
                            @endphp
                            <article class="owner-media-card" data-owner-media-card data-media-id="{{ $media->id }}">
                                <img src="{{ $asset->deliveryUrl($variant?->width) }}" width="{{ $variant?->width ?? $asset->width }}" height="{{ $variant?->height ?? $asset->height }}" loading="lazy" alt="{{ $asset->alt_text ?: $restaurant->name }}">
                                <div><strong class="owner-cover-badge" data-owner-cover-label>{{ $loop->first ? 'Couverture' : 'Galerie' }}</strong><label><input type="checkbox" name="remove_media_ids[]" value="{{ $media->id }}"> Retirer cette photo</label><p><button type="button" class="owner-small-button" data-owner-media-up>Monter</button><button type="button" class="owner-small-button" data-owner-media-down>Descendre</button></p></div>
                            </article>
                        @empty
                            <p class="form-help" data-owner-no-media>Aucune photo pour le moment.</p>
                        @endforelse
                    </div>
                    <div data-owner-media-order>@foreach($photos as $media)<input type="hidden" name="media_order[]" value="{{ $media->id }}">@endforeach</div>
                    <label class="owner-upload-field" for="new-photos">Ajouter des photos<input id="new-photos" type="file" name="new_photos[]" accept="image/jpeg,image/png,image/webp" multiple><span>JPEG, PNG ou WebP, 800 px de large minimum, 10 Mo par fichier (10 fichiers maximum).</span></label>
                    @error('new_photos')<p class="field-error">{{ $message }}</p>@enderror
                    @error('new_photos.*')<p class="field-error">{{ $message }}</p>@enderror
                </section>

                <div class="owner-save-bar">
                    <a class="button button-secondary" href="{{ route('account.dashboard') }}">Retour à mon compte</a>
                    <button class="button" type="submit">Enregistrer les modifications <span aria-hidden="true">→</span></button>
                </div>
            </form>
        </div>
    </section>
</x-layouts.app>
