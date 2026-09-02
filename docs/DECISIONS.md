# Architectural & Product Decisions

## 2026-09-02

### D023 — Mandatory selection for restaurant addresses

Restaurant address creation/replacement is provider-authoritative: every public, admin-create and owner-edit address change starts from a Géoplateforme suggestion whose opaque token is resolved server-side. The public form exposes neither manual administrative/GPS fields nor the INSEE code. A marker refinement after selection changes latitude/longitude only; it does not reverse-geocode, change structured address data or mutate geocoding provenance/status. Existing restaurant records are not mass-updated by this feature.

## 2026-08-31

### D022 — Provider-derived completion for fully missing address structure

When `address_line1`, postcode and city are all missing, Géoplateforme is the authoritative source for their structured replacement and related INSEE/country/provenance fields, provided its result is complete and precise to a street or housenumber. The historical raw address is the query input and remains immutable; existing GPS and geocoding/location statuses are not recomputed by this completion.

### D021 — Strict address-line repair without arbitration

`address` remains the immutable historical/raw value. `address_line1` is only the number and street, while `postal_code` and `city_name` remain separate. A reusable deterministic parser may remove the suffix when the current line duplicates the raw address and a non-empty street remains. The normal mode requires matching structured postcode/city; a separately opted-in cleanup mode, authorized after manual verification, may remove a visibly final historical `CP + ville` despite a mismatch. It never changes the raw address or structured/GPS fields.

### D020 — Double confirmation for restoration and legacy imports

Any database restoration, V2 rebuild/reseed, or import/reimport from the legacy database requires two distinct explicit confirmations from the project owner in the current conversation. Before the first, Codex must state the target, scope, source, expected mutations and rollback/backup plan. The first confirmation permits only preparation; a second confirmation is required immediately before the command or write starts. Previous permission, a broad request or silence cannot be treated as confirmation. Read-only audits are unaffected.

### D019 — Non-regression gate
Significant functional work is not DONE until the complete V2 non-regression suite passes on preproduction. The baseline is held in the V2 database, is refreshed only deliberately after a reviewed legitimate data change, and never uses WordPress as a runtime dependency. Count decreases, lost relations/media, HTTP 500s, browser failures and new Laravel exceptions are release blockers.

## 2026-08-24

### D001 — Leave WordPress entirely
WordPress is legacy storage only. Top-Halal V2 is not a WordPress theme/plugin project and must not require WordPress at runtime.

### D002 — Laravel monolith
Use a Laravel 13 monolith with server-rendered Blade for public pages to minimize operational and frontend complexity.

### D003 — Apache + MariaDB + no Docker
Use the existing dedicated-server stack: Apache, PHP 8.4, MariaDB 11.4.x, SSH and Cron. Do not introduce Docker.

### D004 — Application-managed SEO redirects
Migrate content-path redirects from the old `.htaccess` into the application. Keep host/protocol/infrastructure redirects in Apache. Support exact and advanced/pattern redirects with validation and caching.

### D005 — Comment URLs forbidden
New comments cannot contain URLs/links. Enforce server-side and safely render plain text.

### D006 — Restaurant external links obfuscated
External URLs must not be exposed in public HTML/JSON-LD. Use server-side opaque outbound actions from buttons.

### D007 — AI provenance internal, disclosure configurable
Always keep generation provenance internally. Public disclosure may be globally/per-article enabled or hidden.

### D008 — AI vendor abstraction
AI workflows depend on an internal provider interface rather than one vendor SDK/API.

### D009 — CWV is a release gate
Performance must be designed in from the first templates/components and validated on preproduction.

### D010 — Living specification
Codex must update project status/specifications as it develops and tests features.

### D011 — Controlled V2 media pipeline
Media is reconciled through explicit, idempotent attachment pilots. V2 stores originals privately by checksum, generates local responsive WebP variants for supported raster formats, and never renders a legacy upload URL. Missing files and unselected inline media remain auditable debt rather than inferred or silently discarded.

### D012 — SEO URL and removal policy
Canonical public paths are slashless (except `/`) and use the restaurant, location, taxonomy and editorial route contract in `specifications/seo.md`. Deleted legacy URLs default to a 301 toward the best equivalent and, ultimately, `/`; 404/410 require an explicit technical or product justification.
# ADR — Carte de localisation admin : Leaflet et fournisseur de tuiles configurable

La phase 6A emploie Leaflet uniquement dans le panneau Filament et seulement lorsque l’onglet Localisation est rendu. Les tuiles OpenStreetMap sont une valeur par défaut de préproduction, définie dans `config/location.php` et remplaçable par variables d’environnement avant production. La carte ne participe pas à la logique métier : les services de sélection, qualification et provenance restent indépendants de Leaflet et du fournisseur de tuiles.

### D013 — Enrichissement web sous checkpoints et API officielle optionnelle

Les enrichissements restaurant sont journalisés dans une table dédiée, un checkpoint par restaurant, et traités par ordre d’ID réel sans utiliser le nombre de lignes. La source est une abstraction : Google Places API officiel est un adaptateur optionnel, désactivé par défaut, afin de ne dépendre ni de scraping HTML fragile ni d’une API payante non approuvée. Toute fermeture/radiation est une alerte de revue humaine et interdit toute mutation automatique de la fiche.

### D014 — Recherche web interactive par Codex

Le flux normal de l’enrichissement est la recherche/navigation web interactive de Codex, restaurant par restaurant. Laravel ne recherche pas le web : il réserve les checkpoints, reçoit les preuves structurées, applique les validations métier et écrit le rapport. Cette séparation préserve une recherche contextuelle sans API obligatoire et sans scraping massif.

### D015 — Propositions publiques créées directement en attente

Une proposition publique crée une fiche `restaurants` standard en statut `pending`, afin qu’elle utilise le même circuit de modération que les autres fiches, sans jamais devenir visible publiquement. Son unique e-mail requis alimente le contact de la fiche, à vérifier par la modération, et est conservé avec le lien du déposant dans `restaurant_submissions` pour l’audit. Les URLs proposées restent des destinations sortantes inactives, sans exposition HTML/JSON-LD avant validation humaine.

### D016 — Tests refusés hors configuration SQLite

La base de tests doit obligatoirement être SQLite. Le bootstrap `tests/TestCase.php` échoue avant toute migration si l’environnement ou la connexion active ne correspond pas à la configuration de PHPUnit ; un cache de configuration de préproduction ne peut donc plus faire exécuter des tests migratoires sur MariaDB.

### D017 — Compte administrateur humain et migration legacy figés

Le compte administrateur humain désigné par le propriétaire du projet est l’unique compte admin humain. Son mot de passe, son rôle et son statut ne peuvent être modifiés qu’à la demande explicite et ponctuelle du propriétaire. La migration WordPress est finalisée : la base legacy ne doit plus être interrogée, utilisée ni relancée sans une nouvelle autorisation explicite. Les validations de développement utilisent, lorsque nécessaire, un compte admin provisoire distinct.
