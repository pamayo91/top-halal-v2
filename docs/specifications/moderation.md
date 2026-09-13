# Modération

Les avis et commentaires sont filtrables et passent de `pending` vers `approved`, `rejected` ou `spam`. Seuls les avis approuvés entrent dans l’agrégat public calculé à la demande. Les nouveaux enregistrements `pending` ont un `user_id` validé par le workflow commun de preuve d’identité; le back-office n’introduit aucun contournement. Les commentaires nouveaux gardent leur validation URL-free existante.

Les claims affichent utilisateur, restaurant, date, demande et statut. L’approbation associe le propriétaire uniquement par la relation `restaurant_claims` approuvée ; elle ne modifie pas un rôle restaurant technique. Les claims non attribuables ne sont pas générés.

## Signalements d’information

Les signalements de restaurant, article et page utilisent le même moteur et la même preuve d’identité que les avis/commentaires. Un visiteur sans preuve de session valide crée seulement une vérification temporaire à usage unique (24 h) ; le signalement `new` est créé après le clic, avec le `user_id` créé ou réutilisé. Une preuve valide ou un compte connecté crée directement le signalement. Un compte connecté conserve toujours sa propre identité, indépendamment de tout e-mail posté.

`editorial_content_reports` conserve le type, l’identifiant, le titre et l’URL publique du contenu, le message, le signalant, son état d’authentification et les dates. Les seuls statuts sont `new`, `in_progress`, `resolved` et `dismissed`. Le BO « Communauté > Signalements » permet leur consultation et ces changements ; aucun signalement ne modifie le contenu automatiquement. Un gestionnaire autorisé d’une fiche voit prioritairement le lien d’édition de la fiche, tout en gardant le signalement disponible.

Un lien de vérification expiré d’un signalement propose un renvoi limité, uniquement si le contenu reste publié et que l’identité n’est pas désactivée. Le renvoi remplace le hash du jeton; il ne crée aucun signalement avant le nouveau clic valide.
