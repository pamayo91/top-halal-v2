# Validation — composant d’adresse intelligent (Phase 6A)

## Architecture

`AddressSuggestionService` encapsule l’abstraction `GeocodingService`/Géoplateforme et remet au client un jeton court, jamais un payload fournisseur. `RestaurantLocationService` applique une modification confirmée, qualifie la localisation et écrit dans `AdminAudit`. `DuplicateRestaurantDetector` ne produit que des candidats informatifs.

## Autocomplete et sélection

L’endpoint administrateur `GET /admin/location/autocomplete?q=` est authentifié, réservé au rôle admin et limité à 30 requêtes/minute. Trois caractères au minimum sont requis. Filament utilise le même service avec un debounce de 350 ms, le cache Géoplateforme existant, un timeout fournisseur et un résultat limité. Une sélection préremplit adresse, code postal, ville, code INSEE, pays, GPS, fournisseur, source, précision et score ; elle ne sauvegarde jamais seule.

## Carte et saisie manuelle

La carte Leaflet est réservée à l’onglet de localisation. Les tuiles sont configurables dans `config/location.php`. Un déplacement de marqueur synchronise les coordonnées consultables, conserve la provenance fournisseur, donne la précision/statut `MANUAL`, renseigne `manually_verified_at`, recalcule `proximity_status` à `REVIEW_REQUIRED` et crée l’audit existant. La saisie manuelle ambiguë reste possible sans inventer de code INSEE ni GPS, avec revue requise.

## Sécurité et garde-fous

L’adresse historique est uniquement en lecture. Les zones Geography V2 ne sont pas supprimées lors d’un changement d’adresse ; une incompatibilité de code INSEE est signalée pour revue. Les doublons utilisent distance, nom/adresse/téléphone normalisés, restent visibles comme avertissement et ne bloquent ni ne fusionnent. Le cas O Sha reste au 46 boulevard du Temple : aucun reverse GPS n’est déclenché ni consommé lors de l’ouverture/sauvegarde ; seul un choix explicite peut changer l’adresse.

## Tests et préproduction

Les tests ciblés sont dans `tests/Feature/AddressComponentTest.php` : contrat autocomplete, autorisation endpoint, mapping/INSEE, sélection, préservation O Sha, correction marqueur/audit et doublon informatif. `tests/e2e/admin-address-component.spec.ts` ouvre O Sha sans le modifier et contrôle le contenu, les coordonnées en lecture seule, la carte et la console.

## Limites avant Phase 6B

Le composant métier est réutilisable, mais aucun formulaire public, parcours propriétaire, workflow de modération public ou envoi d’e-mail n’est introduit dans cette phase.
