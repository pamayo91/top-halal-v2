# Architectural & Product Decisions

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

Une proposition publique crée une fiche `restaurants` standard en statut `pending`, afin qu’elle utilise le même circuit de modération que les autres fiches, sans jamais devenir visible publiquement. Son e-mail de suivi et son lien avec l’établissement vivent dans `restaurant_submissions`, séparés des coordonnées publiques du restaurant. Les URLs proposées restent des destinations sortantes inactives, sans exposition HTML/JSON-LD avant validation humaine.

### D016 — Tests refusés hors configuration SQLite

La base de tests doit obligatoirement être SQLite. Le bootstrap `tests/TestCase.php` échoue avant toute migration si l’environnement ou la connexion active ne correspond pas à la configuration de PHPUnit ; un cache de configuration de préproduction ne peut donc plus faire exécuter des tests migratoires sur MariaDB.

### D017 — Compte administrateur humain et migration legacy figés

Le compte administrateur humain désigné par le propriétaire du projet est l’unique compte admin humain. Son mot de passe, son rôle et son statut ne peuvent être modifiés qu’à la demande explicite et ponctuelle du propriétaire. La migration WordPress est finalisée : la base legacy ne doit plus être interrogée, utilisée ni relancée sans une nouvelle autorisation explicite. Les validations de développement utilisent, lorsque nécessaire, un compte admin provisoire distinct.
