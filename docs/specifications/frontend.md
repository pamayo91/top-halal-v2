# Front public

Le front public est rendu côté serveur avec Blade. Il n’utilise ni SPA, ni police distante, ni bibliothèque UI. La feuille CSS locale constitue le design system : couleurs, typographie système, boutons, formulaires, cartes, étiquettes, états vides et grilles responsive.

- L’en-tête conserve les parcours Restaurant, Guide et Compte ; le menu mobile est une amélioration progressive très légère.
- Les pages publiques partagent une navigation clavier, un lien d’évitement, des fils d’Ariane et des états de formulaire accessibles.
- L’accueil, `/blog`, les collections, fiches, articles, pages, auth et compte sont des vues Blade responsives.
- Les images V2 ne pointent jamais vers WordPress : elles utilisent les variantes media locales, avec dimensions, `srcset`, `sizes` et chargement différé hors visuel principal.
- Les horaires ne sont jamais affichés en l’absence de données validées. Les liens sortants sont exclusivement des routes opaques `/sortie/{token}` ; aucune destination n’apparaît dans le HTML ou le JSON-LD.
- Les avis et commentaires sont soumis à modération. Les URL sont refusées côté serveur et le contenu rendu est du texte sûr.
