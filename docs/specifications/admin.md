# Back-office V2

## Accès

Le panel Filament `/admin` exige une session authentifiée, un mot de passe non bloqué et un utilisateur `admin` actif. Le middleware `admin` refuse les autres utilisateurs avec 403. Les formulaires reposent sur la protection CSRF Laravel.

Après connexion, une URL `intended` historique ou non-back-office n’est jamais rejouée pour un administrateur : celui-ci arrive sur `/admin`.

Un administrateur marqué `must_change_password` est dirigé vers le changement de mot de passe, puis vers `/admin` une fois ce changement terminé.

## Modules

Le tableau de bord expose des compteurs réels. L’administration propose la gestion des restaurants (y compris les liens sortants, stockés uniquement côté serveur), la modération des avis/commentaires/claims, les utilisateurs, articles/pages HTML sanitizés, médias raster, redirections, taxonomies et réglages non secrets.

Chaque opération sensible est enregistrée dans `admin_audit_logs`, avec l’administrateur, l’objet, l’action et un résumé filtré des changements. Les mots de passe, tokens et URL sortantes ne sont jamais mis dans ce résumé.

## Localisation des restaurants

L’onglet « Localisation » sépare l’adresse d’origine, strictement consultable, de l’adresse structurée utilisée par V2. La recherche d’adresse est servie par Laravel (`GET /admin/location/autocomplete`) et l’abstraction Géoplateforme : aucun navigateur ne contacte directement le fournisseur. Une sélection ne sauvegarde pas la fiche ; elle préremplit les champs, l’admin confirme ensuite la modification normale.

La carte Leaflet est chargée uniquement dans cet onglet et son fournisseur de tuiles est configurable. Les coordonnées sont consultables mais ne se saisissent pas dans le parcours courant : un déplacement validé par un admin est de précision `MANUAL`, renseigne `manually_verified_at`, conserve l’identifiant du résultat fournisseur et crée une entrée d’audit avec les valeurs avant/après. Cette validation peut être `ELIGIBLE` si aucune anomalie ne subsiste ; une incompatibilité Geography reste à revoir. La provenance `public_map` est réservée au futur parcours public et ne constitue jamais une validation admin. Les associations Geography V2 ne sont jamais supprimées automatiquement ; un changement de code INSEE avec zones existantes est signalé pour revue. Les candidats doublons sont informatifs, sans fusion ni blocage.

Les listes affichent les dates métier pertinentes en format français : publication et dernière modification legacy pour les contenus, date de commentaire/avis et date de modération, inscription utilisateur, dates legacy des médias et restaurants. La géographie affiche le parent/niveau, le nombre de restaurants et la date de modification ; son filtre « Sans restaurant » rend les termes inutilisés immédiatement identifiables. Les valeurs manifestement techniques ou malveillantes sont refusées à la saisie.

## Rôles

- `user`: accès au compte et aux revendications personnelles.
- `restaurant_owner`: mêmes accès et édition uniquement des restaurants revendiqués et approuvés.
- `admin`: accès complet au back-office.

Les secrets restent dans `.env`; les réglages V2 ne contiennent que des valeurs non sensibles. L’action « Supprimer » disponible dans la liste et sur la fiche restaurant est un archivage réversible : elle retire la fiche des parcours publics, conserve les données et laisse une trace d’audit. Les taxonomies associées à des restaurants ne peuvent pas être supprimées.
