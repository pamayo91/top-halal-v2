<x-layouts.app :title="'Contact - Top Halal'" :hide-flash="true">
    <section class="contact-page">
        <div class="contact-layout shell">
            <div class="contact-editorial">
                <p class="contact-kicker"><span aria-hidden="true"></span>TOP HALAL, le site incontournable du halal au quotidien</p>
                <h1>Une question ?<br>On vous écoute.</h1>
                <p class="contact-introduction">{{ $settings['introduction'] ?? "Une information à corriger, une question sur un restaurant, un sujet autour du halal ou de l'islam ? Écrivez-nous." }}</p>
                <ul class="contact-topics" aria-label="Nous pouvons vous aider">
                    <li><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 21s7-6.1 7-12a7 7 0 1 0-14 0c0 5.9 7 12 7 12Z"/><circle cx="12" cy="9" r="2"/></svg><span>Restaurant<br>à signaler</span></li>
                    <li><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m4 20 4.1-1 10.3-10.3a2.2 2.2 0 0 0-3.1-3.1L5 15.9 4 20Z"/><path d="m13.8 7.2 3.1 3.1"/></svg><span>Information<br>à corriger</span></li>
                    <li><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 11.5a8 8 0 0 1-8.5 8 8.7 8.7 0 0 1-3.5-.8L4 20l1.3-3.5A8 8 0 1 1 20 11.5Z"/></svg><span>Un sujet à<br>nous proposer</span></li>
                </ul>
                <div class="contact-art" aria-hidden="true">
                    <svg class="contact-art-pin" viewBox="0 0 90 130"><path d="M45 2C21 2 4 20 4 44c0 30 41 80 41 80s41-50 41-80C86 20 69 2 45 2Z"/><circle cx="45" cy="44" r="15"/></svg>
                    <span class="contact-art-plate"></span><span class="contact-art-bubble">•••</span>
                    <svg class="contact-art-curve" viewBox="0 0 330 130"><path d="M3 118C90 8 258 8 327 70"/></svg>
                    <p>Partager. Découvrir.<br>Transmettre.</p>
                </div>
                <p class="contact-signoff"><span aria-hidden="true">→</span> Le halal au quotidien, et bien plus encore.</p>
            </div>

            <div class="contact-form-wrap">
                <aside class="contact-sticker"><span>On lit vraiment vos messages</span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></aside>
                <div class="contact-form-card">
                    @if(session('status'))
                        <div class="contact-success" role="status" tabindex="-1">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>
                            <h2>Message envoyé</h2><p>{{ session('status') }}</p>
                        </div>
                    @else
                        <header><h2>Envoyez-nous un message</h2><p>Notre équipe vous répond généralement sous 24 à 48h.</p></header>
                        @if($errors->any())<div class="contact-errors" role="alert"><p>Certains champs demandent votre attention.</p></div>@endif
                        <form method="post" action="{{ route('contact.store') }}" class="contact-form" data-contact-form>
                            @csrf
                            <div class="hp" aria-hidden="true"><label for="website">Ne pas remplir</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                            <div class="contact-field"><label for="contact-name">Votre nom</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.7-3.6 3.1-5.5 7-5.5s6.3 1.9 7 5.5"/></svg><input id="contact-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" placeholder="Ex. Sami Benali" aria-describedby="contact-name-error"></div>@error('name')<p id="contact-name-error" class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><label for="contact-email">Votre email</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg><input id="contact-email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" placeholder="Ex. votre@email.fr" aria-describedby="contact-email-error"></div>@error('email')<p id="contact-email-error" class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><label for="contact-subject">Sujet</label><div class="contact-input"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg><input id="contact-subject" name="subject" value="{{ old('subject') }}" required maxlength="180" placeholder="Ex. Demande d'information" aria-describedby="contact-subject-error"></div>@error('subject')<p id="contact-subject-error" class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="contact-field"><label for="contact-message">Votre message</label><div class="contact-textarea"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 11.5a8 8 0 0 1-8.5 8 8.7 8.7 0 0 1-3.5-.8L4 20l1.3-3.5A8 8 0 1 1 20 11.5Z"/></svg><textarea id="contact-message" name="message" required maxlength="5000" rows="7" placeholder="Écrivez votre message ici..." aria-describedby="contact-message-count contact-message-error">{{ old('message') }}</textarea><span id="contact-message-count" class="contact-count" aria-live="polite">{{ mb_strlen(old('message', '')) }} / 5000</span></div>@error('message')<p id="contact-message-error" class="field-error">{{ $message }}</p>@enderror</div>
                            <button class="button contact-submit" type="submit">Envoyer mon message <span aria-hidden="true">→</span></button>
                            <p class="contact-privacy"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Vos informations sont traitées en toute confidentialité.</p>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
