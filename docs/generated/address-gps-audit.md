# Audit adresses et GPS — Top-Halal V2

Run UUID : `6e323329-4b79-422e-9497-70610f018281`
Mode : `strictement lecture seule` (les seules écritures sont les deux artefacts de rapport).

## Résumé

TOTAL RESTAURANTS : 7704

- Adresse complète + GPS : 4159
- Adresse complète sans GPS : 8
- Adresse partielle + GPS : 3437
- Adresse partielle sans GPS : 30
- Ville seule : 0
- GPS seul : 0
- Sans adresse ni GPS / invalide : 3
- Conflits locaux : 67

- Coordonnées présentes : 7663
- Coordonnées absentes : 41
- Coordonnées invalides ou suspectes : 0
- Restaurants à coordonnées dupliquées : 567

- Geography liée : 7678
- Sans Geography : 26
- Mismatch ville / Geography : 67

## Inventaire des champs

### V2

| Table | Champ | Type / nullable | Exemple |
| --- | --- | --- | --- |
| restaurants | address | varchar / nullable | valeur non exposée dans ce rapport |
| restaurants | postal_code | varchar(20) / nullable, indexé | valeur non exposée |
| restaurants | city_name | varchar / nullable, indexé | valeur non exposée |
| restaurants | latitude, longitude | decimal(10,7) / nullable | valeur non exposée |
| restaurants | legacy_wp_id | bigint / non nullable, unique | identifiant de réconciliation |
| locations + restaurant_location | name, slug, parent_id | relation N:N ; aucun code INSEE/département/région dédié | relation géographique legacy |

### Legacy WordPress

- Listings lus : 7704. Les coordonnées/adresses sont stockées dans `tp_postmeta` (notamment les payloads sérialisés `lp_listingpro_options` / `lp_listingpro_options_fields`), pas dans les options WordPress globales.
- Champs physiques pertinents : `posts.ID`, `posts.post_status`, `posts.post_name`, `postmeta.post_id/meta_key/meta_value`, `terms.name/slug`, `term_taxonomy.taxonomy/parent`.
- Aucun champ INSEE, département, région, pays, ligne 2 ou précision de géocodage n’existe dans le modèle V2 actuel.

Meta keys legacy candidates (occurrences) :
- `fave_property_map_address` : 7487
- `fave_property_location` : 7487
- `_yoast_wpseo_primary_location` : 4328

## Compteurs détaillés

- Publiés : 7633 ; autres statuts : 71.
- Adresse présente : 7701 ; vide : 3. Champs V2 dédiés renseignés : ville 0 ; code postal 0.
- Après extraction read-only du suffixe `CP ville` de l’adresse brute : ville 4234 ; code postal 4234 ; adresse + CP + ville : 4234 ; adresse sans CP : 3467 ; adresse sans ville : 3467 ; CP seul : 0.
- Latitude seule : 0 ; longitude seule : 0 ; 0,0 : 0 ; hors plage GPS : 0 ; possiblement inversées : 0.
- France métropolitaine : 7611 ; DOM/TOM : 49 ; hors France : 3.
- Coordonnées uniques : 7368 ; utilisées par 2 : 252 ; par 3+ : 20.
- Plusieurs Geography : 1. La compatibilité CP/Geography est non déterminable localement : `locations` ne porte aucun code postal.

## Anomalies (exemples plafonnés à 20)

### city_geography_mismatch
- {"v2_id":159,"legacy_wp_id":13590,"name":"Les Frangins","city":""}
- {"v2_id":540,"legacy_wp_id":13974,"name":"Louiva","city":""}
- {"v2_id":2816,"legacy_wp_id":16255,"name":"Au P'tit Creux","city":""}
- {"v2_id":2870,"legacy_wp_id":16309,"name":"Snack Kaya Laneuveville","city":""}
- {"v2_id":3119,"legacy_wp_id":16558,"name":"Akropole","city":""}
- {"v2_id":4594,"legacy_wp_id":18034,"name":"Family pizza sandwich","city":""}
- {"v2_id":6434,"legacy_wp_id":19890,"name":"Valleau Kebab Soni","city":""}
- {"v2_id":6440,"legacy_wp_id":19896,"name":"Sandwicherie et kebab","city":""}
- {"v2_id":6441,"legacy_wp_id":19897,"name":"Sos Pizza","city":""}
- {"v2_id":6442,"legacy_wp_id":19898,"name":"Basak kebab","city":""}
- {"v2_id":6445,"legacy_wp_id":19901,"name":"Damla Kebab","city":""}
- {"v2_id":6451,"legacy_wp_id":19907,"name":"Royal kébab","city":""}
- {"v2_id":6455,"legacy_wp_id":19911,"name":"Le Bertlows","city":""}
- {"v2_id":6460,"legacy_wp_id":19916,"name":"Nazar","city":""}
- {"v2_id":6464,"legacy_wp_id":19920,"name":"Ceylan kebab","city":""}
- {"v2_id":6465,"legacy_wp_id":19921,"name":"Istanbul Kebab","city":""}
- {"v2_id":6469,"legacy_wp_id":19925,"name":"Meram","city":""}
- {"v2_id":6476,"legacy_wp_id":19932,"name":"Le pizza kebab","city":""}
- {"v2_id":6486,"legacy_wp_id":19942,"name":"Chez Kuky 2","city":""}
- {"v2_id":6490,"legacy_wp_id":19946,"name":"Ponbou","city":""}

### placeholder
- {"v2_id":3183,"legacy_wp_id":16622,"name":"Turquoise Kebab","city":""}
- {"v2_id":3200,"legacy_wp_id":16639,"name":"La Turka","city":""}
- {"v2_id":3215,"legacy_wp_id":16654,"name":"Le Pintcho","city":""}
- {"v2_id":3224,"legacy_wp_id":16663,"name":"Le Bosphore","city":""}
- {"v2_id":6350,"legacy_wp_id":19806,"name":"Aux Caprices","city":""}

## Clusters GPS dupliqués (50 plus grands)

- `42.5520848,3.0459592` : 4 restaurant(s) — [{"v2_id":331,"legacy_wp_id":13762,"name":"Cappadoce Kebab","city":""},{"v2_id":333,"legacy_wp_id":13764,"name":"Chicken Chica","city":""},{"v2_id":335,"legacy_wp_id":13766,"name":"Le Roi du Kebab","city":""},{"v2_id":337,"legacy_wp_id":13768,"name":"Sea Snack And Sun","city":""}]
- `48.9218094,2.2774733` : 4 restaurant(s) — [{"v2_id":864,"legacy_wp_id":14298,"name":"OCheez Sandwicherie","city":""},{"v2_id":865,"legacy_wp_id":14299,"name":"Le Buffalo","city":""},{"v2_id":867,"legacy_wp_id":14301,"name":"Le buffalo (bois colombes)","city":""},{"v2_id":870,"legacy_wp_id":14304,"name":"Shah jalal","city":""}]
- `49.0351736,2.0763492` : 4 restaurant(s) — [{"v2_id":1327,"legacy_wp_id":14761,"name":"Bosphore Restauration","city":""},{"v2_id":1330,"legacy_wp_id":14764,"name":"La Mer Noire","city":""},{"v2_id":1331,"legacy_wp_id":14765,"name":"Mg kebab","city":""},{"v2_id":1332,"legacy_wp_id":14766,"name":"Zozan","city":""}]
- `47.4737972,-0.5591289` : 3 restaurant(s) — [{"v2_id":223,"legacy_wp_id":13654,"name":"Snack le relais du chateau","city":""},{"v2_id":234,"legacy_wp_id":13665,"name":"Al Majd","city":""},{"v2_id":235,"legacy_wp_id":13666,"name":"Plaisir Sucré Salé","city":""}]
- `48.7408031,2.2812801` : 3 restaurant(s) — [{"v2_id":311,"legacy_wp_id":13742,"name":"Pizza express","city":""},{"v2_id":312,"legacy_wp_id":13743,"name":"Pizza express","city":""},{"v2_id":313,"legacy_wp_id":13744,"name":"Le bun's d'antony","city":""}]
- `48.8039272,2.3118813` : 3 restaurant(s) — [{"v2_id":584,"legacy_wp_id":14018,"name":"O Petit Creux","city":""},{"v2_id":592,"legacy_wp_id":14026,"name":"Las pizzas","city":""},{"v2_id":594,"legacy_wp_id":14028,"name":"O petit creux","city":""}]
- `48.7891523,2.3188435` : 3 restaurant(s) — [{"v2_id":595,"legacy_wp_id":14029,"name":"Au tagine gourmet","city":""},{"v2_id":597,"legacy_wp_id":14031,"name":"Carre pizza","city":""},{"v2_id":598,"legacy_wp_id":14032,"name":"Chicken city 92","city":""}]
- `48.7517267,2.5051347` : 3 restaurant(s) — [{"v2_id":874,"legacy_wp_id":14308,"name":"Tamaris","city":""},{"v2_id":876,"legacy_wp_id":14310,"name":"Bullut","city":""},{"v2_id":878,"legacy_wp_id":14312,"name":"Tamaris","city":""}]
- `48.6233903,2.4223944` : 3 restaurant(s) — [{"v2_id":1866,"legacy_wp_id":15304,"name":"Good'dwich","city":""},{"v2_id":1867,"legacy_wp_id":15305,"name":"O'sushic","city":""},{"v2_id":1868,"legacy_wp_id":15306,"name":"Pizz n go","city":""}]
- `48.9058158,2.2540068` : 3 restaurant(s) — [{"v2_id":2753,"legacy_wp_id":16192,"name":"Pasta gusty","city":""},{"v2_id":2755,"legacy_wp_id":16194,"name":"Klass food","city":""},{"v2_id":2758,"legacy_wp_id":16197,"name":"Klass'Food","city":""}]
- `43.2977548,5.3729501` : 3 restaurant(s) — [{"v2_id":3647,"legacy_wp_id":17087,"name":"Tom Pouce","city":""},{"v2_id":3678,"legacy_wp_id":17118,"name":"Snack Amon","city":""},{"v2_id":3686,"legacy_wp_id":17126,"name":"Le Djerba","city":""}]
- `43.6114110,3.8786925` : 3 restaurant(s) — [{"v2_id":4037,"legacy_wp_id":17477,"name":"Hip Hop's Foods","city":""},{"v2_id":4050,"legacy_wp_id":17490,"name":"Régal Kébab","city":""},{"v2_id":4052,"legacy_wp_id":17492,"name":"L'Indien","city":""}]
- `47.2137974,-1.5578538` : 3 restaurant(s) — [{"v2_id":4276,"legacy_wp_id":17716,"name":"Délices d'Istanbul","city":""},{"v2_id":4278,"legacy_wp_id":17718,"name":"Régal Kebab","city":""},{"v2_id":4288,"legacy_wp_id":17728,"name":"Au kebab de Dilan","city":""}]
- `48.8911402,2.3499902` : 3 restaurant(s) — [{"v2_id":4820,"legacy_wp_id":18273,"name":"Gui's Fast","city":""},{"v2_id":5006,"legacy_wp_id":18459,"name":"Chez Karim - Resto grec plat algérien","city":""},{"v2_id":5267,"legacy_wp_id":18721,"name":"Hana","city":""}]
- `48.9401981,2.3563194` : 3 restaurant(s) — [{"v2_id":5974,"legacy_wp_id":19430,"name":"129","city":""},{"v2_id":6014,"legacy_wp_id":19470,"name":"New taj mahal","city":""},{"v2_id":6036,"legacy_wp_id":19492,"name":"Au sushi","city":""}]
- `48.8083176,2.4714645` : 3 restaurant(s) — [{"v2_id":6102,"legacy_wp_id":19558,"name":"Kydam","city":""},{"v2_id":6106,"legacy_wp_id":19562,"name":"Kydam sandwicherie","city":""},{"v2_id":6108,"legacy_wp_id":19564,"name":"Kydam pizza","city":""}]
- `45.7238368,4.8724369` : 3 restaurant(s) — [{"v2_id":7144,"legacy_wp_id":20601,"name":"Mis kebab","city":""},{"v2_id":7146,"legacy_wp_id":20603,"name":"Vegas pizza","city":""},{"v2_id":7147,"legacy_wp_id":20604,"name":"Pizzeria Lyon Vénissieux","city":""}]
- `45.7715487,4.8632909` : 3 restaurant(s) — [{"v2_id":7343,"legacy_wp_id":20800,"name":"Ayasofia","city":""},{"v2_id":7354,"legacy_wp_id":20811,"name":"Aya Sofia","city":""},{"v2_id":7376,"legacy_wp_id":20833,"name":"Ayasofia","city":""}]
- `48.8476865,2.4398258` : 3 restaurant(s) — [{"v2_id":7409,"legacy_wp_id":20866,"name":"Megna","city":""},{"v2_id":7412,"legacy_wp_id":20870,"name":"Restaurant Titanic","city":""},{"v2_id":7414,"legacy_wp_id":20872,"name":"Croq Fontenay","city":""}]
- `48.8425682,2.4354935` : 3 restaurant(s) — [{"v2_id":7410,"legacy_wp_id":20868,"name":"L'escale","city":""},{"v2_id":7411,"legacy_wp_id":20869,"name":"Le Pacha","city":""},{"v2_id":7416,"legacy_wp_id":20874,"name":"Restaurant Deniz","city":""}]
- `48.7895556,2.4256291` : 2 restaurant(s) — [{"v2_id":3,"legacy_wp_id":13567,"name":"HAYAT","city":""},{"v2_id":132,"legacy_wp_id":13562,"name":"Homewok","city":""}]
- `48.9505103,2.0642055` : 2 restaurant(s) — [{"v2_id":31,"legacy_wp_id":13460,"name":"Noodle ness","city":""},{"v2_id":34,"legacy_wp_id":13463,"name":"Le Dawliz","city":""}]
- `43.5280032,5.4460479` : 2 restaurant(s) — [{"v2_id":63,"legacy_wp_id":13493,"name":"Au Bon Coin","city":""},{"v2_id":65,"legacy_wp_id":13495,"name":"Les Arcades","city":""}]
- `48.8136825,2.4142428` : 2 restaurant(s) — [{"v2_id":121,"legacy_wp_id":13551,"name":"Wonder Food","city":""},{"v2_id":130,"legacy_wp_id":13560,"name":"Wonder food","city":""}]
- `48.7897445,2.4255124` : 2 restaurant(s) — [{"v2_id":123,"legacy_wp_id":13553,"name":"H-express","city":""},{"v2_id":131,"legacy_wp_id":13561,"name":"Al farooj","city":""}]
- `48.8144598,2.4134616` : 2 restaurant(s) — [{"v2_id":128,"legacy_wp_id":13558,"name":"Grec Express","city":""},{"v2_id":135,"legacy_wp_id":13565,"name":"Thai bamboo","city":""}]
- `49.8914471,2.3062671` : 2 restaurant(s) — [{"v2_id":161,"legacy_wp_id":13592,"name":"Le Bosphore","city":""},{"v2_id":162,"legacy_wp_id":13593,"name":"Topkapi","city":""}]
- `49.8882596,2.2806087` : 2 restaurant(s) — [{"v2_id":164,"legacy_wp_id":13595,"name":"Agadir kebab","city":""},{"v2_id":192,"legacy_wp_id":13623,"name":"Agadi","city":""}]
- `49.8988485,2.3007659` : 2 restaurant(s) — [{"v2_id":193,"legacy_wp_id":13624,"name":"Naxos","city":""},{"v2_id":200,"legacy_wp_id":13631,"name":"Pizza Délices 62","city":""}]
- `47.4514200,-0.5571122` : 2 restaurant(s) — [{"v2_id":217,"legacy_wp_id":13648,"name":"Euro doner","city":""},{"v2_id":224,"legacy_wp_id":13655,"name":"Pacha Kebab","city":""}]
- `48.8067036,2.3355164` : 2 restaurant(s) — [{"v2_id":324,"legacy_wp_id":13755,"name":"Zem Zem","city":""},{"v2_id":326,"legacy_wp_id":13757,"name":"Le zem zem","city":""}]
- `42.5522341,3.0473028` : 2 restaurant(s) — [{"v2_id":330,"legacy_wp_id":13761,"name":"Chez Simon","city":""},{"v2_id":332,"legacy_wp_id":13763,"name":"Big Burger 28","city":""}]
- `48.9559182,2.2554995` : 2 restaurant(s) — [{"v2_id":351,"legacy_wp_id":13782,"name":"Le Régal","city":""},{"v2_id":375,"legacy_wp_id":13806,"name":"Le Méditerranée","city":""}]
- `48.9433156,2.2487290` : 2 restaurant(s) — [{"v2_id":353,"legacy_wp_id":13784,"name":"Les Jumeaux","city":""},{"v2_id":391,"legacy_wp_id":13822,"name":"Les jumeaux","city":""}]
- `48.9412365,2.2151238` : 2 restaurant(s) — [{"v2_id":354,"legacy_wp_id":13785,"name":"Monkey Pizza","city":""},{"v2_id":360,"legacy_wp_id":13791,"name":"Burger Palace","city":""}]
- `48.9462655,2.2540174` : 2 restaurant(s) — [{"v2_id":361,"legacy_wp_id":13792,"name":"Le grand pacha","city":""},{"v2_id":395,"legacy_wp_id":13826,"name":"Le grand pacha","city":""}]
- `48.9588704,2.2576693` : 2 restaurant(s) — [{"v2_id":364,"legacy_wp_id":13795,"name":"Le Spécial Koudou","city":""},{"v2_id":381,"legacy_wp_id":13812,"name":"Le spécial sandwicherie","city":""}]
- `48.9321056,2.2310975` : 2 restaurant(s) — [{"v2_id":368,"legacy_wp_id":13799,"name":"Le Spécial Koudou","city":""},{"v2_id":385,"legacy_wp_id":13816,"name":"Le spécial sandwicherie","city":""}]
- `48.9354995,2.2352335` : 2 restaurant(s) — [{"v2_id":372,"legacy_wp_id":13803,"name":"La Patate","city":""},{"v2_id":394,"legacy_wp_id":13825,"name":"La patate","city":""}]
- `48.9399135,2.2238121` : 2 restaurant(s) — [{"v2_id":376,"legacy_wp_id":13807,"name":"Le Fast","city":""},{"v2_id":377,"legacy_wp_id":13808,"name":"Shai wam","city":""}]
- `48.9349536,2.2305287` : 2 restaurant(s) — [{"v2_id":382,"legacy_wp_id":13813,"name":"Le manga","city":""},{"v2_id":386,"legacy_wp_id":13817,"name":"Di napoli pizza","city":""}]
- `48.9186093,2.3825913` : 2 restaurant(s) — [{"v2_id":454,"legacy_wp_id":13886,"name":"Mamoun Chicken","city":""},{"v2_id":497,"legacy_wp_id":13929,"name":"Le majesté","city":""}]
- `48.9139479,2.3885734` : 2 restaurant(s) — [{"v2_id":459,"legacy_wp_id":13891,"name":"O'Chicanos","city":""},{"v2_id":487,"legacy_wp_id":13919,"name":"Pekin express asian food","city":""}]
- `48.9104452,2.3806020` : 2 restaurant(s) — [{"v2_id":478,"legacy_wp_id":13910,"name":"Louiva","city":""},{"v2_id":540,"legacy_wp_id":13974,"name":"Louiva","city":""}]
- `47.1933457,5.3865815` : 2 restaurant(s) — [{"v2_id":554,"legacy_wp_id":13988,"name":"Auxonne Kebab","city":""},{"v2_id":556,"legacy_wp_id":13990,"name":"Auxonne kebab","city":""}]
- `48.8644227,2.4179349` : 2 restaurant(s) — [{"v2_id":606,"legacy_wp_id":14040,"name":"O'Délices","city":""},{"v2_id":610,"legacy_wp_id":14044,"name":"O' delices - delices med","city":""}]
- `46.6410400,4.8702718` : 2 restaurant(s) — [{"v2_id":671,"legacy_wp_id":14105,"name":"Kervan kebab","city":""},{"v2_id":6363,"legacy_wp_id":19819,"name":"Kervan Saray kebab","city":""}]
- `49.1611884,2.3014396` : 2 restaurant(s) — [{"v2_id":720,"legacy_wp_id":14154,"name":"Le 44","city":""},{"v2_id":721,"legacy_wp_id":14155,"name":"Le 44","city":""}]
- `47.2447450,6.0037010` : 2 restaurant(s) — [{"v2_id":744,"legacy_wp_id":14178,"name":"Mega Kebab","city":""},{"v2_id":746,"legacy_wp_id":14180,"name":"Chez Oktay","city":""}]
- `48.9269501,2.2148591` : 2 restaurant(s) — [{"v2_id":781,"legacy_wp_id":14215,"name":"Restaurant Helin","city":""},{"v2_id":782,"legacy_wp_id":14216,"name":"Au 148","city":""}]

## Doublons potentiels (groupes plafonnés à 50)

### same_name_address
- [{"v2_id":7677,"legacy_wp_id":22556,"name":"Mr.","city":""},{"v2_id":7678,"legacy_wp_id":22560,"name":"Mr.","city":""},{"v2_id":7679,"legacy_wp_id":22575,"name":"Mr.","city":""},{"v2_id":7680,"legacy_wp_id":22579,"name":"Mr.","city":""},{"v2_id":7681,"legacy_wp_id":22580,"name":"Mr.","city":""},{"v2_id":7682,"legacy_wp_id":22585,"name":"Mr.","city":""},{"v2_id":7683,"legacy_wp_id":22596,"name":"Mr.","city":""},{"v2_id":7684,"legacy_wp_id":22600,"name":"Mr.","city":""},{"v2_id":7685,"legacy_wp_id":22606,"name":"Mr.","city":""},{"v2_id":7686,"legacy_wp_id":22619,"name":"Mr.","city":""},{"v2_id":7687,"legacy_wp_id":22625,"name":"Mr.","city":""},{"v2_id":7688,"legacy_wp_id":22629,"name":"Mr.","city":""},{"v2_id":7689,"legacy_wp_id":22689,"name":"Mr.","city":""},{"v2_id":7690,"legacy_wp_id":22690,"name":"Mr.","city":""},{"v2_id":7691,"legacy_wp_id":22691,"name":"Mr.","city":""},{"v2_id":7692,"legacy_wp_id":22693,"name":"Mr.","city":""},{"v2_id":7693,"legacy_wp_id":22699,"name":"Mr.","city":""},{"v2_id":7694,"legacy_wp_id":22707,"name":"Mr.","city":""},{"v2_id":7695,"legacy_wp_id":22718,"name":"Mr.","city":""},{"v2_id":7696,"legacy_wp_id":22722,"name":"Mr.","city":""}]
- [{"v2_id":7343,"legacy_wp_id":20800,"name":"Ayasofia","city":""},{"v2_id":7354,"legacy_wp_id":20811,"name":"Aya Sofia","city":""},{"v2_id":7376,"legacy_wp_id":20833,"name":"Ayasofia","city":""}]
- [{"v2_id":311,"legacy_wp_id":13742,"name":"Pizza express","city":""},{"v2_id":312,"legacy_wp_id":13743,"name":"Pizza express","city":""}]
- [{"v2_id":353,"legacy_wp_id":13784,"name":"Les Jumeaux","city":""},{"v2_id":391,"legacy_wp_id":13822,"name":"Les jumeaux","city":""}]
- [{"v2_id":361,"legacy_wp_id":13792,"name":"Le grand pacha","city":""},{"v2_id":395,"legacy_wp_id":13826,"name":"Le grand pacha","city":""}]
- [{"v2_id":372,"legacy_wp_id":13803,"name":"La Patate","city":""},{"v2_id":394,"legacy_wp_id":13825,"name":"La patate","city":""}]
- [{"v2_id":554,"legacy_wp_id":13988,"name":"Auxonne Kebab","city":""},{"v2_id":556,"legacy_wp_id":13990,"name":"Auxonne kebab","city":""}]
- [{"v2_id":584,"legacy_wp_id":14018,"name":"O Petit Creux","city":""},{"v2_id":594,"legacy_wp_id":14028,"name":"O petit creux","city":""}]
- [{"v2_id":874,"legacy_wp_id":14308,"name":"Tamaris","city":""},{"v2_id":878,"legacy_wp_id":14312,"name":"Tamaris","city":""}]
- [{"v2_id":875,"legacy_wp_id":14309,"name":"L'escale","city":""},{"v2_id":877,"legacy_wp_id":14311,"name":"L'escale","city":""}]
- [{"v2_id":1010,"legacy_wp_id":14444,"name":"Kebab du Soleil","city":""},{"v2_id":1013,"legacy_wp_id":14447,"name":"Kebab du soleil","city":""}]
- [{"v2_id":1588,"legacy_wp_id":15023,"name":"Mac Kenzi","city":""},{"v2_id":1592,"legacy_wp_id":15027,"name":"Mac kenzi","city":""}]
- [{"v2_id":1757,"legacy_wp_id":15193,"name":"Half Time","city":""},{"v2_id":1771,"legacy_wp_id":15207,"name":"Half time","city":""}]
- [{"v2_id":2072,"legacy_wp_id":15510,"name":"Le mirage","city":""},{"v2_id":2082,"legacy_wp_id":15520,"name":"Le mirage","city":""}]
- [{"v2_id":2181,"legacy_wp_id":15619,"name":"La Baraka","city":""},{"v2_id":2182,"legacy_wp_id":15620,"name":"La baraka","city":""}]
- [{"v2_id":2639,"legacy_wp_id":16078,"name":"Select Food","city":""},{"v2_id":2652,"legacy_wp_id":16091,"name":"Select food","city":""}]
- [{"v2_id":2850,"legacy_wp_id":16289,"name":"Go Food","city":""},{"v2_id":2852,"legacy_wp_id":16291,"name":"Go food","city":""}]
- [{"v2_id":2917,"legacy_wp_id":16356,"name":"L'Idéal","city":""},{"v2_id":2922,"legacy_wp_id":16361,"name":"L'ideal","city":""}]
- [{"v2_id":3094,"legacy_wp_id":16533,"name":"Crousty Time","city":""},{"v2_id":3098,"legacy_wp_id":16537,"name":"Crousty time","city":""}]
- [{"v2_id":3191,"legacy_wp_id":16630,"name":"Timgad","city":""},{"v2_id":3240,"legacy_wp_id":16679,"name":"Timgad","city":""}]
- [{"v2_id":3557,"legacy_wp_id":16997,"name":"Istanbul kebab","city":""},{"v2_id":3559,"legacy_wp_id":16999,"name":"Istanbul kebab","city":""}]
- [{"v2_id":3666,"legacy_wp_id":17106,"name":"L'Escale","city":""},{"v2_id":3693,"legacy_wp_id":17133,"name":"L'escale","city":""}]
- [{"v2_id":3778,"legacy_wp_id":17218,"name":"Snack Cappadoce","city":""},{"v2_id":3819,"legacy_wp_id":17259,"name":"Snack Cappadoce","city":""}]
- [{"v2_id":4171,"legacy_wp_id":17611,"name":"Au P'tit Prince","city":""},{"v2_id":4178,"legacy_wp_id":17618,"name":"Au p'tit prince","city":""}]
- [{"v2_id":4495,"legacy_wp_id":17935,"name":"Sushina","city":""},{"v2_id":4498,"legacy_wp_id":17938,"name":"Sushi na","city":""}]
- [{"v2_id":4558,"legacy_wp_id":17998,"name":"La Médina","city":""},{"v2_id":4562,"legacy_wp_id":18002,"name":"La medina","city":""}]
- [{"v2_id":4612,"legacy_wp_id":18053,"name":"Snack Time","city":""},{"v2_id":4622,"legacy_wp_id":18063,"name":"Snack time","city":""}]
- [{"v2_id":4633,"legacy_wp_id":18075,"name":"Pizza time","city":""},{"v2_id":5199,"legacy_wp_id":18653,"name":"Pizza time","city":""}]
- [{"v2_id":4636,"legacy_wp_id":18080,"name":"Afrik'n'fusion","city":""},{"v2_id":5280,"legacy_wp_id":18734,"name":"Afrik'n'fusion","city":""}]
- [{"v2_id":4637,"legacy_wp_id":18082,"name":"Allo pizza","city":""},{"v2_id":5335,"legacy_wp_id":18789,"name":"Allo pizza","city":""}]
- [{"v2_id":4639,"legacy_wp_id":18086,"name":"Asian Food","city":""},{"v2_id":5316,"legacy_wp_id":18770,"name":"Asian food","city":""}]
- [{"v2_id":4640,"legacy_wp_id":18088,"name":"Atlas couscous","city":""},{"v2_id":5253,"legacy_wp_id":18707,"name":"Atlas couscous","city":""}]
- [{"v2_id":4641,"legacy_wp_id":18090,"name":"Au poulet braisé","city":""},{"v2_id":5334,"legacy_wp_id":18788,"name":"Au poulet braisé","city":""}]
- [{"v2_id":4643,"legacy_wp_id":18094,"name":"Bhai bhai","city":""},{"v2_id":5301,"legacy_wp_id":18755,"name":"Bhai bhai","city":""}]
- [{"v2_id":4956,"legacy_wp_id":18409,"name":"Kifak","city":""},{"v2_id":5281,"legacy_wp_id":18735,"name":"Kifak","city":""}]
- [{"v2_id":5013,"legacy_wp_id":18466,"name":"L'escapade","city":""},{"v2_id":5261,"legacy_wp_id":18715,"name":"L'escapade","city":""}]
- [{"v2_id":5018,"legacy_wp_id":18471,"name":"Les Frangins","city":""},{"v2_id":5265,"legacy_wp_id":18719,"name":"Les frangins","city":""}]
- [{"v2_id":5147,"legacy_wp_id":18601,"name":"Le Cedre","city":""},{"v2_id":5219,"legacy_wp_id":18673,"name":"Le cedre","city":""}]
- [{"v2_id":5585,"legacy_wp_id":19041,"name":"Faster Food","city":""},{"v2_id":5594,"legacy_wp_id":19050,"name":"Faster food","city":""}]
- [{"v2_id":5625,"legacy_wp_id":19081,"name":"Régal kebab","city":""},{"v2_id":5626,"legacy_wp_id":19082,"name":"Régal Kebab","city":""}]
- [{"v2_id":5736,"legacy_wp_id":19192,"name":"Karadeniz kebab","city":""},{"v2_id":5737,"legacy_wp_id":19193,"name":"Karadeniz Kebab","city":""}]
- [{"v2_id":5805,"legacy_wp_id":19261,"name":"Indian food","city":""},{"v2_id":5806,"legacy_wp_id":19262,"name":"INDIAN FOOD","city":""}]
- [{"v2_id":5977,"legacy_wp_id":19433,"name":"Pizza Salam","city":""},{"v2_id":6027,"legacy_wp_id":19483,"name":"Pizza salam","city":""}]
- [{"v2_id":6003,"legacy_wp_id":19459,"name":"Le Four Pizzeria","city":""},{"v2_id":6030,"legacy_wp_id":19486,"name":"Le four pizzeria","city":""}]
- [{"v2_id":6749,"legacy_wp_id":20206,"name":"Oumma Burger","city":""},{"v2_id":6751,"legacy_wp_id":20208,"name":"Oumma burger","city":""}]
- [{"v2_id":7170,"legacy_wp_id":20627,"name":"L'Orient Fast","city":""},{"v2_id":7173,"legacy_wp_id":20630,"name":"L'orient fast","city":""}]
- [{"v2_id":7291,"legacy_wp_id":20748,"name":"Le Neuf 4","city":""},{"v2_id":7293,"legacy_wp_id":20750,"name":"Le neuf 4","city":""}]
- [{"v2_id":7296,"legacy_wp_id":20753,"name":"Mama gaya","city":""},{"v2_id":7304,"legacy_wp_id":20761,"name":"Mamagaya","city":""}]
- [{"v2_id":7340,"legacy_wp_id":20797,"name":"Le Palmier d'Or","city":""},{"v2_id":7387,"legacy_wp_id":20844,"name":"Le palmier d'or","city":""}]
- [{"v2_id":7372,"legacy_wp_id":20829,"name":"Le Delta du Strauss","city":""},{"v2_id":7386,"legacy_wp_id":20843,"name":"Le delta du strauss","city":""}]

### same_name_gps
- [{"v2_id":7343,"legacy_wp_id":20800,"name":"Ayasofia","city":""},{"v2_id":7354,"legacy_wp_id":20811,"name":"Aya Sofia","city":""},{"v2_id":7376,"legacy_wp_id":20833,"name":"Ayasofia","city":""}]
- [{"v2_id":121,"legacy_wp_id":13551,"name":"Wonder Food","city":""},{"v2_id":130,"legacy_wp_id":13560,"name":"Wonder food","city":""}]
- [{"v2_id":311,"legacy_wp_id":13742,"name":"Pizza express","city":""},{"v2_id":312,"legacy_wp_id":13743,"name":"Pizza express","city":""}]
- [{"v2_id":353,"legacy_wp_id":13784,"name":"Les Jumeaux","city":""},{"v2_id":391,"legacy_wp_id":13822,"name":"Les jumeaux","city":""}]
- [{"v2_id":361,"legacy_wp_id":13792,"name":"Le grand pacha","city":""},{"v2_id":395,"legacy_wp_id":13826,"name":"Le grand pacha","city":""}]
- [{"v2_id":372,"legacy_wp_id":13803,"name":"La Patate","city":""},{"v2_id":394,"legacy_wp_id":13825,"name":"La patate","city":""}]
- [{"v2_id":478,"legacy_wp_id":13910,"name":"Louiva","city":""},{"v2_id":540,"legacy_wp_id":13974,"name":"Louiva","city":""}]
- [{"v2_id":554,"legacy_wp_id":13988,"name":"Auxonne Kebab","city":""},{"v2_id":556,"legacy_wp_id":13990,"name":"Auxonne kebab","city":""}]
- [{"v2_id":584,"legacy_wp_id":14018,"name":"O Petit Creux","city":""},{"v2_id":594,"legacy_wp_id":14028,"name":"O petit creux","city":""}]
- [{"v2_id":720,"legacy_wp_id":14154,"name":"Le 44","city":""},{"v2_id":721,"legacy_wp_id":14155,"name":"Le 44","city":""}]
- [{"v2_id":828,"legacy_wp_id":14262,"name":"Nour kebab","city":""},{"v2_id":834,"legacy_wp_id":14268,"name":"Nour Kebab","city":""}]
- [{"v2_id":874,"legacy_wp_id":14308,"name":"Tamaris","city":""},{"v2_id":878,"legacy_wp_id":14312,"name":"Tamaris","city":""}]
- [{"v2_id":875,"legacy_wp_id":14309,"name":"L'escale","city":""},{"v2_id":877,"legacy_wp_id":14311,"name":"L'escale","city":""}]
- [{"v2_id":1010,"legacy_wp_id":14444,"name":"Kebab du Soleil","city":""},{"v2_id":1013,"legacy_wp_id":14447,"name":"Kebab du soleil","city":""}]
- [{"v2_id":1029,"legacy_wp_id":14463,"name":"Le kebab","city":""},{"v2_id":1030,"legacy_wp_id":14464,"name":"Le kebab","city":""}]
- [{"v2_id":1143,"legacy_wp_id":14577,"name":"Deeg's","city":""},{"v2_id":1144,"legacy_wp_id":14578,"name":"Deeg s","city":""}]
- [{"v2_id":1329,"legacy_wp_id":14763,"name":"Le Bon Détour","city":""},{"v2_id":1870,"legacy_wp_id":15308,"name":"Le bon detour","city":""}]
- [{"v2_id":1588,"legacy_wp_id":15023,"name":"Mac Kenzi","city":""},{"v2_id":1592,"legacy_wp_id":15027,"name":"Mac kenzi","city":""}]
- [{"v2_id":1757,"legacy_wp_id":15193,"name":"Half Time","city":""},{"v2_id":1771,"legacy_wp_id":15207,"name":"Half time","city":""}]
- [{"v2_id":2072,"legacy_wp_id":15510,"name":"Le mirage","city":""},{"v2_id":2082,"legacy_wp_id":15520,"name":"Le mirage","city":""}]
- [{"v2_id":2181,"legacy_wp_id":15619,"name":"La Baraka","city":""},{"v2_id":2182,"legacy_wp_id":15620,"name":"La baraka","city":""}]
- [{"v2_id":2639,"legacy_wp_id":16078,"name":"Select Food","city":""},{"v2_id":2652,"legacy_wp_id":16091,"name":"Select food","city":""}]
- [{"v2_id":2755,"legacy_wp_id":16194,"name":"Klass food","city":""},{"v2_id":2758,"legacy_wp_id":16197,"name":"Klass'Food","city":""}]
- [{"v2_id":2826,"legacy_wp_id":16265,"name":"Istanbul Kebab","city":""},{"v2_id":2827,"legacy_wp_id":16266,"name":"Istanbul kébab","city":""}]
- [{"v2_id":2850,"legacy_wp_id":16289,"name":"Go Food","city":""},{"v2_id":2852,"legacy_wp_id":16291,"name":"Go food","city":""}]
- [{"v2_id":2917,"legacy_wp_id":16356,"name":"L'Idéal","city":""},{"v2_id":2922,"legacy_wp_id":16361,"name":"L'ideal","city":""}]
- [{"v2_id":2959,"legacy_wp_id":16398,"name":"Pacha Kebab","city":""},{"v2_id":7076,"legacy_wp_id":20533,"name":"Pacha Kebab","city":""}]
- [{"v2_id":3094,"legacy_wp_id":16533,"name":"Crousty Time","city":""},{"v2_id":3098,"legacy_wp_id":16537,"name":"Crousty time","city":""}]
- [{"v2_id":3191,"legacy_wp_id":16630,"name":"Timgad","city":""},{"v2_id":3240,"legacy_wp_id":16679,"name":"Timgad","city":""}]
- [{"v2_id":3430,"legacy_wp_id":16869,"name":"Gerland kebab","city":""},{"v2_id":3436,"legacy_wp_id":16875,"name":"Gerland Kebab","city":""}]
- [{"v2_id":3437,"legacy_wp_id":16876,"name":"Le Régal Du Palais","city":""},{"v2_id":3502,"legacy_wp_id":16941,"name":"Le regal du palais","city":""}]
- [{"v2_id":3557,"legacy_wp_id":16997,"name":"Istanbul kebab","city":""},{"v2_id":3559,"legacy_wp_id":16999,"name":"Istanbul kebab","city":""}]
- [{"v2_id":3666,"legacy_wp_id":17106,"name":"L'Escale","city":""},{"v2_id":3693,"legacy_wp_id":17133,"name":"L'escale","city":""}]
- [{"v2_id":3778,"legacy_wp_id":17218,"name":"Snack Cappadoce","city":""},{"v2_id":3819,"legacy_wp_id":17259,"name":"Snack Cappadoce","city":""}]
- [{"v2_id":3945,"legacy_wp_id":17385,"name":"Le Chateau","city":""},{"v2_id":3949,"legacy_wp_id":17389,"name":"Le château","city":""}]
- [{"v2_id":4082,"legacy_wp_id":17522,"name":"Adonis","city":""},{"v2_id":4097,"legacy_wp_id":17537,"name":"Adonis","city":""}]
- [{"v2_id":4171,"legacy_wp_id":17611,"name":"Au P'tit Prince","city":""},{"v2_id":4178,"legacy_wp_id":17618,"name":"Au p'tit prince","city":""}]
- [{"v2_id":4495,"legacy_wp_id":17935,"name":"Sushina","city":""},{"v2_id":4498,"legacy_wp_id":17938,"name":"Sushi na","city":""}]
- [{"v2_id":4558,"legacy_wp_id":17998,"name":"La Médina","city":""},{"v2_id":4562,"legacy_wp_id":18002,"name":"La medina","city":""}]
- [{"v2_id":4612,"legacy_wp_id":18053,"name":"Snack Time","city":""},{"v2_id":4622,"legacy_wp_id":18063,"name":"Snack time","city":""}]
- [{"v2_id":4633,"legacy_wp_id":18075,"name":"Pizza time","city":""},{"v2_id":5199,"legacy_wp_id":18653,"name":"Pizza time","city":""}]
- [{"v2_id":4636,"legacy_wp_id":18080,"name":"Afrik'n'fusion","city":""},{"v2_id":5280,"legacy_wp_id":18734,"name":"Afrik'n'fusion","city":""}]
- [{"v2_id":4637,"legacy_wp_id":18082,"name":"Allo pizza","city":""},{"v2_id":5335,"legacy_wp_id":18789,"name":"Allo pizza","city":""}]
- [{"v2_id":4638,"legacy_wp_id":18084,"name":"Allo pizza express","city":""},{"v2_id":5347,"legacy_wp_id":18801,"name":"Allo pizza express","city":""}]
- [{"v2_id":4639,"legacy_wp_id":18086,"name":"Asian Food","city":""},{"v2_id":5316,"legacy_wp_id":18770,"name":"Asian food","city":""}]
- [{"v2_id":4640,"legacy_wp_id":18088,"name":"Atlas couscous","city":""},{"v2_id":5253,"legacy_wp_id":18707,"name":"Atlas couscous","city":""}]
- [{"v2_id":4641,"legacy_wp_id":18090,"name":"Au poulet braisé","city":""},{"v2_id":5334,"legacy_wp_id":18788,"name":"Au poulet braisé","city":""}]
- [{"v2_id":4642,"legacy_wp_id":18092,"name":"Bagels square","city":""},{"v2_id":5248,"legacy_wp_id":18702,"name":"Bagels square","city":""}]
- [{"v2_id":4643,"legacy_wp_id":18094,"name":"Bhai bhai","city":""},{"v2_id":5301,"legacy_wp_id":18755,"name":"Bhai bhai","city":""}]
- [{"v2_id":4698,"legacy_wp_id":18151,"name":"Fast Balard","city":""},{"v2_id":5323,"legacy_wp_id":18777,"name":"Fast balard","city":""}]

### same_address_different_names
- [{"v2_id":7677,"legacy_wp_id":22556,"name":"Mr.","city":""},{"v2_id":7678,"legacy_wp_id":22560,"name":"Mr.","city":""},{"v2_id":7679,"legacy_wp_id":22575,"name":"Mr.","city":""},{"v2_id":7680,"legacy_wp_id":22579,"name":"Mr.","city":""},{"v2_id":7681,"legacy_wp_id":22580,"name":"Mr.","city":""},{"v2_id":7682,"legacy_wp_id":22585,"name":"Mr.","city":""},{"v2_id":7683,"legacy_wp_id":22596,"name":"Mr.","city":""},{"v2_id":7684,"legacy_wp_id":22600,"name":"Mr.","city":""},{"v2_id":7685,"legacy_wp_id":22606,"name":"Mr.","city":""},{"v2_id":7686,"legacy_wp_id":22619,"name":"Mr.","city":""},{"v2_id":7687,"legacy_wp_id":22625,"name":"Mr.","city":""},{"v2_id":7688,"legacy_wp_id":22629,"name":"Mr.","city":""},{"v2_id":7689,"legacy_wp_id":22689,"name":"Mr.","city":""},{"v2_id":7690,"legacy_wp_id":22690,"name":"Mr.","city":""},{"v2_id":7691,"legacy_wp_id":22691,"name":"Mr.","city":""},{"v2_id":7692,"legacy_wp_id":22693,"name":"Mr.","city":""},{"v2_id":7693,"legacy_wp_id":22699,"name":"Mr.","city":""},{"v2_id":7694,"legacy_wp_id":22707,"name":"Mr.","city":""},{"v2_id":7695,"legacy_wp_id":22718,"name":"Mr.","city":""},{"v2_id":7696,"legacy_wp_id":22722,"name":"Mr.","city":""}]
- [{"v2_id":331,"legacy_wp_id":13762,"name":"Cappadoce Kebab","city":""},{"v2_id":333,"legacy_wp_id":13764,"name":"Chicken Chica","city":""},{"v2_id":335,"legacy_wp_id":13766,"name":"Le Roi du Kebab","city":""},{"v2_id":337,"legacy_wp_id":13768,"name":"Sea Snack And Sun","city":""}]
- [{"v2_id":1327,"legacy_wp_id":14761,"name":"Bosphore Restauration","city":""},{"v2_id":1330,"legacy_wp_id":14764,"name":"La Mer Noire","city":""},{"v2_id":1331,"legacy_wp_id":14765,"name":"Mg kebab","city":""},{"v2_id":1332,"legacy_wp_id":14766,"name":"Zozan","city":""}]
- [{"v2_id":223,"legacy_wp_id":13654,"name":"Snack le relais du chateau","city":""},{"v2_id":234,"legacy_wp_id":13665,"name":"Al Majd","city":""},{"v2_id":235,"legacy_wp_id":13666,"name":"Plaisir Sucré Salé","city":""}]
- [{"v2_id":311,"legacy_wp_id":13742,"name":"Pizza express","city":""},{"v2_id":312,"legacy_wp_id":13743,"name":"Pizza express","city":""},{"v2_id":313,"legacy_wp_id":13744,"name":"Le bun's d'antony","city":""}]
- [{"v2_id":584,"legacy_wp_id":14018,"name":"O Petit Creux","city":""},{"v2_id":592,"legacy_wp_id":14026,"name":"Las pizzas","city":""},{"v2_id":594,"legacy_wp_id":14028,"name":"O petit creux","city":""}]
- [{"v2_id":874,"legacy_wp_id":14308,"name":"Tamaris","city":""},{"v2_id":876,"legacy_wp_id":14310,"name":"Bullut","city":""},{"v2_id":878,"legacy_wp_id":14312,"name":"Tamaris","city":""}]
- [{"v2_id":1866,"legacy_wp_id":15304,"name":"Good'dwich","city":""},{"v2_id":1867,"legacy_wp_id":15305,"name":"O'sushic","city":""},{"v2_id":1868,"legacy_wp_id":15306,"name":"Pizz n go","city":""}]
- [{"v2_id":3647,"legacy_wp_id":17087,"name":"Tom Pouce","city":""},{"v2_id":3678,"legacy_wp_id":17118,"name":"Snack Amon","city":""},{"v2_id":3686,"legacy_wp_id":17126,"name":"Le Djerba","city":""}]
- [{"v2_id":4037,"legacy_wp_id":17477,"name":"Hip Hop's Foods","city":""},{"v2_id":4050,"legacy_wp_id":17490,"name":"Régal Kébab","city":""},{"v2_id":4052,"legacy_wp_id":17492,"name":"L'Indien","city":""}]
- [{"v2_id":4820,"legacy_wp_id":18273,"name":"Gui's Fast","city":""},{"v2_id":5006,"legacy_wp_id":18459,"name":"Chez Karim - Resto grec plat algérien","city":""},{"v2_id":5267,"legacy_wp_id":18721,"name":"Hana","city":""}]
- [{"v2_id":6102,"legacy_wp_id":19558,"name":"Kydam","city":""},{"v2_id":6106,"legacy_wp_id":19562,"name":"Kydam sandwicherie","city":""},{"v2_id":6108,"legacy_wp_id":19564,"name":"Kydam pizza","city":""}]
- [{"v2_id":7144,"legacy_wp_id":20601,"name":"Mis kebab","city":""},{"v2_id":7146,"legacy_wp_id":20603,"name":"Vegas pizza","city":""},{"v2_id":7147,"legacy_wp_id":20604,"name":"Pizzeria Lyon Vénissieux","city":""}]
- [{"v2_id":7343,"legacy_wp_id":20800,"name":"Ayasofia","city":""},{"v2_id":7354,"legacy_wp_id":20811,"name":"Aya Sofia","city":""},{"v2_id":7376,"legacy_wp_id":20833,"name":"Ayasofia","city":""}]
- [{"v2_id":3,"legacy_wp_id":13567,"name":"HAYAT","city":""},{"v2_id":132,"legacy_wp_id":13562,"name":"Homewok","city":""}]
- [{"v2_id":31,"legacy_wp_id":13460,"name":"Noodle ness","city":""},{"v2_id":34,"legacy_wp_id":13463,"name":"Le Dawliz","city":""}]
- [{"v2_id":63,"legacy_wp_id":13493,"name":"Au Bon Coin","city":""},{"v2_id":65,"legacy_wp_id":13495,"name":"Les Arcades","city":""}]
- [{"v2_id":123,"legacy_wp_id":13553,"name":"H-express","city":""},{"v2_id":131,"legacy_wp_id":13561,"name":"Al farooj","city":""}]
- [{"v2_id":128,"legacy_wp_id":13558,"name":"Grec Express","city":""},{"v2_id":135,"legacy_wp_id":13565,"name":"Thai bamboo","city":""}]
- [{"v2_id":161,"legacy_wp_id":13592,"name":"Le Bosphore","city":""},{"v2_id":162,"legacy_wp_id":13593,"name":"Topkapi","city":""}]
- [{"v2_id":193,"legacy_wp_id":13624,"name":"Naxos","city":""},{"v2_id":200,"legacy_wp_id":13631,"name":"Pizza Délices 62","city":""}]
- [{"v2_id":324,"legacy_wp_id":13755,"name":"Zem Zem","city":""},{"v2_id":326,"legacy_wp_id":13757,"name":"Le zem zem","city":""}]
- [{"v2_id":330,"legacy_wp_id":13761,"name":"Chez Simon","city":""},{"v2_id":332,"legacy_wp_id":13763,"name":"Big Burger 28","city":""}]
- [{"v2_id":351,"legacy_wp_id":13782,"name":"Le Régal","city":""},{"v2_id":375,"legacy_wp_id":13806,"name":"Le Méditerranée","city":""}]
- [{"v2_id":353,"legacy_wp_id":13784,"name":"Les Jumeaux","city":""},{"v2_id":391,"legacy_wp_id":13822,"name":"Les jumeaux","city":""}]
- [{"v2_id":361,"legacy_wp_id":13792,"name":"Le grand pacha","city":""},{"v2_id":395,"legacy_wp_id":13826,"name":"Le grand pacha","city":""}]
- [{"v2_id":364,"legacy_wp_id":13795,"name":"Le Spécial Koudou","city":""},{"v2_id":381,"legacy_wp_id":13812,"name":"Le spécial sandwicherie","city":""}]
- [{"v2_id":368,"legacy_wp_id":13799,"name":"Le Spécial Koudou","city":""},{"v2_id":385,"legacy_wp_id":13816,"name":"Le spécial sandwicherie","city":""}]
- [{"v2_id":372,"legacy_wp_id":13803,"name":"La Patate","city":""},{"v2_id":394,"legacy_wp_id":13825,"name":"La patate","city":""}]
- [{"v2_id":454,"legacy_wp_id":13886,"name":"Mamoun Chicken","city":""},{"v2_id":497,"legacy_wp_id":13929,"name":"Le majesté","city":""}]
- [{"v2_id":459,"legacy_wp_id":13891,"name":"O'Chicanos","city":""},{"v2_id":487,"legacy_wp_id":13919,"name":"Pekin express asian food","city":""}]
- [{"v2_id":554,"legacy_wp_id":13988,"name":"Auxonne Kebab","city":""},{"v2_id":556,"legacy_wp_id":13990,"name":"Auxonne kebab","city":""}]
- [{"v2_id":865,"legacy_wp_id":14299,"name":"Le Buffalo","city":""},{"v2_id":867,"legacy_wp_id":14301,"name":"Le buffalo (bois colombes)","city":""}]
- [{"v2_id":875,"legacy_wp_id":14309,"name":"L'escale","city":""},{"v2_id":877,"legacy_wp_id":14311,"name":"L'escale","city":""}]
- [{"v2_id":903,"legacy_wp_id":14337,"name":"Kozak Gulbade","city":""},{"v2_id":939,"legacy_wp_id":14373,"name":"Durum Seray","city":""}]
- [{"v2_id":970,"legacy_wp_id":14404,"name":"Pause de la Reine","city":""},{"v2_id":986,"legacy_wp_id":14420,"name":"La pause de la reine","city":""}]
- [{"v2_id":1010,"legacy_wp_id":14444,"name":"Kebab du Soleil","city":""},{"v2_id":1013,"legacy_wp_id":14447,"name":"Kebab du soleil","city":""}]
- [{"v2_id":1149,"legacy_wp_id":14583,"name":"Carthage Couscous","city":""},{"v2_id":1152,"legacy_wp_id":14586,"name":"Bip bip pizza","city":""}]
- [{"v2_id":1164,"legacy_wp_id":14598,"name":"Mundo'crep","city":""},{"v2_id":1165,"legacy_wp_id":14599,"name":"Le cube","city":""}]
- [{"v2_id":1328,"legacy_wp_id":14762,"name":"Restaurant Berfin","city":""},{"v2_id":1344,"legacy_wp_id":14778,"name":"FCH - THE FRENCH CHICKEN HOUSE","city":""}]
- [{"v2_id":1443,"legacy_wp_id":14878,"name":"L'Etoile","city":""},{"v2_id":1444,"legacy_wp_id":14879,"name":"Carre food","city":""}]
- [{"v2_id":1497,"legacy_wp_id":14932,"name":"Ilo kebab","city":""},{"v2_id":1498,"legacy_wp_id":14933,"name":"Show kebab","city":""}]
- [{"v2_id":1588,"legacy_wp_id":15023,"name":"Mac Kenzi","city":""},{"v2_id":1592,"legacy_wp_id":15027,"name":"Mac kenzi","city":""}]
- [{"v2_id":1614,"legacy_wp_id":15050,"name":"Delice pizza","city":""},{"v2_id":1616,"legacy_wp_id":15052,"name":"Reale pizza","city":""}]
- [{"v2_id":1672,"legacy_wp_id":15108,"name":"Restaurant Rosa","city":""},{"v2_id":1680,"legacy_wp_id":15116,"name":"Istanbul kebap","city":""}]
- [{"v2_id":1682,"legacy_wp_id":15118,"name":"Miam's","city":""},{"v2_id":1683,"legacy_wp_id":15119,"name":"Fleche pizza","city":""}]
- [{"v2_id":1695,"legacy_wp_id":15131,"name":"Restaurant Gulistan","city":""},{"v2_id":1697,"legacy_wp_id":15133,"name":"La route de la soie","city":""}]
- [{"v2_id":1749,"legacy_wp_id":15185,"name":"Les Renouillers","city":""},{"v2_id":1774,"legacy_wp_id":15210,"name":"Le c'one","city":""}]
- [{"v2_id":1755,"legacy_wp_id":15191,"name":"Le Spécial Koudou","city":""},{"v2_id":1778,"legacy_wp_id":15214,"name":"Le spécial sandwicherie","city":""}]
- [{"v2_id":1757,"legacy_wp_id":15193,"name":"Half Time","city":""},{"v2_id":1771,"legacy_wp_id":15207,"name":"Half time","city":""}]

## Index actuels et recommandations

- Actuels : index sur `status`, `postal_code`, `city_name`, unicité `legacy_wp_id`; aucune indexation GPS dédiée. Les pivots ont une clé primaire `(restaurant_id, location_id)`.
- Phase 2 : index composite de recherche/qualité sur `(status, city_name, postal_code)`, index batch sur `geocoding_status` une fois créé, et index spatial/stratégie de proximité validée contre le plan MariaDB. Ne pas créer avant la conception de la phase 2.

## Modèle cible proposé (non implémenté)

- Réutiliser `address` comme `address_raw` pendant une transition contrôlée; conserver `postal_code`, `city_name`, `latitude`, `longitude`.
- Ajouter : `address_line1`, `address_line2`, `country_code`, `geocoding_provider`, `geocoding_source_id`, `geocoding_precision`, `geocoding_status`, `geocoded_at`, `manually_verified_at` et une trace de décision/audit.
- Les régions, départements et codes INSEE devraient provenir d’une source de référence versionnée et ne doivent pas être déduits aveuglément du texte.

## Triage recommandé pour la phase 2

- KEEP AS IS : 4159 à échantillonner par reverse-geocoding, sans écriture automatique.
- AUTO-GEOCODE HIGH CONFIDENCE : 8 adresses complètes, seulement après pilote Géoplateforme et seuil strict.
- AUTO-REVERSE-GEOCODE : 0 GPS seuls + les 3437 partiels avec GPS, pour enrichissement proposé et non appliqué.
- MANUAL REVIEW : 67 conflits + 5 textes anormaux + clusters GPS massifs.
- UNUSABLE : 3 sans adresse, ville, CP ni GPS.

## Échantillon déterministe

Le CSV associé contient 100 enregistrement(s), plafonné à 100; sélection par classification, anomalies puis `legacy_wp_id` croissant.
