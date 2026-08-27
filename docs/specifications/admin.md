# Back-office V2

## Accès

Toutes les routes `/admin` exigent une session authentifiée, un mot de passe non bloqué et un utilisateur `admin` actif. Le middleware `admin` refuse les autres utilisateurs avec 403. Les formulaires web reposent sur la protection CSRF Laravel.

## Modules

Le tableau de bord expose des compteurs réels. L’administration propose la gestion des restaurants (y compris les liens sortants, stockés uniquement côté serveur), la modération des avis/commentaires/claims, les utilisateurs, articles/pages HTML sanitizés, médias raster, redirections, taxonomies et réglages non secrets.

Chaque opération sensible est enregistrée dans `admin_audit_logs`, avec l’administrateur, l’objet, l’action et un résumé filtré des changements. Les mots de passe, tokens et URL sortantes ne sont jamais mis dans ce résumé.

## Rôles

- `user`: accès au compte et aux revendications personnelles.
- `restaurant_owner`: mêmes accès et édition uniquement des restaurants revendiqués et approuvés.
- `admin`: accès complet au back-office.

Les secrets restent dans `.env`; les réglages V2 ne contiennent que des valeurs non sensibles. Une suppression de restaurant est un archivage. Les taxonomies associées à des restaurants ne peuvent pas être supprimées.
