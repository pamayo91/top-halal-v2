# Full migration reconciliation

Final idempotence run: `ab6713de-164a-4216-b320-4cb143e1b184` — completed with zero new records.

| Domaine | Legacy | V2 migré | Ignoré volontairement | Anomalie / explication |
|---|---:|---:|---:|---|
| Restaurants | 7,704 | 7,704 | 0 | 15,261 warnings de champs legacy, sans erreur de migration |
| Articles | 121 publiés + 1 draft | 121 publiés + 1 draft | 1 auto-draft | auto-draft exclu |
| Pages | 90 publiées | 90 | 1 trash | trash exclue |
| Commentaires humains | 713 approved + 15 pending | 728 | spam/pingbacks exclus | 0 |
| Avis ListingPro | 84 | 77 | 7 | rattachement/rating non fiables |
| Utilisateurs | 545 | 544 | 1 | e-mail absent |
| Claims | 52 | 0 | 52 | aucun claimant fiable, `user_id` jamais inventé |
| Attachments / médias référencés | 2,239 attachments; 695 inline refs | 1,459 assets; 562 relations inline | non référencés exclus | 3 inline sources 404 confirmées |

## Médias réellement perdus

- Post 27: `/wp-content/uploads/2013/01/rouleaux-300x225.jpg`
- Post 104: `/wp-content/uploads/2013/01/img_0159-1024x1024.jpg`
- Post 10755: `/wp-content/uploads/2014/01/telephone-hanane-2891-e1389007625493-300x289.jpg`

Ces trois URLs sont absentes des deux arbres legacy locaux et répondent HTTP 404 sur le site legacy. Tous les autres médias inline récupérables sont servis depuis V2 avec original, checksum, dimensions et variantes WebP. Aucun HTML V2 ne contient `wp-content` ou `wp-contenu`.
