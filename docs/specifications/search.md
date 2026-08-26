# Recherche restaurants

`/restaurants` est une page de découverte SSR. Les paramètres `q`, `ville`, `categories[]`, `features[]`, `lat` et `lng` servent exclusivement à la recherche et restent `noindex,follow`, conformément à la politique SEO.

- La recherche textuelle porte sur le nom et la ville réellement migrés.
- Une ville/zone correspond à la ville normalisée ou à un terme géographique V2.
- Les catégories et services utilisent les relations V2 ; plusieurs choix sont combinés de façon restrictive.
- La pagination conserve les filtres mais reste non indexable au-delà de la première page.
- « Autour de moi » demande explicitement la géolocalisation navigateur après clic, transmet les coordonnées par POST puis redirige vers une URL de résultat. Aucune demande de position n’est faite au chargement.
- La distance est calculée côté MariaDB avec les coordonnées réellement renseignées ; les restaurants sans coordonnées ne sont pas présentés dans ce résultat.
