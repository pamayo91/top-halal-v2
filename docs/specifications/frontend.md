# Front public

Le front public est rendu côté serveur avec Blade. Il n’utilise ni SPA, ni police distante, ni bibliothèque UI. La feuille CSS locale constitue le design system : couleurs, typographie système, boutons, formulaires, cartes, étiquettes, états vides et grilles responsive.

- L’en-tête conserve les parcours Restaurant, Guide et Compte ; le menu mobile est une amélioration progressive très légère.
- Lorsqu’un administrateur actif consulte une fiche restaurant, un article ou une page, l’en-tête rend un raccourci SSR « Éditer » vers son formulaire Filament ; les visiteurs et autres comptes ne reçoivent pas ce markup.
- Les pages publiques partagent une navigation clavier, un lien d’évitement, des fils d’Ariane et des états de formulaire accessibles.
- L’accueil, `/blog`, les collections, fiches, articles, pages, auth et compte sont des vues Blade responsives.
- Les images V2 ne pointent jamais vers WordPress : elles utilisent les variantes media locales, avec dimensions, `srcset`, `sizes` et chargement différé hors visuel principal.
- Les horaires ne sont jamais affichés en l’absence de données validées. Lorsqu’ils existent, ils sont affichés dans une carte compacte par jour, y compris les journées fermées, avec créneaux séparés et mise en évidence discrète du jour courant. Le statut ouvert/fermé est calculé au rendu Blade dans le fuseau `Europe/Paris` uniquement pour une journée complète et cohérente ; des créneaux absents ou incomplets ne donnent jamais lieu à une estimation. Les liens sortants sont exclusivement des routes opaques `/sortie/{token}` ; aucune destination n’apparaît dans le HTML ou le JSON-LD.
- Les avis et commentaires sont soumis à modération. Les URL sont refusées côté serveur et le contenu rendu est du texte sûr.
- Le parcours public d’ajout de restaurant est volontairement `noindex,nofollow`, SSR et sans compte. Son script optionnel charge la carte et Leaflet uniquement à l’étape Adresse ; aucune dépendance cartographique n’est chargée sur les autres pages publiques.
- La suite Playwright de non-régression préproduction vérifie les URLs sentinelles (accueil, recherche, fiches, blog, article, page, redirection, connexion et 404) : statut attendu, absence de 500, d'erreur console/réseau et de requête legacy.
