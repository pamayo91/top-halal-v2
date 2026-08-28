# Audit intégrité des données migrées

Généré le : `2026-08-28T21:46:59+00:00`
Mode : `correction appliquée`

## Dates
### Articles
- Legacy : 123 enregistrements ; création disponible 121 ; modification disponible 122.
- V2 avant : 122 enregistrements ; identités legacy 121 ; modification legacy 122.
- Publication V2 avant : 121.
- Publication V2 après : 121.

### Pages
- Legacy : 91 enregistrements ; création disponible 91 ; modification disponible 91.
- V2 avant : 90 enregistrements ; identités legacy 90 ; modification legacy 90.
- Publication V2 avant : 90.
- Publication V2 après : 90.

### Comments
- Legacy : 728 enregistrements ; création disponible 728 ; modification disponible 728.
- V2 avant : 730 enregistrements ; identités legacy 728.

### Reviews
- Legacy : 84 enregistrements ; création disponible 83 ; modification disponible 84.
- V2 avant : 79 enregistrements ; identités legacy 77.

### Restaurants
- Legacy : 7704 enregistrements ; création disponible 7634 ; modification disponible 7636.
- V2 avant : 7704 enregistrements ; identités legacy 7634 ; modification legacy 7636.

### Users
- Legacy : 545 enregistrements ; création disponible 545.
- V2 avant : 544 enregistrements ; identités legacy 544.

### Media
- Legacy : 2239 enregistrements ; création disponible 2239 ; modification disponible 2239.
- V2 avant : 1459 enregistrements ; identités legacy 1048.

Les `created_at`/`updated_at` V2 restent des traces V2 lorsqu’ils ne sont pas déjà historiques. Les dates WordPress sont conservées dans les champs `legacy_*`; les articles/pages utilisent aussi `published_at` pour la publication historique.

## Géographie
- Total avant : 1971 ; utilisées : 1961 ; inutilisées : 10.
- Valides : 1971 ; suspectes : 0 ; manifestement malveillantes : 0 ; vides : 0.
- Doublons potentiels (non fusionnés automatiquement) : 1.
- Supprimées : 0.
- Après correction : 1971 lieux ; utilisées : 1961 ; inutilisées : 10 ; malveillantes restantes : 0.

## Autres taxonomies
- categories : 22 entrées, 0 anomalie(s) détectée(s).
- features : 13 entrées, 0 anomalie(s) détectée(s).
- editorial_categories : 3 entrées, 0 anomalie(s) détectée(s).
- editorial_tags : 615 entrées, 0 anomalie(s) détectée(s).

## Corrections idempotentes
- Articles synchronisés : 0 ; pages : 0 ; restaurants : 0 ; médias : 0.
