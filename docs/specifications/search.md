# Recherche restaurants

`/restaurants` est une page de découverte SSR. Les paramètres `q`, `ville`, `categories[]`, `features[]`, `lat` et `lng` servent exclusivement à la recherche et restent `noindex,follow`, conformément à la politique SEO.

- La recherche textuelle porte sur le nom et la ville réellement migrés.
- Une ville/zone correspond à la ville normalisée ou à un terme géographique V2.
- Les catégories et services utilisent les relations V2 ; plusieurs choix sont combinés de façon restrictive.
- La pagination conserve les filtres mais reste non indexable au-delà de la première page.
- « Autour de moi » demande explicitement la géolocalisation navigateur après clic, transmet les coordonnées par POST puis redirige vers une URL de résultat. Aucune demande de position n’est faite au chargement.
- La distance est calculée côté MariaDB avec les coordonnées réellement renseignées ; les restaurants sans coordonnées ne sont pas présentés dans ce résultat.

## Recherche publique à deux champs

L’accueil et l’annuaire réutilisent le même composant Blade SSR : une localisation et une spécialité ou un restaurant. Paris est sa valeur initiale. Les villes sont calculées depuis `restaurants.city_name` des seules fiches publiées, classées par volume, avec une liste courte et une recherche asynchrone légère ; aucune table `locations`, donnée redondante ni code INSEE ne sert à cette fonction. Une ville seule mène à `/restos/{Str::slug(city_name)}`.

Les suggestions, limitées et déclenchées après deux caractères, distinguent toutes les spécialités V2 et les restaurants publiés. Une spécialité est donc proposée dès sa création, même avant d’être associée à une fiche publiée. Les restaurants de la ville sélectionnée sont proposés en premier, sans exclure les autres villes. La sélection explicite d’un restaurant ouvre directement sa fiche.

« Autour de moi » ne demande la position qu’après le clic volontaire correspondant. Refus, indisponibilité et délai affichent « Impossible d’obtenir votre position. Choisissez une ville. » sans empêcher une recherche par ville. Les recherches et combinaisons de filtres restent sur `/restaurants` en `noindex,follow`; elles ne créent aucune nouvelle landing page SEO ou facette indexable.
