# Modération

Les avis et commentaires sont filtrables et passent de `pending` vers `approved`, `rejected` ou `spam`. Seuls les avis approuvés entrent dans l’agrégat public calculé à la demande. Les nouveaux enregistrements `pending` ont un `user_id` validé par le workflow commun de preuve d’identité; le back-office n’introduit aucun contournement. Les commentaires nouveaux gardent leur validation URL-free existante.

Les claims affichent utilisateur, restaurant, date, demande et statut. L’approbation associe le propriétaire uniquement par la relation `restaurant_claims` approuvée ; elle ne modifie pas un rôle restaurant technique. Les claims non attribuables ne sont pas générés.
