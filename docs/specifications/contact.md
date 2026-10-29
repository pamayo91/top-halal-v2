# Contact public

Le formulaire SSR `GET /contact` et `POST /contact` conserve un parcours sans JavaScript. Une soumission acceptée crée un unique `contact_messages`, puis seulement les Email Delivery Logs et les e-mails transactionnels déjà configurés.

## Anti-spam

Avant toute mutation métier, le POST applique un honeypot serveur, un jeton chiffré émis au GET (valide 24 h et soumis après au moins deux secondes), puis les limites Laravel : 5 IP / 10 min, 20 IP / 24 h et 5 e-mails normalisés / h. Les clés e-mail/IP du cache sont des HMAC ; Laravel utilise exclusivement l'adresse résolue par sa configuration de proxies de confiance et ne fait pas confiance aux headers proxy non configurés.

L'empreinte SHA-256 du nom, e-mail, sujet et message normalisés est unique pendant une heure. La contrainte BDD est le dernier rempart contre les POST concurrents : un doublon n'écrit ni message, ni log de livraison, ni job, ni e-mail. Après une heure, l'empreinte expirée est libérée transactionnellement afin qu'un vrai nouveau message identique reste possible.

Honeypot, délai invalide et doublon répondent par une confirmation neutre. Les limites de fréquence affichent un message compréhensible sans révéler les seuils. Les sorties `accepted`, `honeypot`, `time_trap`, `duplicate` et `rate_limit` sont journalisées dans le canal quotidien privé `contact-antispam`, avec seulement un hash HMAC de l'IP ; aucun contenu ni e-mail n'y est écrit.
