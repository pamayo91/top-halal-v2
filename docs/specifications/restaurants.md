# Restaurants

## Core entity
Restaurant records migrate from ListingPro listings and keep `legacy_wp_id`.
Suggested core data includes name/slug/description/status/owner, address/postcode/city/geolocation, contact info, pricing, halal/certification information, social/website URLs (private from crawlable public markup), media, hours, categories/features, verification/claim status and SEO fields.

## Geography
Normalize legacy flat locations into useful region/department/city/postcode relationships where data allows. Keep geocoordinates and support spatial/distance queries using MariaDB capabilities.

## Structured address contract

- `address` is retained unchanged as the historical/raw address for legacy records.
- `address_line1` contains only the number and street; `postal_code` and `city_name` are separate fields.
- `AddressLineParser` is the single deterministic implementation for this split. It removes a suffix only when the raw/structured postcode and city agree strictly and a non-empty street remains.
- A confirmed cleanup mode may remove an explicit final historical `CP + ville` from `address_line1` even when it conflicts with structured data; it never changes `address`, `postal_code` or `city_name`, and requires a non-empty street before the suffix.
- New or replacement addresses require a Géoplateforme selection resolved server-side. A usable selection supplies `address_line1`, postcode, city, INSEE code, country and valid GPS coordinates; no manual administrative/GPS entry is accepted.
- The persisted localisation contract is `address`, `address_line1`, `address_line2`, `postal_code`, `city_name`, `city_code`, `country_code`, `latitude` and `longitude`. The former migration-only confidence, precision, provenance and proximity qualification columns are not part of the model.
- Proximity search includes only non-null coordinates within latitude `[-90, 90]` and longitude `[-180, 180]`; it has no qualification-status dependency.
- Public restaurant pages and JSON-LD use only `address_line1`, `postal_code` and `city_name` whenever structured fields exist; they must not expose the historical/raw `address` in that case.

## Controlled legacy migration
- The pilot schema uses `restaurants`, `categories`, `features`, hierarchical `locations`, their pivots, `restaurant_opening_hours` and `restaurant_media`.
- Each imported entity keeps its WordPress identifier (`legacy_wp_id`, `legacy_term_id` or `legacy_attachment_id`).
- `legacy:migrate-restaurants` is read-only on `legacy_wp`; its default and `--dry-run` modes write no V2 data. `--apply --limit=10` is the only currently supported write mode.
- Missing gallery, hours, coordinates or location terms are reported record-by-record. Hours are only persisted when ListingPro contains an enabled, parseable value; no schedule is inferred.
- The 2026-08-25 legacy audit found no enabled, parseable ListingPro schedule across 7,704 listings. `restaurant_opening_hours` remains ready for future/admin-entered schedules, but no legacy record is inserted until a real source format exists.

## Public page
- Fast server-rendered content.
- Core info and opening hours. When validated hours are available, the public detail page renders a compact semantic schedule card with one stored day per row, separated services and an unobtrusive current-day highlight. A server-side `Europe/Paris` status is displayed only when the current schedule is complete: open (with closing time), between services (with the next opening time), closed for the day, or the next opening on the following day. Incomplete schedules retain their available display but never produce a guessed live status.
- categories/features.
- Services are rendered as local inline SVG icons plus their text labels. The icon set has no external dependency; three domain-specific icons (halal certification, prayer room and decor) follow the same 24px rounded-stroke style as the Lucide-derived icons.
- Reviews approved by moderation and their aggregate where valid; public reviews are displayed from newest to oldest.
- map/geographic JS loaded only when needed.
- external outbound actions obfuscated server-side.

## Admin/owner
Create/edit/moderate records, media, hours, categories/features and ownership/claims with clear auditability.

## Public restaurant proposal

`/ajouter-un-restaurant` is a `noindex,nofollow` public Blade form. It requires no account and is protected by Laravel CSRF, server-side validation and an e-mail/IP rate limit. JavaScript progressively enhances the five-step interaction; it is required solely for the mandatory remote address-selection control.

- Step 1 requires a restaurant name and at least one of `has_halal_meat` or `has_halal_chicken`; it performs an informative name-similarity lookup.
- Step 2 uses the reusable Géoplateforme address-selection service in the public form, Filament and owner-edit flow. Selecting a suggestion is mandatory: it is resolved again server-side and supplies `address_line1`, postcode, city, INSEE code, country and valid GPS. The public UI never exposes the INSEE code or manual postcode/city/GPS inputs. When the precise address is missing, the contributor selects the closest suggestion then moves the marker; this changes only latitude/longitude, does not reverse-geocode and never rewrites the selected structured address. Similarity candidates combine name, structured address and a 250m GPS radius without automatic merge or publication.
- Step 3 accepts optional categories, services (including an optional halal certification), simple seven-day schedules, contact data and private outbound destinations. External destinations are stored inactive and never rendered directly in public HTML or JSON-LD.
- Step 4 requires exactly one cover upload and accepts at most ten additional JPEG, PNG or WebP uploads. Every uploaded image, cover or gallery, must be at least 800 px wide. Files are revalidated by the server-side V2 media pipeline before a pending media relation is created.
- Step 5 stores the required e-mail both as the restaurant contact e-mail to be checked by moderation and in the private submission context with the contributor's relation to the restaurant (`owner`, `employee` or `customer`); no name or account is required. Selecting owner does not ask for further details. Claiming remains a separate authenticated flow.

A valid proposal creates a normal `restaurants` record with `status=pending`, never `published`, plus one private `restaurant_submissions` record for the contact/audit context. Filament’s existing pending status is the moderation queue; the e-mail is visible to administrators as the restaurant contact and the linked contributor context.

## Référentiel de spécialités

Les spécialités sont un référentiel V2 partagé : elles alimentent les filtres de l’annuaire, l’autocomplétion de recherche, le formulaire public, et la sélection d’administration. `Burger`, `Brunch` et `Grillades` sont des entrées natives V2 disponibles même lorsqu’aucune fiche publiée ne leur est encore associée.

Les illustrations associées aux spécialités peuvent servir de miniature de secours uniquement pour les fiches sans photo raster. Elles suivent l’ordre alphabétique déterministe de la première spécialité, sans remplacer ni créer une couverture de fiche.

## SEO par fiche
Le panneau SEO permet de sélectionner les directives `robots` actives (`all`, `noindex`, `nofollow`, `none`, `nosnippet`, `indexifembedded`, `noimageindex`, `notranslate`) et de paramétrer les valeurs qui nécessitent un argument : longueur d’extrait, aperçu image, aperçu vidéo et date `unavailable_after`. Les directives sont rendues dans la balise `meta name="robots"` de la fiche ; une fiche `noindex` ou `none` est exclue du sitemap.

Le titre par défaut d’une fiche publiée est `Restaurant {nom} Halal à {ville} spécialité {première spécialité}`, sans suffixe de marque. La première spécialité est déterminée par ordre alphabétique pour un résultat stable. Une valeur renseignée dans `seo_title` remplace intégralement ce titre généré.

## Non-régression

Une modification sans rapport dans Filament doit préserver les relations existantes de la fiche : médias, catégories, services, zones, avis, horaires et adresse/GPS. Le registre V2 de sentinelles compare ces relations exactement sur préproduction et bloque toute perte inattendue.
