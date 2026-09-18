<x-layouts.app :title="'Modifier '. $restaurant->name .' | Top Halal'" :hide-flash="true">
    @php
        $selectedCategories = collect(old('categories', $restaurant->categories->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        $selectedFeatures = collect(old('features', $restaurant->features->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
        $links = $restaurant->outboundLinks->keyBy('label');
        $photos = $restaurant->media->filter(fn ($media) => $media->role !== 'fallback_thumbnail' && $media->asset)->values();
    @endphp

    <section class="contact-page owner-form-page">
        <div class="shell owner-form-shell">
            <div class="contact-form-wrap owner-form-wrap">
                <aside class="contact-sticker"><span>Votre fiche restaurant</span></aside>
                <div class="contact-form-card owner-form-card">
                    <header>
                        <p class="eyebrow">Gérer ma fiche</p>
                        <h1>Modifier {{ $restaurant->name }}</h1>
                        <p>Toutes les informations affichées sur votre fiche se modifient ici. Votre adresse e-mail se gère uniquement depuis <a href="{{ route('account.dashboard') }}">Mon profil et sécurité</a>.</p>
                    </header>

                    @if(session('status'))<div class="auth-success" role="status">{{ session('status') }}</div>@endif
                    @if($errors->any())<div class="contact-errors" role="alert"><p>Certains champs demandent votre attention.</p></div>@endif

                    <form class="contact-form owner-restaurant-editor" method="post" enctype="multipart/form-data" action="{{ route('owner.restaurants.update', $restaurant) }}" data-owner-editor>
                        @csrf
                        @method('PUT')

                        <fieldset class="owner-editor-section">
                            <legend>Informations générales</legend>
                            <div class="contact-field"><label for="restaurant-name">Nom</label><div class="contact-input"><input id="restaurant-name" name="name" required value="{{ old('name', $restaurant->name) }}"></div>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><span class="contact-label">Proposition halal</span><input type="hidden" name="halal_meat" value="0"><input type="hidden" name="halal_chicken" value="0"><div class="owner-check-grid"><label><input type="checkbox" name="halal_meat" value="1" @checked(old('halal_meat', $restaurant->has_halal_meat))> Viande halal</label><label><input type="checkbox" name="halal_chicken" value="1" @checked(old('halal_chicken', $restaurant->has_halal_chicken))> Poulet halal</label></div>@error('halal_meat')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><label for="restaurant-description">Description</label><div class="contact-textarea"><textarea id="restaurant-description" name="description" rows="7" maxlength="3000">{{ old('description', $restaurant->description) }}</textarea></div><p class="form-help">Les URL ne sont pas acceptées dans la description ; utilisez les liens dédiés plus bas.</p>@error('description')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>

                        <fieldset class="owner-editor-section">
                            <legend>Spécialités et services</legend>
                            <p class="form-help">Choisissez au moins une spécialité et un service.</p>
                            <div class="contact-field"><span class="contact-label">Spécialités</span><div class="owner-check-grid">@foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true))> {{ $category->name }}</label>@endforeach</div>@error('categories')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><span class="contact-label">Services</span><div class="owner-check-grid">@foreach($features as $feature)<label><input type="checkbox" name="features[]" value="{{ $feature->id }}" @checked(in_array($feature->id, $selectedFeatures, true))> {{ $feature->name }}</label>@endforeach</div>@error('features')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>

                        <fieldset class="owner-editor-section">
                            <legend>Horaires</legend>
                            <p class="form-help">Ajoutez autant de plages que nécessaire. Elles doivent se suivre sans se chevaucher.</p>
                            <div class="owner-hours-list">
                                @foreach($hours as $dayIndex => $entry)
                                    <section class="owner-hours-day" data-owner-hours-day>
                                        <input type="hidden" name="hours[{{ $dayIndex }}][day]" value="{{ $entry['day'] }}">
                                        @foreach($entry['hour_ids'] as $hourId)<input type="hidden" name="hours[{{ $dayIndex }}][hour_ids][]" value="{{ $hourId }}">@endforeach
                                        <div class="owner-hours-head"><strong>{{ \App\Services\RestaurantHours::DAYS[$entry['day']] }}</strong><label>Statut <select name="hours[{{ $dayIndex }}][status]" data-owner-hours-status><option value="closed" @selected($entry['status'] === 'closed')>Fermé</option><option value="all_day" @selected($entry['status'] === 'all_day')>24 h / 24</option><option value="slots" @selected($entry['status'] === 'slots')>Plages horaires</option></select></label></div>
                                        <div class="owner-hours-slots" data-owner-hours-slots @if($entry['status'] !== 'slots') hidden @endif>
                                            @foreach($entry['slots'] as $slotIndex => $slot)
                                                <div class="owner-hours-slot" data-owner-hours-slot>
                                                    @if($slot['id'])<input type="hidden" name="hours[{{ $dayIndex }}][slots][{{ $slotIndex }}][id]" value="{{ $slot['id'] }}">@endif
                                                    <label>Ouverture <input type="time" name="hours[{{ $dayIndex }}][slots][{{ $slotIndex }}][opens_at]" value="{{ $slot['opens_at'] }}"></label>
                                                    <label>Fermeture <input type="time" name="hours[{{ $dayIndex }}][slots][{{ $slotIndex }}][closes_at]" value="{{ $slot['closes_at'] }}"></label>
                                                    <button type="button" class="owner-small-button" data-owner-remove-slot>Retirer</button>
                                                </div>
                                            @endforeach
                                        </div>
                                        <button type="button" class="owner-small-button" data-owner-add-slot @if($entry['status'] !== 'slots') hidden @endif>Ajouter une plage</button>
                                        <template data-owner-slot-template><div class="owner-hours-slot" data-owner-hours-slot><label>Ouverture <input type="time" data-owner-slot-open></label><label>Fermeture <input type="time" data-owner-slot-close></label><button type="button" class="owner-small-button" data-owner-remove-slot>Retirer</button></div></template>
                                        @error('hours.'.$dayIndex.'.slots')<p class="field-error">{{ $message }}</p>@enderror
                                    </section>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="owner-editor-section">
                            <legend>Adresse et position</legend>
                            <input type="hidden" name="location_changed" value="0" data-location-changed>
                            <x-address-selector label="Adresse du restaurant" :address-line1="$restaurant->address_line1" :postal-code="$restaurant->postal_code" :city-name="$restaurant->city_name" :latitude="$restaurant->latitude" :longitude="$restaurant->longitude" :show-existing="true" />
                            <p class="form-help">Recherchez et sélectionnez une adresse pour changer l’adresse. Vous pouvez aussi déplacer le marqueur pour ajuster uniquement la position GPS.</p>
                        </fieldset>

                        <fieldset class="owner-editor-section">
                            <legend>Contact et liens</legend>
                            <div class="contact-field"><label for="restaurant-phone">Téléphone</label><div class="contact-input"><input id="restaurant-phone" name="phone" inputmode="tel" value="{{ old('phone', $restaurant->phone) }}"></div>@error('phone')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <p class="form-help">L’adresse e-mail n’est pas modifiable ici : elle reste celle de votre compte et se modifie depuis votre profil.</p>
                            @foreach(['website_url' => ['Site web', 'Site web'], 'instagram_url' => ['Instagram', 'Instagram'], 'facebook_url' => ['Facebook', 'Facebook'], 'tiktok_url' => ['TikTok', 'TikTok']] as $field => [$label, $storedLabel])
                                <div class="contact-field"><label for="{{ $field }}">{{ $label }}</label><div class="contact-input"><input id="{{ $field }}" type="url" name="{{ $field }}" inputmode="url" placeholder="https://…" value="{{ old($field, $links->get($storedLabel)?->destination_url) }}"></div>@error($field)<p class="field-error">{{ $message }}</p>@enderror</div>
                            @endforeach
                        </fieldset>

                        <fieldset class="owner-editor-section">
                            <legend>Photos</legend>
                            <p class="form-help">La première photo est utilisée comme couverture. Les photos existantes peuvent être réordonnées ou retirées ; les ajouts rejoignent la galerie.</p>
                            <div class="owner-media-grid" data-owner-media-list>
                                @forelse($photos as $media)
                                    @php($asset = $media->asset)
                                    <article class="owner-media-card" data-owner-media-card data-media-id="{{ $media->id }}">
                                        <img src="{{ $asset->deliveryUrl(480) }}" width="{{ $asset->width }}" height="{{ $asset->height }}" loading="lazy" alt="{{ $asset->alt_text ?: $restaurant->name }}">
                                        <div><strong data-owner-cover-label>{{ $loop->first ? 'Couverture' : 'Galerie' }}</strong><label><input type="checkbox" name="remove_media_ids[]" value="{{ $media->id }}"> Retirer cette photo</label><p><button type="button" class="owner-small-button" data-owner-media-up>Monter</button><button type="button" class="owner-small-button" data-owner-media-down>Descendre</button></p></div>
                                    </article>
                                @empty
                                    <p class="form-help" data-owner-no-media>Aucune photo pour le moment.</p>
                                @endforelse
                            </div>
                            <div data-owner-media-order>@foreach($photos as $media)<input type="hidden" name="media_order[]" value="{{ $media->id }}">@endforeach</div>
                            <div class="contact-field"><label for="new-photos">Ajouter des photos</label><div class="contact-input"><input id="new-photos" type="file" name="new_photos[]" accept="image/jpeg,image/png,image/webp" multiple></div><p class="form-help">JPEG, PNG ou WebP, 800 px de large minimum, 10 Mo par fichier (10 fichiers maximum).</p>@error('new_photos.*')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </fieldset>

                        <button class="button contact-submit" type="submit">Enregistrer les modifications <span aria-hidden="true">→</span></button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
