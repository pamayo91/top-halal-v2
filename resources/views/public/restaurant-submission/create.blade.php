@php
    $initialStep = max(1, min(5, (int) session('submission_error_step', old('current_step', 1))));
    $selectedCategories = array_map('intval', old('categories', []));
    $selectedFeatures = array_map('intval', old('features', []));
@endphp
<x-layouts.app title="Ajouter un restaurant halal | Top Halal" robots="noindex,nofollow" :hide-flash="true">
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
                        <div class="submission-duplicates" data-name-duplicates aria-live="polite"></div>
                        @if($duplicate = session('duplicate_restaurant'))
                            <div class="submission-duplicates" role="alert">
                                <p><b>{{ $duplicate['name'] }}</b> semble déjà être référencé.</p>
                                @if($duplicate['url'])<a href="{{ $duplicate['url'] }}">Voir la fiche existante</a>@endif
                                @if($duplicate['claim_url']) <a href="{{ $duplicate['claim_url'] }}">Revendiquer cette fiche</a>@endif
                            </div>
                        @endif

                        <x-restaurant.halal-options :meat="old('halal_meat')" :chicken="old('halal_chicken')" :required="true" />

                        <div class="submission-actions">
                            <button class="button" type="button" data-next hidden>Continuer</button>
                        </div>
                    </section>

                    <section class="submission-step" data-submission-step="2" aria-labelledby="submission-step-2-title">
                        <p class="submission-kicker">Étape 2 sur 5</p>
                        <h2 id="submission-step-2-title">L’adresse</h2>
                        <p class="muted">Commencez à saisir l'adresse du restaurant, puis sélectionnez la bonne adresse dans la liste proposée.</p>
                        <x-address-selector />

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

                        <x-restaurant.taxonomy-selector kind="categories" :items="$categories" :selected="$selectedCategories" :required="true" />
                        <x-restaurant.taxonomy-selector kind="features" :items="$features" :selected="$selectedFeatures" :required="true" />
                        <x-restaurant.hours-editor />

                        <div class="form-grid submission-contact-grid">
                            <label class="submission-phone-field" for="restaurant-phone">Téléphone <input id="restaurant-phone" name="phone" type="tel" autocomplete="tel" maxlength="30" value="{{ old('phone') }}"></label>
                            <label for="restaurant-website">Site web <input id="restaurant-website" name="website_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('website_url') }}"></label>
                            <label for="restaurant-instagram">Instagram <input id="restaurant-instagram" name="instagram_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('instagram_url') }}"></label>
                            <label for="restaurant-facebook">Facebook <input id="restaurant-facebook" name="facebook_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('facebook_url') }}"></label>
                            <label for="restaurant-tiktok">TikTok <input id="restaurant-tiktok" name="tiktok_url" type="url" inputmode="url" placeholder="https://…" value="{{ old('tiktok_url') }}"></label>
                        </div>
                        <label for="restaurant-description">Description</label>
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
                        <p class="muted">Ajoutez une belle photo de couverture du restaurant.</p>
                        <label for="cover-photo">Photo de couverture <span aria-hidden="true">*</span></label>
                        <input id="cover-photo" name="cover_photo" type="file" accept="image/jpeg,image/png,image/webp" required data-cover-input>
                        <p class="form-help">JPEG, PNG ou WebP, 10 Mo maximum.</p>
                        <div class="photo-cover-preview" data-cover-preview aria-live="polite"></div>
                        @error('cover_photo')<p class="field-error">{{ $message }}</p>@enderror

                        <label for="gallery-photos">Photos complémentaires (10 maximum)</label>
                        <input id="gallery-photos" name="gallery_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-gallery-input>
                        <p class="form-help">Vous pourrez retirer ou réorganiser les photos avant l’envoi.</p>
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
                        <h2 id="submission-step-5-title">Vos informations</h2>
                        <p class="muted">Indiquez comment nous pouvons vous recontacter au sujet de cette proposition.</p>

                        <fieldset class="submission-choice-group submission-owner-choice">
                            <legend>Êtes-vous le gérant ou propriétaire de cet établissement ?</legend>
                            <label class="choice-card choice-card--role">
                                <input type="radio" name="submitter_role" value="customer" required @checked(old('submitter_role') === 'customer') data-owner-choice>
                                <span><b>Non</b><small>Je propose simplement ce restaurant. Il pourra être revendiqué plus tard par son gérant.</small></span>
                            </label>
                            <label class="choice-card choice-card--role">
                                <input type="radio" name="submitter_role" value="owner" required @checked(old('submitter_role') === 'owner') data-owner-choice>
                                <span><b>Oui</b><small>Je suis le gérant ou propriétaire de cet établissement. Je pourrai gérer cette fiche après validation.</small></span>
                            </label>
                            <div class="submission-owner-fields" data-owner-fields @unless(old('submitter_role') === 'owner') hidden @endunless>
                                <label><span class="submission-required-label">Nom / prénom <span aria-hidden="true">*</span></span><input name="owner_full_name" maxlength="255" value="{{ old('owner_full_name') }}" @required(old('submitter_role') === 'owner')></label>
                                @error('owner_full_name')<p class="field-error">{{ $message }}</p>@enderror
                                <label><span class="submission-required-label">Société <span aria-hidden="true">*</span></span><input name="owner_company" maxlength="255" value="{{ old('owner_company') }}" @required(old('submitter_role') === 'owner')></label>
                                @error('owner_company')<p class="field-error">{{ $message }}</p>@enderror
                                <label><span class="submission-required-label">SIRET <span aria-hidden="true">*</span></span><input name="owner_siret" inputmode="numeric" maxlength="20" value="{{ old('owner_siret') }}" @required(old('submitter_role') === 'owner') data-owner-siret></label>
                                <p class="field-error" data-owner-siret-error role="alert" hidden></p>
                                @error('owner_siret')<p class="field-error">{{ $message }}</p>@enderror
                                <label class="submission-owner-certification"><input type="checkbox" name="owner_certified" value="1" @checked(old('owner_certified')) @required(old('submitter_role') === 'owner')><span>Je certifie être le propriétaire, le gérant ou être autorisé à gérer cet établissement. <span aria-hidden="true">*</span></span></label>
                                @error('owner_certified')<p class="field-error">{{ $message }}</p>@enderror
                                <p class="form-help">Après validation de votre proposition, nous vous recontacterons pour la gestion de cette fiche.</p>
                            </div>
                            @error('submitter_role')<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                        <label for="submitter-email">Votre e-mail</label>
                        <input id="submitter-email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}">
                        <p class="form-help" data-owner-email-help="customer" @if(old('submitter_role') === 'owner') hidden @endif>Nous utilisons votre e-mail pour le suivi. Après sa confirmation, vous recevrez aussi un lien pour activer votre espace et adapter cette fiche.</p>
                        <p class="form-help" data-owner-email-help="owner" @unless(old('submitter_role') === 'owner') hidden @endunless>Nous utilisons votre e-mail pour le suivi. Après sa confirmation, vous recevrez aussi un lien pour activer votre espace et gérer cette fiche.</p>
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
                        <li data-step-indicator="5"><b>5</b><span>Vos informations<small>Rôle et e-mail</small></span></li>
                    </ol>
                </aside>
            </div>
        </div>
    </section>
</x-layouts.app>
