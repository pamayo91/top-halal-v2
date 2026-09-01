@php
    $initialStep = max(1, min(5, (int) old('current_step', 1)));
    $selectedCategories = array_map('strval', old('categories', []));
    $selectedFeatures = array_map('strval', old('features', []));
@endphp
<x-layouts.app title="Ajouter un restaurant halal | Top Halal" robots="noindex,nofollow">
    <x-slot:head>
        <script>document.documentElement.classList.add('has-submission-js');</script>
    </x-slot:head>

    <section class="submission-page" data-restaurant-submission data-initial-step="{{ $initialStep }}" data-address-endpoint="{{ route('restaurant-submissions.addresses') }}" data-duplicates-endpoint="{{ route('restaurant-submissions.duplicates') }}">
        <div class="shell submission-shell">
            <header class="submission-header">
                <a href="{{ route('restaurants.index') }}" class="submission-back">← Retour aux restaurants</a>
                <p class="eyebrow">Référencer une adresse</p>
                <h1>Ajoutez un restaurant halal</h1>
                <p class="hero-copy">Quelques informations suffisent. Chaque proposition est vérifiée avant toute publication.</p>
                <div class="submission-progress" aria-label="Progression du formulaire" aria-live="polite">
                    <span class="submission-progress-label">Étape <b data-current-step>{{ $initialStep }}</b>/5</span>
                    <div class="submission-progress-track" aria-hidden="true"><span data-progress-bar style="width: {{ $initialStep * 20 }}%"></span></div>
                </div>
            </header>

            <div class="submission-layout">
                <form class="submission-form" method="post" action="{{ route('restaurant-submissions.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="current_step" value="{{ $initialStep }}" data-current-step-input>

                    <section class="submission-step" data-submission-step="1" aria-labelledby="submission-step-1-title">
                        <p class="submission-kicker">Étape 1 sur 5</p>
                        <h2 id="submission-step-1-title">Le restaurant</h2>
                        <p class="muted">Commençons par l’essentiel. Nous vérifions aussi les fiches au nom proche pour éviter les doublons.</p>
                        <label for="submission-name">Nom du restaurant</label>
                        <input id="submission-name" name="name" required maxlength="255" autocomplete="organization" value="{{ old('name') }}" data-restaurant-name>
                        @error('name')<p class="field-error">{{ $message }}</p>@enderror

                        <fieldset class="submission-choice-group" data-halal-group>
                            <legend>Que propose le restaurant ?</legend>
                            <p class="form-help">Au moins une option est nécessaire pour continuer.</p>
                            <label class="choice-card"><input type="checkbox" name="halal_meat" value="1" @checked(old('halal_meat')) data-halal-option> <span><b>Viande halal</b><small>Le restaurant propose de la viande halal.</small></span></label>
                            <label class="choice-card"><input type="checkbox" name="halal_chicken" value="1" @checked(old('halal_chicken')) data-halal-option> <span><b>Poulet halal</b><small>Le restaurant propose du poulet halal.</small></span></label>
                            <p class="field-error" hidden data-halal-error>Choisissez au moins une des deux options pour continuer.</p>
                            @error('halal_meat')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>

                        <div class="submission-duplicates" data-name-duplicates aria-live="polite"></div>
                        <div class="submission-actions">
                            <button class="button" type="button" data-next hidden>Continuer</button>
                        </div>
                    </section>

                    <section class="submission-step" data-submission-step="2" aria-labelledby="submission-step-2-title">
                        <p class="submission-kicker">Étape 2 sur 5</p>
                        <h2 id="submission-step-2-title">L’adresse</h2>
                        <p class="muted">Sélectionnez l’adresse proposée : les données administratives et la position sont alors complétées automatiquement.</p>

                        <label for="address-query">Rechercher une adresse</label>
                        <input id="address-query" type="search" autocomplete="street-address" placeholder="Ex. 46 boulevard du Temple, Paris" data-address-query aria-describedby="address-help address-results">
                        <p id="address-help" class="form-help">Les suggestions sont fournies par la Géoplateforme. Vous pourrez ajuster le marqueur si nécessaire.</p>
                        <div id="address-results" class="address-results" data-address-results aria-live="polite"></div>
                        <input type="hidden" name="address_suggestion_token" value="{{ old('address_suggestion_token') }}" data-address-token>
                        <p class="address-selected" data-address-selected @if(!old('address_suggestion_token')) hidden @endif>Adresse sélectionnée. Vous pouvez déplacer le marqueur pour corriger la position.</p>

                        <details class="manual-address" data-manual-address @if(!old('address_suggestion_token')) open @endif>
                            <summary>Mon adresse n’apparaît pas dans les suggestions</summary>
                            <p class="form-help">Renseignez ce que vous connaissez. La modération vérifiera l’adresse avant publication.</p>
                            <div class="form-grid">
                                <label for="address-line1">Adresse <input id="address-line1" name="address_line1" maxlength="255" value="{{ old('address_line1') }}" data-address-field="address_line1"></label>
                                <label for="address-line2">Complément <input id="address-line2" name="address_line2" maxlength="255" value="{{ old('address_line2') }}" data-address-field="address_line2"></label>
                                <label for="postal-code">Code postal <input id="postal-code" name="postal_code" maxlength="20" inputmode="numeric" value="{{ old('postal_code') }}" data-address-field="postal_code"></label>
                                <label for="city-name">Ville <input id="city-name" name="city_name" maxlength="255" value="{{ old('city_name') }}" data-address-field="city_name"></label>
                                <label for="city-code">Code INSEE <input id="city-code" name="city_code" maxlength="10" value="{{ old('city_code') }}" data-address-field="city_code"></label>
                            </div>
                            <input type="hidden" name="country_code" value="{{ old('country_code', 'FR') }}" data-address-field="country_code">
                        </details>
                        @foreach(['address_line1', 'postal_code', 'city_name', 'latitude'] as $field) @error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach

                        <div class="submission-map-wrap">
                            <div class="submission-map" data-submission-map data-tile-url="{{ config('location.map_tile_url') }}" data-tile-attribution="{{ config('location.map_tile_attribution') }}" aria-label="Carte de la position du restaurant"></div>
                            <p class="form-help" data-map-help>Choisissez une adresse pour afficher la carte et déplacer le marqueur.</p>
                        </div>
                        <input type="hidden" name="latitude" value="{{ old('latitude') }}" data-latitude>
                        <input type="hidden" name="longitude" value="{{ old('longitude') }}" data-longitude>
                        <input type="hidden" name="map_moved" value="{{ old('map_moved', 0) }}" data-map-moved>

                        <div class="submission-duplicates" data-address-duplicates aria-live="polite"></div>
                        <div class="submission-actions">
                            <button class="button button-secondary" type="button" data-previous hidden>Retour</button>
                            <button class="button" type="button" data-next hidden>Continuer</button>
                        </div>
                    </section>

                    <section class="submission-step" data-submission-step="3" aria-labelledby="submission-step-3-title">
                        <p class="submission-kicker">Étape 3 sur 5</p>
                        <h2 id="submission-step-3-title">Les informations utiles</h2>
                        <p class="muted">Ajoutez ce qui aidera les visiteurs. Tout est vérifié avant publication.</p>

                        <fieldset class="taxonomy-fieldset">
                            <legend>Catégories / type de cuisine</legend>
                            <div class="checkbox-grid">
                                @foreach($categories as $category)<label><input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array((string) $category->id, $selectedCategories, true))> {{ $category->name }}</label>@endforeach
                            </div>
                        </fieldset>
                        <fieldset class="taxonomy-fieldset">
                            <legend>Services et caractéristiques</legend>
                            <p class="form-help">Une certification halal éventuelle se sélectionne ici comme un service ; elle n’est jamais obligatoire.</p>
                            <div class="checkbox-grid">
                                @foreach($features as $feature)<label><input type="checkbox" name="features[]" value="{{ $feature->id }}" @checked(in_array((string) $feature->id, $selectedFeatures, true))> {{ $feature->name }}</label>@endforeach
                            </div>
                        </fieldset>

                        <fieldset class="hours-fieldset" data-hours-editor>
                            <legend>Horaires</legend>
                            <p class="form-help">Pour chaque jour, indiquez fermé, ouvert 24h/24, ou une à deux plages horaires.</p>
                            @foreach($days as $dayKey => $dayLabel)
                                @php($status = old("hours.$dayKey.status", 'closed'))
                                <div class="hours-day" data-hours-day="{{ $dayKey }}">
                                    <div><b>{{ $dayLabel }}</b><label class="sr-only" for="hours-{{ $dayKey }}-status">Statut {{ $dayLabel }}</label><select id="hours-{{ $dayKey }}-status" name="hours[{{ $dayKey }}][status]" data-hours-status><option value="closed" @selected($status === 'closed')>Fermé</option><option value="all_day" @selected($status === 'all_day')>Ouvert 24h/24</option><option value="slots" @selected($status === 'slots')>Horaires</option></select></div>
                                    <div class="hours-slots" data-hours-slots @if($status !== 'slots') hidden @endif>
                                        <label>De <input type="time" name="hours[{{ $dayKey }}][first_open]" value="{{ old("hours.$dayKey.first_open") }}"></label>
                                        <label>À <input type="time" name="hours[{{ $dayKey }}][first_close]" value="{{ old("hours.$dayKey.first_close") }}"></label>
                                        <label>De <input type="time" name="hours[{{ $dayKey }}][second_open]" value="{{ old("hours.$dayKey.second_open") }}"></label>
                                        <label>À <input type="time" name="hours[{{ $dayKey }}][second_close]" value="{{ old("hours.$dayKey.second_close") }}"></label>
                                    </div>
                                </div>
                            @endforeach
                            <div class="hours-copy"><label for="hours-copy-source">Recopier les horaires de</label><select id="hours-copy-source" data-copy-source>@foreach($days as $dayKey => $dayLabel)<option value="{{ $dayKey }}">{{ $dayLabel }}</option>@endforeach</select><fieldset><legend class="sr-only">Jours à mettre à jour</legend>@foreach($days as $dayKey => $dayLabel)<label><input type="checkbox" value="{{ $dayKey }}" data-copy-target> {{ $dayLabel }}</label>@endforeach</fieldset><button class="button button-secondary button-small" type="button" data-copy-hours>Recopier</button></div>
                        </fieldset>

                        <div class="form-grid">
                            <label for="restaurant-phone">Téléphone <input id="restaurant-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"></label>
                            <label for="restaurant-website">Site web <input id="restaurant-website" name="website_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('website_url') }}"></label>
                            <label for="restaurant-instagram">Instagram <input id="restaurant-instagram" name="instagram_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('instagram_url') }}"></label>
                            <label for="restaurant-facebook">Facebook <input id="restaurant-facebook" name="facebook_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('facebook_url') }}"></label>
                            <label for="restaurant-tiktok">TikTok <input id="restaurant-tiktok" name="tiktok_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('tiktok_url') }}"></label>
                        </div>
                        <p class="form-help">Les liens sont stockés pour vérification et ne sont jamais affichés directement sur une fiche publique.</p>
                        <label for="restaurant-description">Description (facultative)</label>
                        <textarea id="restaurant-description" name="description" maxlength="3000" data-description>{{ old('description') }}</textarea>
                        @foreach(['phone', 'website_url', 'instagram_url', 'facebook_url', 'tiktok_url', 'description'] as $field) @error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach

                        <div class="submission-actions">
                            <button class="button button-secondary" type="button" data-previous hidden>Retour</button>
                            <button class="button" type="button" data-next hidden>Continuer</button>
                        </div>
                    </section>

                    <section class="submission-step" data-submission-step="4" aria-labelledby="submission-step-4-title">
                        <p class="submission-kicker">Étape 4 sur 5</p>
                        <h2 id="submission-step-4-title">Les photos</h2>
                        <p class="muted">Une belle photo de couverture est nécessaire pour permettre la vérification. Les photos sont privées jusqu’à la modération.</p>
                        <label for="cover-photo">Photo de couverture <span aria-hidden="true">*</span></label>
                        <input id="cover-photo" name="cover_photo" type="file" accept="image/jpeg,image/png,image/webp" required data-cover-input>
                        <p class="form-help">JPEG, PNG ou WebP, 10 Mo maximum.</p>
                        <div class="photo-cover-preview" data-cover-preview aria-live="polite"></div>
                        @error('cover_photo')<p class="field-error">{{ $message }}</p>@enderror

                        <label for="gallery-photos">Photos complémentaires (facultatives, 10 maximum)</label>
                        <input id="gallery-photos" name="gallery_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-gallery-input>
                        <p class="form-help">Vous pouvez retirer ou réorganiser les photos avant l’envoi.</p>
                        <ol class="photo-gallery-preview" data-gallery-preview aria-live="polite"></ol>
                        @error('gallery_photos')<p class="field-error">{{ $message }}</p>@enderror
                        @error('gallery_photos.*')<p class="field-error">{{ $message }}</p>@enderror

                        <div class="submission-actions">
                            <button class="button button-secondary" type="button" data-previous hidden>Retour</button>
                            <button class="button" type="button" data-next hidden>Continuer</button>
                        </div>
                    </section>

                    <section class="submission-step" data-submission-step="5" aria-labelledby="submission-step-5-title">
                        <p class="submission-kicker">Étape 5 sur 5</p>
                        <h2 id="submission-step-5-title">Vérification</h2>
                        <p class="muted">Relisez la proposition avant de l’envoyer. Elle restera en attente de modération.</p>
                        <div class="submission-summary" data-submission-summary aria-live="polite"></div>

                        <fieldset class="submission-choice-group">
                            <legend>Quel est votre lien avec ce restaurant ?</legend>
                            <label class="choice-card"><input type="radio" name="submitter_role" value="owner" required @checked(old('submitter_role') === 'owner')> <span><b>Je suis propriétaire / gérant</b><small>Nous ne vous demanderons pas d’autre information ici. Le parcours de revendication viendra ensuite.</small></span></label>
                            <label class="choice-card"><input type="radio" name="submitter_role" value="employee" @checked(old('submitter_role') === 'employee')> <span><b>Je travaille dans ce restaurant</b></span></label>
                            <label class="choice-card"><input type="radio" name="submitter_role" value="customer" @checked(old('submitter_role') === 'customer')> <span><b>Je suis client</b></span></label>
                            @error('submitter_role')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                        <label for="submitter-email">Votre e-mail</label>
                        <input id="submitter-email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}">
                        <p class="form-help">Nous l’utilisons pour le suivi de cette proposition et comme e-mail de contact à vérifier par la modération. Aucun nom ni prénom n’est demandé.</p>
                        @error('email')<p class="field-error">{{ $message }}</p>@enderror

                        <div class="submission-actions">
                            <button class="button button-secondary" type="button" data-previous hidden>Retour</button>
                            <button class="button" type="submit" data-final-submit>Envoyer le restaurant</button>
                        </div>
                    </section>
                </form>

                <aside class="submission-steps-aside" aria-label="Étapes du formulaire">
                    <ol>
                        <li data-step-indicator="1"><b>1</b><span>Restaurant<small>Nom et offre halal</small></span></li>
                        <li data-step-indicator="2"><b>2</b><span>Adresse<small>Position et doublons</small></span></li>
                        <li data-step-indicator="3"><b>3</b><span>Informations<small>Services et horaires</small></span></li>
                        <li data-step-indicator="4"><b>4</b><span>Photos<small>Couverture et galerie</small></span></li>
                        <li data-step-indicator="5"><b>5</b><span>Vérification<small>Récapitulatif et e-mail</small></span></li>
                    </ol>
                </aside>
            </div>
        </div>
    </section>
</x-layouts.app>
