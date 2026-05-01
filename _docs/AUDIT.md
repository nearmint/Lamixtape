# AUDIT.md — Lamixtape.fr (thème WordPress)

> Audit en lecture seule réalisé le 2026-04-28. **Aucun fichier de code n'a été modifié.** Findings classés par axe puis par sévérité décroissante. Voir `CLAUDE.md` pour le contexte stratégique.

**Échelle de sévérité**
- **Critique** : faille exploitable, bug bloquant, perte de données potentielle, prérequis avant tout refacto.
- **Haute** : impact utilisateur ou maintenabilité fort, à traiter en priorité.
- **Moyenne** : à corriger mais non bloquant.
- **Basse** : nice-to-have, polish.

---

## <a id="securite"></a>1. Sécurité

### [SEC-001] Endpoint REST `social/v2/likes` ouvert sans `permission_callback` ni rate-limit
- **Sévérité** : Critique
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php:276-285,287-298`
- **Description** : `register_rest_route('social/v2', '/likes/(?P<id>\d+)', ...)` est déclaré sans `permission_callback` (équivaut à `__return_true` selon les versions de WP, mais émet un warning et expose la route à tout anonyme). Aucun `wp_verify_nonce`, aucun rate-limit, aucune capability check. Le callback `social__like` incrémente naïvement `likes_number` à chaque requête POST/GET.
- **Impact** : N'importe qui peut spammer le compteur de likes d'un post (curl en boucle), faussant la métrique éditoriale "🔥". Surface d'attaque : DoS léger, pollution des données.
- **Recommandation** : Ajouter `'permission_callback' => '__return_true'` (explicite) **et** un nonce REST (`wp_create_nonce('wp_rest')` côté JS, `WP_REST_Server::READABLE` + check). Restreindre aux méthodes POST seules. Ajouter un transient par IP (`set_transient('lmt_like_'.$ip.'_'.$id, 1, HOUR_IN_SECONDS)`) pour bloquer les spams. Idéalement, exiger un cookie session ou un cookie WP.
- **Statut** : Résolu Phase 0 (`f8107e0` `permission_callback` `lmt_social_like_permission` + nonce REST `X-WP-Nonce` + rate-limit transient avec hash IP `wp_hash` (RGPD) + `WP_REST_Server::CREATABLE` (POST only) + `validate_callback` sur `id`).

### [SEC-002] Callback `social__dislike` référencé mais non défini → 500 sur l'endpoint
- **Sévérité** : Critique
- **Axe** : Sécurité (et fiabilité)
- **Fichier(s)** : `functions.php:281-285,300`
- **Description** : La route `social/v2/dislikes/{id}` est enregistrée avec `'callback' => 'social__dislike'`, mais aucune fonction `social__dislike` n'existe dans le thème (un commentaire `// (Add social__dislike and any other missing functions as needed)` confirme l'oubli). `js/main.js:80-94` envoie pourtant des POST vers cette route.
- **Impact** : Tout clic sur le bouton dislike → erreur 500 / rest_invalid_handler / fatal côté serveur, log pollué, UX cassée.
- **Recommandation** : Soit implémenter `social__dislike` (symétrique de `social__like`), soit retirer la route et le bouton dislike. Vu la conversation business, choisir avant de coder (probablement supprimer : feature jamais finie).
- **Statut** : Résolu Phase 0 (`2d656f8` route REST `/dislikes/{id}` supprimée + handler JS `.dislike__btn` supprimé + commentaire orphelin `social__dislike` supprimé). Champ ACF `dislikes_number` préservé en BDD (décision business).

### [SEC-003] Requête SQL custom avec interpolation directe (`guests.php`)
- **Sévérité** : Haute
- **Axe** : Sécurité
- **Fichier(s)** : `guests.php:14-18`
- **Description** : `$query = "SELECT ID, user_nicename from $wpdb->users WHERE ID != '$site_admin' ORDER BY user_nicename"` — `$site_admin` est aujourd'hui une chaîne vide, donc inoffensif, mais le pattern utilise `$wpdb->get_results($query)` sans `$wpdb->prepare()`. Le jour où `$site_admin` est rendu dynamique (via une option, un GET, un champ admin), c'est une SQLi directe.
- **Impact** : SQLi potentielle si la variable devient dynamique. Mauvais pattern qui se propagera.
- **Recommandation** : Remplacer par `get_users(['exclude' => [1], 'orderby' => 'nicename', 'fields' => ['ID', 'user_nicename']])` (API WP). Si un `$wpdb->get_results` est vraiment nécessaire, passer par `$wpdb->prepare("... WHERE ID != %d", $admin_id)`.
- **Statut** : Résolu Phase 2 (`37fd794` interpolation `$wpdb->get_results($query)` brute remplacée par `get_users(['exclude' => [1], 'orderby' => 'nicename', 'fields' => ['ID', 'user_nicename']])` dans `lmt_get_curators()` (`inc/queries.php`). Le filtre legacy `WHERE ID != '$site_admin'` avec `$site_admin = ""` était un no-op (chaîne vide → comparaison à 0 → aucun utilisateur exclu) ; CSS masquait `.author-1` pour compenser. Phase 2 exprime l'intention au niveau data — visuel inchangé puisque CSS le masquait déjà).

### [SEC-004] `cf_search_where` modifie la clause SQL via `preg_replace` sans audit du résultat
- **Sévérité** : Moyenne
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php:23-32`
- **Description** : Le filtre `posts_where` réécrit la clause SQL via une regex pour ajouter une condition `OR (postmeta.meta_value LIKE ...)`. Le `$1` capturé est directement réinjecté dans la chaîne. WP nettoie la requête en amont, mais ce pattern (réécriture SQL par regex) est fragile : le moindre changement de moteur SQL côté WP casse le filtre, et toute future modification du filtre peut introduire une injection.
- **Impact** : Code fragile + surface d'attaque latente (médiocre, pas exploitable en l'état).
- **Recommandation** : Remplacer par une **meta_query** native sur le `pre_get_posts` ou par un index plein-texte sur `postmeta.meta_value`. Voir le pattern documenté par WP Engine / Yoast pour étendre `s=` aux postmeta.
- **Statut** : Reporté avec PERF-006 (Q10 search rewrite, post-Phase-6). SEC-004 et PERF-006 ciblent le même mécanisme (`lmt_search_postmeta_where` ex-`cf_search_where`) sous deux angles différents (sécurité du `preg_replace` / coût du `LEFT JOIN postmeta`). La refonte de la stratégie de recherche les fermera ensemble — cf. CLAUDE.md section 7 Q10 pour les options (FT MySQL, Relevanssi, etc.).

### [SEC-005] Aucune vérification de capability sur les actions REST
- **Sévérité** : Moyenne
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php:276-298`
- **Description** : Les endpoints REST `likes/dislikes` ne contrôlent aucune capability. C'est un choix volontaire (anonymes), mais aucun garde-fou (nonce, transient, captcha) ne vient compenser.
- **Impact** : Cf. SEC-001 — pollution facile des métriques.
- **Recommandation** : Au minimum, exiger un nonce REST `X-WP-Nonce` envoyé par le JS, et limiter les requêtes à 1 par IP par heure par post via transient.
- **Statut** : Résolu Phase 0 + Phase 3 (Phase 0 `f8107e0` `lmt_social_like_permission` avec nonce REST `X-WP-Nonce` + rate-limit transient 1/h/IP/post sur `social/v2/likes` ; Phase 3 `8bd0588` même pattern appliqué au nouveau endpoint `lamixtape/v1/posts` via `lmt_rest_pagination_permission` — nonce + rate-limit transient 100/h/IP. Les deux endpoints REST custom du thème sont maintenant nonce-gardés et rate-limited).

### [SEC-006] `wp_localize_script` attaché au handle `'jquery'`
- **Sévérité** : Moyenne
- **Axe** : Sécurité (et qualité)
- **Fichier(s)** : `functions.php:267`
- **Description** : `wp_localize_script('jquery', 'bloginfo', [...])` — la variable globale `bloginfo` (qui contient `template_url`, `site_url`, `post_id`) est rattachée à un handle qui n'appartient pas au thème. Si jQuery n'est pas effectivement enqueued sur une page (ex. admin), la variable est absente. Pire, n'importe quel autre plugin qui dépend de `jquery` reçoit cette variable injectée dans son scope JS.
- **Impact** : Fuite involontaire d'info, bugs intermittents (variable `undefined`).
- **Recommandation** : Attacher au handle propre du thème (`wp_localize_script('lmt-main', 'lmtData', [...])` après avoir enregistré le script `main.js` sous le handle `lmt-main`).
- **Statut** : Résolu Phase 1 — 3 commits atomiques (`83d72cb` rename handle `'ajax-script'` → `'lmt-main'` + `72647ba` rename `bloginfo` → `lmtData` attaché à `'lmt-main'` + déplacement hook `'init'` → `'wp_enqueue_scripts'` + `62457a2` fusion de `loadmore_enqueue` + `push_script` dans `lmt_enqueue_assets()`).

### [SEC-007] Scripts/CSS externes via CDN sans Subresource Integrity (SRI)
- **Sévérité** : Basse
- **Axe** : Sécurité
- **Fichier(s)** : `header.php:6,8,9` ; `footer.php:47` ; `style.css:11-13` ; `analytics.php:2` ; `player.php` (YouTube IFrame API chargée dynamiquement)
- **Description** : 6 sources externes (Bootstrap CDN, jQuery CDN, MediaElement CDN, Google Fonts, Umami, YouTube) sont chargées sans attribut `integrity=` ni `crossorigin=`. Si l'un de ces CDN est compromis, du code malveillant s'exécute en frontal Lamixtape.
- **Impact** : Risque supply-chain. Faible probabilité, impact élevé.
- **Recommandation** : Soit auto-héberger Bootstrap/jQuery/MediaElement (`composer require` ou `npm install`, copie dans `assets/vendor/`), soit ajouter SRI (`integrity="sha384-..."`). L'auto-hébergement est aussi une optimisation perf (un seul domaine, HTTP/2 multiplexing).
- **Statut** : Résolu Phase 1 (`57404c9` Bootstrap 4.4.1 + jQuery 3.6.0 + Outfit variable woff2 auto-hébergés dans `assets/vendor/` + `ed7bb07` MediaElement basculé vers `wp-mediaelement` WP-bundled — équivalent fonctionnel : la dépendance n'est plus un CDN externe mais une lib WP). SRI non nécessaire pour les fichiers locaux (intégrité git). Umami reste inline en CDN (snippet officiel SaaS, decision Phase 1).

### [SEC-008] Aucun header de sécurité émis côté thème
- **Sévérité** : Basse
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php` (absence)
- **Description** : Le thème ne pose pas `X-Frame-Options`, `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`. Le filtre `login_errors` masque les erreurs de login (functions.php:151-154) — c'est bien — mais les headers HTTP de sécurité de base sont absents.
- **Impact** : Site cliquable en iframe (clickjacking), pas de CSP pour limiter les CDN tiers.
- **Recommandation** : Ajouter via `send_headers` (ou côté serveur Nginx/Apache, plus propre) : `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=()`. CSP plus complexe, à définir après l'inventaire des CDN.
- **Statut** : Résolu Phase 3 (`2d10728` `lmt_send_security_headers` hooké sur `send_headers` pose les 5 headers baseline — `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security: max-age=31536000; includeSubDomains`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()` — et `header_remove('X-Powered-By')` pour ne plus exposer la version PHP. Vérifié pré-déploiement par `curl -I https://lamixtape.fr` que ni OVH ni Cloudflare ne posaient déjà ces headers, donc 0 risque de doublon. Content-Security-Policy intentionnellement reporté en Q11/CLAUDE.md vu la matrice non-triviale (Bootstrap inline, YouTube iframe, MediaElement, Cloudflare Turnstile, Umami, ACF dynamic style="...") — à finaliser Phase 5/6 après Tailwind v4).

### [SEC-009] iframes YouTube créées sans `referrerpolicy` ni `sandbox`
- **Sévérité** : Basse
- **Axe** : Sécurité
- **Fichier(s)** : `player.php:121-133` (instanciation `YT.Player`)
- **Description** : L'iframe YouTube est créée par l'API YT.Player qui ne pose pas par défaut de `referrerpolicy` ni de `sandbox`. Le referrer Lamixtape fuit vers Google.
- **Impact** : Faible (analytics tiers), mais améliorable.
- **Recommandation** : Après `youtubePlayer = new YT.Player(...)`, manipuler l'iframe via `youtubePlayer.getIframe().setAttribute('referrerpolicy', 'no-referrer-when-downgrade')`. Pour `sandbox`, attention : rompt l'API JS YouTube.
- **Statut** : Résolu Phase 3 (`780c463` dans `js/player.js`, `onPlayerReady` ajoute `referrerpolicy="strict-origin-when-cross-origin"` sur l'iframe YouTube via `youtubePlayer.getIframe().setAttribute(...)`. Effet : YouTube reçoit uniquement l'origine `https://lamixtape.fr` au lieu de l'URL complète de la mixtape. `sandbox` intentionnellement non appliqué — il rompt le canal `postMessage` que l'API YT JS utilise pour play/pause/seek. Idempotent (hasAttribute guard) + try/catch défensif pour ne jamais bloquer la lecture sur ce hardening).

---

## <a id="performance"></a>2. Performance

### [PERF-001] `index.php` charge **toutes** les playlists publiées d'un coup
- **Sévérité** : Critique
- **Axe** : Performance
- **Fichier(s)** : `index.php:38-41`
- **Description** : `new WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>-1])` rend l'intégralité du catalogue (≥ 360 mixtapes d'après le copy de la home) en HTML, dans une `<section>` unique, sans pagination, sans virtualisation, sans lazy.
- **Impact** : LCP catastrophique sur la home, payload HTML > 200 Ko, parsing CSS/DOM long sur mobile, INP dégradé. Croît linéairement avec la BDD.
- **Recommandation** : Paginer (50/page) avec `paginate_links`, OU implémenter un défilement infini AJAX (REST API custom + intersection observer), OU charger 30 articles puis "Load more" (pattern déjà suggéré par le nom `loadmore_enqueue` — fonctionnalité jamais finie).
- **Statut** : Résolu Phase 3 (Axe A — `c34a332` `index.php` rend les 30 premières mixtapes server-side + sentinel `#lmt-infinite-sentinel` + endpoint REST `lamixtape/v1/posts` `8bd0588` + JS IntersectionObserver `b7f9f18`. Payload HTML initial passe de 370+ cards à 30 cards ; les suivantes se chargent par lots de 30 sur scroll. LCP au load préservé. Aucune card visible n'est perdue : l'utilisateur voit toujours toutes les mixtapes en scrollant).

### [PERF-002] `single.php` exécute une `WP_Query` sur 1 000 000 posts filtrés par date
- **Sévérité** : Critique
- **Axe** : Performance
- **Fichier(s)** : `single.php:99-111`
- **Description** : Le bloc "anciennes mixtapes" sous l'article courant utilise `posts_per_page=1000000` + un filtre `posts_where` qui injecte une comparaison `post_date < '...'`. Effets : (1) `OFFSET 0 LIMIT 1000000` dans la requête, (2) load complet en mémoire de tous les posts antérieurs à chaque vue de mixtape.
- **Impact** : TTFB qui croît avec la BDD, RAM PHP saturée sur les mixtapes anciennes (peu d'antérieurs) vs. récentes (300+ antérieurs). À terme : timeout PHP ou erreur 500.
- **Recommandation** : Limiter à 20-30 entrées avec `posts_per_page => 30` et `paginate_links`. Mieux : remplacer par `get_adjacent_post()` côté navigation, et déplacer la liste complète sur la home (avec pagination). Supprimer entièrement le filtre `posts_where` global (cf. PERF-009).
- **Statut** : Résolu Phase 3 (Axe A — `071e4e1` `lmt_get_previous_mixtapes()` accepte désormais `$limit`/`$offset` ; `single.php` rend les 30 premières en server-side, les suivantes via AJAX vers le même endpoint REST `lamixtape/v1/posts` (context `single_previous` + `data-exclude=<post_id>`). Le commentaire `// PERF-002 tracked, pagination strategy in Phase 3` posé en Phase 2 a été retiré).

### [PERF-003] Bootstrap CSS chargé via `@import` dans `style.css`
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `style.css:11-13`
- **Description** : `@import url(https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css);` dans `style.css`. Les `@import` CSS sont **séquentiels** : le navigateur ne télécharge `bootstrap.min.css` qu'après avoir parsé `style.css`. Idem pour MediaElement CSS et la font Outfit. Multiplie le critical path.
- **Impact** : First Contentful Paint dégradé, render-blocking en cascade.
- **Recommandation** : Enqueuer chaque dépendance CSS via `wp_enqueue_style()` (handles distincts, dépendances explicites). Préférer auto-hébergement de Bootstrap (et MediaElement) dans `assets/vendor/`.
- **Statut** : Résolu Phase 1 (`97a7e96` `<link>` en dur supprimé de `header.php` + `style.css` réduit au header de thème WP + chaîne `@import` Bootstrap/MediaElement/Outfit remplacée par `wp_enqueue_style` dans `lmt_enqueue_assets()` (Bootstrap depuis `assets/vendor/`, ordre de cascade préservé via dépendances explicites). Cf. aussi PERF-010 résolu par le même commit, PERF-004 (jQuery CDN), SEC-007 (auto-hébergement vendor), WP-001 (style.css enqueué proprement)).

### [PERF-004] jQuery chargée deux fois (CDN dans `<head>` + dépendance enqueue)
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `header.php:8` + `functions.php:261`
- **Description** : `<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>` est en dur dans `header.php`, et `wp_enqueue_script('ajax-script', ..., array('jquery'), ...)` déclare `jquery` comme dépendance — WP enqueue alors **sa propre** jQuery bundlée. Résultat : deux versions de jQuery chargées, conflits potentiels (`$.fn.mediaelementplayer` peut viser une version, le code utilisateur l'autre).
- **Impact** : ~90 Ko inutiles, conflits de plugins jQuery, comportement non-déterministe.
- **Recommandation** : Supprimer la balise CDN dans `header.php`. Si une version spécifique est requise, `wp_deregister_script('jquery')` puis `wp_register_script('jquery', '...', [], '3.6.0', true)` dans un hook `wp_enqueue_scripts` à priorité basse. Mieux : viser le retrait progressif de jQuery.
- **Statut** : Résolu Phase 1 (`863ee0f` `<script src="https://code.jquery.com/jquery-3.6.0.min.js">` supprimé de `header.php`. Le thème consomme uniquement la jQuery bundlée par WP via la chaîne de dépendance des handles `lmt-main`, `lmt-bootstrap-bundle`, `lmt-player`, `wp-mediaelement` (tous déclarent `'jquery'` comme dépendance dans `lmt_enqueue_assets()`). 1 seule version chargée, ~90 Ko économisés).

### [PERF-005] Multiples `WP_Query` aléatoires (`orderby => rand`) par page
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `header.php:62` ; `index.php:21` ; `single.php:83` ; `404.php:9`
- **Description** : 4 endroits exécutent `new WP_Query(['orderby' => 'rand', 'posts_per_page' => 1])`. `ORDER BY RAND()` MySQL est **O(n)** sur la table `wp_posts` complète : sur 360+ posts c'est encore tolérable, sur 1 000+ c'est lourd. Et la home en exécute **2** (header + index), single en exécute 2 aussi (header + bouton random).
- **Impact** : Charge BDD inutile, TTFB augmenté.
- **Recommandation** : Cacher l'ID aléatoire en transient courte durée (`set_transient('lmt_random_post', $id, 5 * MINUTE_IN_SECONDS)`) ou pré-calculer un pool de 50 IDs aléatoires en cache. Variante : sélectionner `MAX(ID)`, tirer un random PHP, refaire un `get_post()`.
- **Statut** : Résolu Phase 3 (`ad294a6` helper `lmt_get_random_mixtape($cache_key, $ttl = HOUR_IN_SECONDS)` créé dans `inc/queries.php`. Les 4 sites d'appel (`header.php` mobile menu, `index.php` about inline, `single.php` random button, `404.php` fallback) consomment le helper avec un `cache_key` unique chacun (`header_mobile_menu`, `home_about_inline`, `single_random_button`, `404_fallback`) — 4 transients indépendants, TTL 1h par défaut conformément à D8. Le seul `orderby=rand` restant dans le codebase est dans le helper lui-même).

### [PERF-006] `cf_search_join` LEFT JOIN systématique sur `postmeta`
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `functions.php:13-19`
- **Description** : Toute recherche frontend déclenche un `LEFT JOIN postmeta`. La table `postmeta` est dénormalisée et **non-indexée** sur `meta_value` (par défaut WP). Sur 360 posts × N champs ACF, c'est OK ; à 10k+ ça devient catastrophique.
- **Impact** : Recherches lentes (>1s) à mesure que la BDD grossit.
- **Recommandation** : Limiter aux meta-keys utiles (filter `WHERE meta_key IN ('tracklist_%_track', ...)`), ou indexer `wp_postmeta(meta_value(191))`, ou migrer vers `WP_Query` standard avec un index plein-texte (`MATCH ... AGAINST`).
- **Statut** : Reporté Q10 search rewrite, post-Phase-6 (cf. CLAUDE.md section 7 Q10). Décision business + technique : refonte de la stratégie de recherche est trop volumineuse pour le scope refacto thème. Options à arbitrer : (1) index FT MySQL + meta_key whitelist, (2) plugin Relevanssi ou SearchWP, (3) Algolia / Meilisearch (probablement overkill < 1k posts), (4) status quo accepté tant que la BDD reste < 1k posts. Aujourd'hui tolérable post-fix QC-005 Phase 1 (la recherche fonctionne de nouveau, juste pas optimale en performance). À ré-évaluer si la BDD croît significativement.

### [PERF-007] `category.php` sans pagination (`posts_per_page => -1`)
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `category.php:21-29`
- **Description** : `posts_per_page => -1` charge tous les posts de la catégorie. La pagination est même *codée puis commentée* (`category.php:64-67`). Une catégorie populaire (ex. "House", "Hip-hop") peut contenir > 100 posts.
- **Impact** : LCP dégradé sur les pages catégories populaires.
- **Recommandation** : Décommenter la pagination, fixer `posts_per_page => 30`, ou laisser `-1` mais avec un cache complet de la page (page cache).
- **Statut** : Résolu Phase 3 (Axe A — `e8c8b86` `category.php` rend les 30 premières mixtapes server-side via `posts_per_page = LMT_INFINITE_SCROLL_BATCH_SIZE` + sentinel `data-context="category" data-category="<cat_id>"`. Cards 31+ chargées via le même endpoint REST `lamixtape/v1/posts` consommé par PERF-001/002. La pagination commentée legacy + le `$paged = max(1, get_query_var('paged'))` mort ont été retirés).

### [PERF-008] `guests.php` exécute une `WP_Query` par utilisateur (N+1)
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `guests.php:30-34`
- **Description** : Pour chaque auteur listé, une `WP_Query('author=ID&posts_per_page=-1')` est exécutée pour afficher ses titres. Avec 50 auteurs × 7 mixtapes en moyenne, c'est 50 requêtes BDD séquentielles juste pour cette page.
- **Impact** : TTFB > 1s sur la page Guests, scaling linéaire avec le nombre d'auteurs.
- **Recommandation** : Une seule `get_posts(['posts_per_page' => -1, 'post_status' => 'publish'])` puis grouper en PHP par `post_author`. Ou utiliser un cache full-page sur cette URL (rarement modifiée).
- **Statut** : Résolu Phase 2 (`37fd794` 1 query `get_posts(posts_per_page=-1, post_status=publish)` + bucketing PHP par `post_author` dans `lmt_get_posts_grouped_by_author()` ; `guests.php` consomme le résultat sans WP_Query par auteur. ~50 roundtrips SQL collapsés en 1 quel que soit le nombre de curators).

### [PERF-009] `filter_where` redéclarée à chaque rendu de `single.php`
- **Sévérité** : Moyenne
- **Axe** : Performance / Fiabilité
- **Fichier(s)** : `single.php:102-108`
- **Description** : `function filter_where($where = '') { ... }` est définie au scope global **dans le template**. Si une autre partie de l'app inclut `single.php` deux fois (peu probable mais possible via shortcodes/REST), PHP émet `Cannot redeclare filter_where()` → fatal. De plus, le filtre `posts_where` est ajouté juste avant la query et retiré juste après — si une exception se lève entre les deux, le filtre reste actif et pollue toutes les queries suivantes.
- **Impact** : Risque de fatal error, risque de pollution des queries.
- **Recommandation** : Déclarer la fonction dans `functions.php` avec un nom préfixé (`lmt_filter_where_before_date`), et utiliser une closure si possible. Idéalement, **supprimer ce mécanisme** au profit d'un `date_query` natif WP.
- **Statut** : Résolu Phase 2 (`df0590d` `function filter_where(...)` global au scope de `single.php` remplacée par closure scoped dans `lmt_get_previous_mixtapes()` (`inc/queries.php`). Le `posts_where` filter est ajouté/retiré strictement à l'intérieur de la helper, et la `$publish_date` est capturée par valeur via `use` — plus de risque de redéclaration ni de pollution. Date interpolation aussi durcie via `$wpdb->prepare(' AND post_date < %s', $publish_date)`).

### [PERF-010] `style.css` ne sert qu'à `@import` 15 fichiers CSS séparés
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `style.css:11-29`
- **Description** : 15 `@import url(...)` locaux + 3 externes = **18 requêtes CSS séquentielles**. HTTP/2 multiplexe, mais l'`@import` CSS-dans-CSS est toujours sérialisé.
- **Impact** : Critical path CSS allongé.
- **Recommandation** : Concaténer en un seul `style.css` (ou en bundles thématiques chargés par template via `wp_enqueue_style`). Avec Tailwind v4, la migration produira un seul fichier CSS final.
- **Statut** : Résolu Phase 1 (`97a7e96` `style.css` réduit au header de thème WP — toutes les `@import` éliminées. Les 14 fichiers CSS thème (15 moins newsletter supprimé en Phase 1.3) sont maintenant enqueued individuellement via `wp_enqueue_style` dans `lmt_enqueue_assets()`, en parallèle HTTP/2 plutôt qu'en cascade `@import` sérielle. La concaténation en un bundle unique reste un objectif Phase 4 Tailwind où le pipeline produira un CSS final).

### [PERF-011] Pas de `loading="lazy"`, pas de `srcset`/`sizes` sur les images
- **Sévérité** : Basse
- **Axe** : Performance
- **Fichier(s)** : `single.php:63` ; `header.php:30` (logo logique) ; `index.php:32` ; `404.php:23`
- **Description** : `the_post_thumbnail_url()` est utilisée seule (sans `the_post_thumbnail($size, $attrs)`), donc pas de `srcset` automatique WP. Aucune balise n'a `loading="lazy"` explicite (WP en pose certaines depuis 5.5 mais pas systématiquement quand on construit le `<img>` à la main).
- **Impact** : Téléchargement d'images haute résolution sur mobile, bytes inutiles, LCP dégradé.
- **Recommandation** : Remplacer `the_post_thumbnail_url()` par `the_post_thumbnail('large', ['class' => 'img-fluid mt-4 illustration', 'loading' => 'lazy', 'alt' => ...])`. Forcer `loading="lazy"` sur toutes les `<img>` non-LCP.
- **Statut** : Résolu Phase 3 (`e6081ad` partie 1/2 : `loading="lazy" decoding="async"` ajoutés sur les 3 `<img>` statiques de `404.php`, `index.php` et `player.php`. `3a29def` partie 2/2 : `<img src="<?php the_post_thumbnail_url(); ?>">` de `single.php` remplacé par `the_post_thumbnail('large', ['class' => ..., 'alt' => esc_attr(get_the_title()), 'loading' => 'lazy', 'decoding' => 'async'])` — WP génère désormais `srcset` + `sizes` automatiquement, et le mobile pull une variante 'large' (~1024px) au lieu du fichier original. `alt` est aussi proprement échappé via `esc_attr` au passage. Header logo = SVG inline, pas d'`<img>` à protéger).

### [PERF-012] Polices Google sans `preconnect` ni `font-display=swap` propre
- **Sévérité** : Basse
- **Axe** : Performance
- **Fichier(s)** : `style.css:13`
- **Description** : `@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap')` — l'URL contient bien `display=swap`, mais l'`@import` empêche les pre-resolve DNS. Pas de `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>` non plus.
- **Impact** : FOIT/FOUT mal géré, ~100 ms perdus sur la connexion DNS+TLS.
- **Recommandation** : Auto-héberger la police (variable font Outfit, ~50 Ko woff2), ou ajouter `preconnect` dans `header.php`.
- **Statut** : Résolu Phase 1 + Phase 3 (`57404c9` Phase 1 : Outfit variable woff2 auto-hébergé dans `assets/vendor/outfit/`, chaîne `@import` Google Fonts éliminée → besoin de `preconnect` vers `fonts.googleapis.com`/`fonts.gstatic.com` disparu. `614a886` Phase 3 : `<link rel="preload" as="font" type="font/woff2" href="…/outfit-latin.woff2" crossorigin="anonymous">` posé en `<head>` priorité 1 via `lmt_preload_outfit_font()` → optimisation LCP, le browser commence à fetcher la police avant même de parser le CSS qui la déclare. Subset `latin-ext` non préchargé (rare, fetché à la demande)).

### [PERF-013] `console.log` de diagnostic en production (`player.php`)
- **Sévérité** : Basse
- **Axe** : Performance / Qualité
- **Fichier(s)** : `player.php:36-38,120,149,158,189,234,254,257,260,290,330,338,341,344,347,350,357,360`
- **Description** : ~18 `console.log` actifs en production dans le JS du player (préfixés `[Player]`, `[YouTube API]`, `[UI]`).
- **Impact** : Pollution console (mauvaise UX dev externe, +1 KB minifié, micro-coût d'évaluation).
- **Recommandation** : Supprimer tous les `console.log` ou les wrapper dans `if (window.LMT_DEBUG)`. À faire pendant le refacto JS.
- **Statut** : Résolu Phase 1 (`2462471` 21 `console.log` + 2 `console.warn` supprimés de `player.php` — tous purement diagnostiques `[Player]` / `[YouTube API]` / `[UI]`).

### [PERF-014] `WP_POST_REVISIONS` défini dans `functions.php` (trop tard)
- **Sévérité** : Basse
- **Axe** : Performance
- **Fichier(s)** : `functions.php:137`
- **Description** : `define('WP_POST_REVISIONS', 3)` arrive lors du chargement du thème, donc **après** que WP a déjà décidé du nombre de revisions à conserver pour les requêtes en cours (sauvegarde de post). Le bon emplacement est `wp-config.php`.
- **Impact** : La limitation à 3 revisions n'est pas garantie.
- **Recommandation** : Déplacer dans `wp-config.php` (`define('WP_POST_REVISIONS', 3);` avant `require_once ABSPATH . 'wp-settings.php';`).
- **Statut** : Reporté infrastructure (Phase 6 prep). Le fix complet impose de modifier `wp-config.php` qui est hors du périmètre du thème (CLAUDE.md section 8 — règle "wp-config.php hors thème, ne jamais modifier sans confirmation"). Mitigation déjà en place Phase 1 (`389dd3c` wrap `if ( ! defined('WP_POST_REVISIONS') )` dans `functions.php`, cf. QC-NEW-001) qui élimine le warning et permet à `wp-config.php` de définir la constante en amont si la valeur y est posée. À reprendre lors d'une session admin/infrastructure dédiée (déploiement, ops). Doublon documentaire : voir aussi WP-006 sous l'axe WP best practices.

---

## <a id="a11y"></a>3. Accessibilité (WCAG 2.1 AA)

### [A11Y-001] Focus visible désactivé globalement
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `css/general.css:17-21,23-28`
- **Description** : `.btn:focus, button:focus, input:focus, select:focus, textarea:focus { outline: 0!important; box-shadow: none!important; }` — supprime tout indicateur de focus clavier sans aucun fallback visuel.
- **Impact** : **Violation WCAG 2.4.7 (niveau AA)**. Le site est inutilisable au clavier (impossible de savoir où on est).
- **Recommandation** : Supprimer ces règles. Définir un focus style cohérent : `:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }`. À traiter en priorité.
- **Statut** : Résolu Phase 5. Suppression des rules legacy `outline: 0 !important` blanket-disabled (`.btn / button / input / select / textarea`) + `a:focus { outline: 0 }` dans `css/general.css`. Remplacé par `:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }` (universel, déclenche sur navigation clavier mais pas sur clic souris) + override `#seekbar:focus-visible, input[type="range"]:focus-visible` avec `outline-offset: 0` (l'offset par défaut clipperait hors du track de la slider). Suppression de `outline-none` Tailwind sur `#burger-btn` (header.php) qui aurait contourné le focus-visible. Couleur `#fff` cohérente avec le thème (texte blanc sur fond #333), visible sur tous les backgrounds incluant le gradient mobile menu et les cards ACF-colorées. WCAG 2.4.7 niveau AA. **Petit impact visuel accepté** — focus rings visibles sur navigation clavier (par design, validé par utilisateur dans la procédure révisée Phase 5 GROUPE 5).

### [A11Y-002] Liens factices `href="#" data-toggle="modal"` non focusables proprement
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php:71-72,73-74` ; `single.php:63,91,92` ; `index.php:16,29,32` ; `footer.php:18`
- **Description** : Multiples `<a href="#" data-toggle="modal" data-target="#donatemodal">` — si le JS Bootstrap échoue (CDN down, JS désactivé), le lien n'a aucun effet. Pas de `role="button"`, pas de `aria-haspopup="dialog"`, pas de `aria-controls`. Au clavier, Enter ne déclenche pas l'action sur certains navigateurs (selon le focus state).
- **Impact** : Modals (donation, contact, image) non activables au clavier. Violation WCAG 2.1.1 (niveau A).
- **Recommandation** : Remplacer `<a href="#">` par `<button type="button">` avec un handler JS explicite. Ajouter `aria-haspopup="dialog"` et `aria-controls="donatemodal"`. Cohérent avec la migration vers `<dialog>` HTML natif (cf. TW-002).
- **Statut** : Résolu en 2 phases. **Phase 4** (`871b11d` Axe C C16) : migration `data-toggle="modal" data-target="..."` → `data-lmt-dialog="..."` + handler vanilla `js/dialogs.js` qui `preventDefault()` correctement. **Phase 5** : migration sémantique `<a href="#">` → `<button type="button">` sur les 9 triggers (header.php x2 mobile menu, index.php x3 paragraph + booking img, footer.php x1 paragraph, single.php x3 thumbnail + 2 action-buttons), ajout `aria-haspopup="dialog"` + `aria-controls="<dialog-id>"` sur tous, ajout `aria-label` sur les triggers contenant un `<img>` seul (booking, post thumbnail). Nouvelle component class `.lmt-link-button` dans `tailwind.input.css` qui reset les UA defaults du `<button>` pour préserver le rendu inline (font: inherit, background: transparent, etc.). `css/mixtape-page.css` mis à jour pour matcher `:is(a, button)` dans `.action-buttons`.

### [A11Y-003] Hiérarchie de titres confuse (`<h1>` dans la nav, `<h2>` partout ailleurs)
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php:30` ; `index.php:47` ; `single.php:6` ; `category.php:38` ; etc.
- **Description** : La navbar pose un `<h1>Lamixtape</h1>` sur **toutes** les pages. Les pages elles-mêmes n'ont pas de `<h1>` propre (single utilise `<h2>` pour le titre de la mixtape, category aussi). Pour un lecteur d'écran, chaque page commence par "Lamixtape" comme titre principal, le contenu réel est en `<h2>`.
- **Impact** : Violation WCAG 1.3.1 (niveau A) — structure sémantique incorrecte. Mauvais SEO secondaire.
- **Recommandation** : Garder le logo Lamixtape comme `<a><span>` (ou `<h1>` uniquement sur la home). Les templates posent leur propre `<h1>` (titre de la mixtape, "Genre : House", "Search: foo", "404 — Looks like you got lost").
- **Statut** : Résolu Phase 5. **header.php** : `<h1>Lamixtape</h1>` → `<span class="lmt-logo">` (CSS `nav h1` → `nav .lmt-logo` dans `navbar.css`). Chaque template pose son propre `<h1>` : **single.php** (titre mixtape, ex-`<h2>` ; CSS `article h2` → `article :is(h1, h2)`), **404.php** ("Looks like you got lost", ex-`<h2>`), **category.php** ("Genre : <name>", ex-`<h4>`), **search.php** ("Search: <terme>", ex-`<h4>`), **text.php** (titre page colophon/legal-notice, ex-`<h2>` ; CSS `body.page-template-text h2` → `:is(h1, h2)`), **index.php** (`<h1 class="sr-only">` "Lamixtape — monthly curated music mixtapes" pour la home), **explore.php** (`<h1 class="sr-only">` "Explore mixtapes"). Le `<h2>` mixtape-card dans `template-parts/card-mixtape.php` reste `<h2>` (sub-headings dans la liste). Tailwind v4 preflight reset `font-size: inherit` sur tous les headings garantit zéro régression visuelle hormis sur les éléments explicitement stylés (couverts par les selector updates).

### [A11Y-004] Pas de skip-link, pas de `<main>`, pas de landmarks
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php` (ouverture body) ; `footer.php` (fermeture)
- **Description** : Aucun `<a class="skip-link" href="#main">` (lien d'évitement vers le contenu). Aucune balise `<main>` (le contenu vit dans des `<section>` / `<article>` à la racine du `<body>`). La `<nav>` est unique mais sans `aria-label`. Pas de `<footer>` global (le `<footer>` dans `single.php:147` est vide).
- **Impact** : Violation WCAG 2.4.1 (niveau A) — pas de bypass blocks. Violation WCAG 1.3.1.
- **Recommandation** : Ajouter `<a class="skip-link" href="#main">Aller au contenu</a>` en première ligne de body. Wrapper le contenu de chaque template dans `<main id="main" tabindex="-1">`. Ajouter `aria-label="Navigation principale"` sur `<nav>`.
- **Statut** : Résolu Phase 5. **Skip link** : `<a class="lmt-skip-link" href="#main">Skip to main content</a>` ajouté en première ligne de `<body>` dans `header.php`. Composant `.lmt-skip-link` dans `tailwind.input.css` @layer components, visuellement caché (clip-path + position:absolute) jusqu'au focus clavier où il apparaît top-left sur fond #333 / texte #fff (cohérent avec la navbar). **`<main id="main" tabindex="-1">`** wrappe le contenu de chaque template : ouvert à la fin de `header.php` (après le mobile-menu-overlay), fermé au début de `footer.php` (avant les `<dialog>` modals). `tabindex="-1"` permet le focus programmatique depuis le skip link sans entrer dans le tab order. **`<nav>` aria-label** : `aria-label="Main navigation"` ajouté sur `<nav class="navbar">`. Les modals `<dialog>` restent à la racine de `<body>` après `</main>` (semantically correct, ils ne sont pas du contenu principal).

### [A11Y-005] Attribut `alt=""` posé sur des `<a>` (HTML invalide)
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `index.php:54` ; `single.php:14` ; `search.php:40` ; `category.php:51`
- **Description** : `<a class="mr-1" href="..." alt="View all posts in ...">` — `alt` n'est pas un attribut valide sur `<a>`. C'est probablement une confusion avec `title`.
- **Impact** : Information ignorée par tous les lecteurs d'écran. HTML invalide.
- **Recommandation** : Remplacer `alt=` par `title=` (ou mieux : `aria-label=` si le texte du lien est insuffisant).
- **Statut** : Résolu Phase 5 (`title=` hardcodé dans `template-parts/card-mixtape.php`, paramètre `tag_link_attr` supprimé du template-part et de tous ses callers — `index.php`, `search.php`, `single.php`, `category.php`, `inc/rest.php` x3 contexts. Le paramètre n'avait plus qu'une seule valeur valide après le fix, sa suppression simplifie l'API et garantit l'invalidité HTML ne peut plus revenir).

### [A11Y-006] Mobile menu overlay sans `aria-hidden`/focus trap
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `header.php:49-77` ; `js/main.js:100-138`
- **Description** : `#mobile-menu-overlay` est masqué via `display: none` mais sans `aria-hidden="true"` initial. À l'ouverture, aucune gestion du focus trap : la touche Tab peut sortir du menu vers la page derrière. ESC ferme (✓), mais pas de focus return sur le burger après fermeture.
- **Impact** : Lecteur d'écran annonce des liens cachés ; clavier perdu.
- **Recommandation** : Ajouter `aria-hidden="true"` initial. À l'ouverture, mettre `aria-hidden="false"` + `inert` sur le reste de la page + focus sur le bouton close. Au close, restaurer focus sur `#burger-btn`. Considérer le pattern "dialog modal" (rôle `dialog`, `aria-modal="true"`).
- **Statut** : Résolu Phase 5. **header.php** : `<div id="mobile-menu-overlay">` enrichi avec `role="dialog"`, `aria-modal="true"`, `aria-label="Mobile navigation"`, `aria-hidden="true"` initial. **js/main.js** : à l'ouverture (`openMenu()`), sauvegarde `document.activeElement` dans `lastFocused`, met `aria-hidden="false"`, focus le bouton close après le `fadeIn`, et applique `inert` sur tous les enfants directs de `<body>` sauf l'overlay (focus trap browser-natif). À la fermeture (`closeMenu()`), met `aria-hidden="true"`, retire `inert` de tous les siblings, et restaure le focus sur `lastFocused` (typiquement `#burger-btn`). Préserve le close ESC + click-outside existants. Le pattern `inert` est supporté Safari 15.4+, Chrome 102+, Firefox 112+ (couverture > 97% en 2026).

### [A11Y-007] Animations sans `prefers-reduced-motion`
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `css/general.css:64-78` (fade-in) ; `css/player.css:23-39` (marquee) ; `css/player.css:137-145` (slide-up)
- **Description** : Animations `fade-in`, `marquee` (titre du track qui défile), `player-slide-up` actives sans média query `prefers-reduced-motion: reduce`.
- **Impact** : Violation WCAG 2.3.3 (niveau AAA, mais good practice AA). Inconfort vestibulaire pour utilisateurs sensibles.
- **Recommandation** : Wrapper les `@keyframes` et `transition` dans `@media (prefers-reduced-motion: no-preference) { ... }`, ou ajouter `@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }`.
- **Statut** : Résolu en 2 phases. **Phase 4 CP3-4** : `.fade-in` homepage entrance animation supprimée intégralement (conflit avec infinite scroll, cf. trailer comment `general.css`). **Phase 5** : ajout du bloc global `@media (prefers-reduced-motion: reduce)` dans `css/general.css` qui force `animation-duration: 0.01ms !important`, `animation-iteration-count: 1 !important`, `transition-duration: 0.01ms !important`, `scroll-behavior: auto !important` sur `*, *::before, *::after`. Couvre les 3 animations restantes (`.menu-fade-in` navbar, `#title` marquee player, `.player-slide-up` player) ET toute animation future / plugin-émise. Pattern recommandé par MDN — 0.01ms préserve l'état final d'une transition sans motion visible (vs `none` qui peut laisser des intermediate states bloqués).

### [A11Y-008] Modals Bootstrap sans focus trap/role dialog WCAG
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `footer.php:1-44`
- **Description** : Les 2 modals (`#donatemodal`, `#contactmodal`) reposent intégralement sur le JS BS4 pour le focus trap. Bootstrap 4.4 gère partiellement ce trap, mais la pratique moderne est d'utiliser `<dialog>` HTML natif ou un composant dédié (Reach UI, Headless UI). `aria-labelledby` pointe sur l'ID du modal lui-même au lieu du `<h2>` titre.
- **Impact** : Comportement clavier non garanti hors BS4.
- **Recommandation** : Migrer vers `<dialog>` natif (`<dialog id="donatemodal"><h2 id="donatemodal-title">...</h2></dialog>`) avec `dialog.showModal()`. Ajustement `aria-labelledby="donatemodal-title"`.
- **Note d'observation (Phase 1)** : Chromium émet en console à l'ouverture d'un modal `Blocked aria-hidden on an element because its descendant retained focus.` Manifestation directe du finding : Bootstrap 4.4 pose `aria-hidden="true"` sur le modal pendant que le focus s'y trouve, et les navigateurs récents flaguent ce conflit. **Pas une régression Phase 1** — bug intrinsèque à BS4. Résolution attendue avec la migration `<dialog>` (Phase 5).
- **Note A11Y-NEW-001 (résolution Phase 4)** : la note d'observation Phase 1 ci-dessus a parfois été référencée sous l'identifiant `A11Y-NEW-001` (warning Chromium aria-hidden). **Résolu Phase 4** (`871b11d` migration `<dialog>` natif Axe C C16) : le composant `<dialog>` HTML5 ne pose plus `aria-hidden` pendant l'ouverture comme Bootstrap 4 le faisait — l'attribut `open` du `<dialog>` est mutuellement exclusif avec `aria-hidden`, et le browser-natif gère le focus management sans le conflit BS4. À traiter ici comme sous-note de A11Y-008 plutôt que finding indépendant.
- **Statut** : Résolu en 2 phases. **Phase 4** (`871b11d` Axe C C16) : migration markup `<dialog id="donatemodal" aria-labelledby="donatemodal-title">` + `<h2 id="donatemodal-title">` dans `footer.php` + handler `js/dialogs.js` qui appelle `dialog.showModal()` (focus trap browser-natif + `aria-modal="true"` auto + restauration focus auto sur close). **Phase 5** (vérification) : `aria-labelledby` confirmé pointer correctement vers le `<h2>` titre (et non plus vers l'ID du modal lui-même comme avant Phase 4). Aucun changement de code Phase 5 — la migration `<dialog>` natif a couvert l'intégralité de la recommandation. La sous-note A11Y-NEW-001 (warning Chromium `aria-hidden on focused element`) est résolue par la même migration.

### [A11Y-009] Couleurs ACF `color` appliquées sans contrôle de contraste
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `single.php:2,118` ; `index.php:44` ; `category.php:34` ; `search.php:30`
- **Description** : Le champ ACF `color` est appliqué brut en `background-color` sur les `<article>` qui contiennent du texte blanc et des liens curator. Aucun garde-fou : un curator peut saisir `#FFFFFF`, `#FFE066`, etc., rendant le texte illisible.
- **Impact** : Violation WCAG 1.4.3 (niveau AA — contraste 4.5:1) potentielle.
- **Recommandation** : Soit fournir une palette restreinte côté ACF (champ `select` ou `color_picker` avec presets validés), soit calculer la luminance côté PHP et choisir blanc/noir comme couleur de texte automatiquement, soit overlay sombre semi-transparent.
- **Statut** : Résolu Phase 5 (Option B — luminance-driven foreground). Décision business prise par l'utilisateur en marathon : ne pas restreindre la palette ACF (préserve la liberté curator), pas d'overlay (préserve le branding visuel), basculer la couleur de texte de #fff à #000 automatiquement quand la luminance du `background-color` ACF dépasse 0.5. **Implementation** : nouveau helper `lmt_contrast_text_color( $hex )` dans `inc/queries.php` (formule WCAG relative luminance : sRGB linéaire + pondération 0.2126 R + 0.7152 G + 0.0722 B, seuil 0.5 — pattern standard GitHub/Material). **Edge cases** : hex 3-char auto-expansé en 6, hex malformé → fallback `#fff`. **Templates** : `template-parts/card-mixtape.php` + `single.php` ajoutent `color: <fg>` inline en plus du `background-color`. **Fix cascade** : règle `article a, article small { color: inherit }` ajoutée dans `css/general.css` (specificity 0,0,2 bat `a, body, small { color: #fff }` 0,0,1). Les `<article>` sans inline color (home, text page) restent sur #fff via héritage body. Couvre toute couleur ACF passée et future sans intervention curator. Changement visuel : sur les cards où le texte était illisible (background clair + texte blanc), le texte passe au noir → on RÉPARE la lisibilité plutôt qu'on dégrade.

### [A11Y-010] Comment form : labels manquants (placeholders only)
- **Sévérité** : Basse
- **Axe** : A11y
- **Fichier(s)** : `functions.php:204-240`
- **Description** : `my_update_comment_fields()` génère les `<input>` avec `placeholder="Name"` mais sans `<label for="...">`. Le placeholder n'est pas un substitut accessible.
- **Impact** : Violation WCAG 1.3.1 / 4.1.2.
- **Recommandation** : Ajouter `<label for="author" class="sr-only">Name</label>` (et idem email/url). Garder le placeholder pour l'usage visuel.
- **Statut** : Résolu Phase 2.5 (`9f24105` + `3cb6aad` + `50d490d` + `d844b33` — module commentaires entièrement supprimé : `comments.php` retiré, callbacks `lmt_comment_callback` / `lmt_comment_form_fields` / `lmt_comment_form_textarea` supprimés de `functions.php`, `css/comment-form.css` retiré, BDD `comments_open=closed` sur tous les posts. Le formulaire n'existe plus, donc la question des `<label>` manquants devient moot — finding résolu par disparition du composant).

### [A11Y-011] Player : pas de `<label>` lié au seekbar
- **Sévérité** : Basse
- **Axe** : A11y
- **Fichier(s)** : `player.php:22`
- **Description** : `<input type="range" id="seekbar" aria-label="Seek">` — le label est minimal mais ne décrit pas la track en cours, et pas de `aria-valuetext` pour annoncer "01:23 sur 03:45".
- **Impact** : Lecteur d'écran annonce "Seek, 0 sur 100" — peu utile.
- **Recommandation** : Ajouter `aria-valuetext` mis à jour par JS (ex. `seekbar.setAttribute('aria-valuetext', '01:23 sur 03:45')`).
- **Statut** : Résolu Phase 5. **player.php** : `aria-label="Seek"` → `aria-label="Track progress"` (plus descriptif), ajout `aria-valuetext="00:00 of 00:00"` initial. **js/player.js** : nouvelle fonction `updateSeekbarAria(cur, dur)` appelée à chaque tick `updateTimeDisplay()` qui formate `aria-valuetext = "<HH:MM> of <HH:MM> — <trackTitle>"` (ex. "01:23 of 03:45 — Daft Punk - Voyager"). Couvre les 2 types de player (YouTube + MediaElement MP3/MP4). Lecteur d'écran annonce maintenant la position temporelle réelle au lieu de "0 of 100".

---

## <a id="qc"></a>4. Qualité code & dette technique

### [QC-001] Repo non versionné (pas de `.git`)
- **Sévérité** : Critique
- **Axe** : Qualité / Process
- **Fichier(s)** : N/A (absence de `.git`)
- **Description** : Le thème (et le projet parent `app/public/wp-content/themes/lamixtape/`) n'est pas un dépôt git. Aucun historique, aucun rollback possible, aucune branche, aucune PR review.
- **Impact** : **Tout refacto est non-réversible.** Une erreur = perte définitive (sauf backup serveur). Aucun audit du code historique possible. Bloquant pour tout travail sérieux.
- **Recommandation** : `git init` dans le dossier du thème, `.gitignore` couvrant `.DS_Store`, `node_modules/`, `vendor/`, `wp-config.php`, `*.log`, `.idea/`, `.vscode/`. Commit initial "as-is". Pousser vers GitHub/GitLab privé. **Action n°1 du refacto**, avant toute modification.
- **Statut** : Résolu Phase 0 (`9606b78` `git init` + `.gitignore` + commit initial as-is + remote SSH `git@github.com:nearmint/Lamixtape.git`).

### [QC-002] Logique métier (queries SQL, filtres) dans les templates
- **Sévérité** : Haute
- **Axe** : Qualité
- **Fichier(s)** : `single.php:99-111` ; `search.php:25-26` ; `guests.php:14-18` ; `index.php:38-41`
- **Description** : `single.php` définit une fonction `filter_where`, ajoute/retire des filtres WP, exécute des queries custom. `guests.php` exécute un `$wpdb->get_results()` brut. `search.php` exécute `new WP_Query("s=$s&showposts=-1")`. Aucune séparation entre "couche données" et "couche présentation".
- **Impact** : Tests impossibles, réutilisation impossible, code dupliqué (cf. QC-003), risque de fatal (cf. PERF-009).
- **Recommandation** : Extraire dans `inc/queries.php` (ou une classe `Lmt_Repository`). Templates ne contiennent que `the_loop` ou `get_template_part(..., $args)`.
- **Statut** : Résolu Phase 2 — 4 commits (`9b41c70` scaffold `inc/queries.php` + require depuis `functions.php` ; `df0590d` extraction previous-mixtapes → `lmt_get_previous_mixtapes()` ; `2b46822` extraction search → `lmt_get_search_results()` ; `37fd794` extraction guests → `lmt_get_curators()` + `lmt_get_posts_grouped_by_author()`). `single.php`, `search.php`, `guests.php` ne contiennent plus de WP_Query custom ni de filtres ajoutés/retirés. `index.php` (PERF-001) et `category.php` (PERF-007) gardent leur WP_Query inline en Phase 2 par décision UX (pagination = changement visuel) — extraction reportée Phase 3 avec PERF-001.

### [QC-003] Bloc "card mixtape" dupliqué 4 fois
- **Sévérité** : Haute
- **Axe** : Qualité
- **Fichier(s)** : `index.php:43-60` ; `single.php:118-134` ; `category.php:33-58` ; `search.php:30-46`
- **Description** : Le `<article style="background-color: ...">` avec le titre, l'icône highlight, les tags catégorie, le curator, est réécrit textuellement dans 4 templates avec micro-variations cosmétiques.
- **Impact** : Toute modification visuelle = 4 modifications à synchroniser. Source garantie de bugs.
- **Recommandation** : Extraire en `template-parts/card-mixtape.php`. Appel via `get_template_part('template-parts/card-mixtape')` dans la loop.
- **Statut** : Résolu Phase 2 (`1e658de` `template-parts/card-mixtape.php` créé, 4 instances factorisées via `get_template_part('template-parts/card-mixtape', null, $args)`). 6 micro-variations conservées via `$args` : `delay`, `article_extra_classes`, `h2_extra_classes`, `highlight_mode` (`always_span` / `conditional` / `none`), `hide_curator_on_small`, `tag_link_attr` (préserve A11Y-005 `alt=` sur `<a>` invalid HTML, fix tracé séparément).

### [QC-004] Text-domain littéral `'text-domain'` (placeholder jamais remplacé)
- **Sévérité** : Haute
- **Axe** : Qualité / i18n
- **Fichier(s)** : Tous les templates et `functions.php` (~40 occurrences)
- **Description** : Tous les `__()/_e()/esc_html__()` utilisent le slug `'text-domain'` (placeholder de générateur), jamais remplacé par un slug réel. `load_theme_textdomain` n'est appelé nulle part. Aucun fichier `.pot`/`.po`/`.mo`.
- **Impact** : Le site n'est traduisible **dans aucune langue**. Les outils i18n WP (Loco, WPML) ne trouveront pas le text-domain.
- **Recommandation** : `find/replace` global `'text-domain'` → `'lamixtape'`. Ajouter dans `functions.php` : `add_action('after_setup_theme', function() { load_theme_textdomain('lamixtape', get_template_directory() . '/languages'); });`. Générer un `.pot` initial.
- **Statut** : Résolu Phase 2 (`23cf296` 41 occurrences `'text-domain'` / `"text-domain"` remplacées par `'lamixtape'` / `"lamixtape"` sur 9 fichiers PHP via `perl -i -pe`. `load_theme_textdomain('lamixtape', .../languages)` ajouté dans `lmt_setup_theme()`. Dossier `languages/` créé (vide, `.gitkeep`). `.pot` à générer ultérieurement via `wp i18n make-pot`).

### [QC-005] Variables non initialisées (`$counter`, `$index`, `$s`)
- **Sévérité** : Haute
- **Axe** : Qualité
- **Fichier(s)** : `single.php:50,136-141` ; `search.php:26`
- **Description** : `single.php:136` incrémente `$counter` jamais initialisé. `single.php:50` lit `$index` sans initialisation. `search.php:26` utilise `$s` (alias historique de la query var search, dépend de `register_globals` ou des magic globals WP).
- **Impact** : Warnings PHP (sur PHP 8.1+ : `Undefined variable`), comportement non-déterministe. Sur PHP 8.2+ : warning explicite.
- **Recommandation** : Initialiser explicitement (`$counter = 0;` avant la boucle), retirer `$index` (variable morte), remplacer `$s` par `get_search_query()`.
- **Statut** : Résolu Phase 1 (`5465b59` $counter + `0f131e8` $index + `706d209` $s). Investigation a montré que `$counter` et `$index` étaient calculés/émis pour rien — suppression complète au lieu d'initialisation. `$s` remplacé par `get_search_query(false)` dans un array `WP_Query` (`showposts` deprecated remplacé par `posts_per_page`). Bug fonctionnel critique découvert au passage : avant fix, **toute recherche `/search/*` retournait le catalogue entier** (terme vide envoyé à WP_Query).

### [QC-006] `wp_localize_script` mal cadré (cf. SEC-006)
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `functions.php:267`
- **Description** : Cf. SEC-006 — handle `'jquery'` au lieu du handle thème. Doublon documentaire pour la traçabilité côté qualité.
- **Impact** : Variable `bloginfo` non garantie, risque de confusion avec d'autres scripts.
- **Recommandation** : Cf. SEC-006.
- **Statut** : Résolu Phase 1 (doublon documentaire de SEC-006 — `83d72cb` rename handle `'ajax-script'` → `'lmt-main'` + `72647ba` rename `bloginfo` → `lmtData` attaché à `'lmt-main'` + déplacement hook `'init'` → `'wp_enqueue_scripts'` + `62457a2` fusion de `loadmore_enqueue` + `push_script` dans `lmt_enqueue_assets()`. Backfill rétroactif Phase 6 prep — le finding parent SEC-006 portait déjà le Statut, QC-006 le tracait sous l'axe qualité sans Statut séparé).

### [QC-007] Inline `<style>` et `<script>` dispersés dans les templates
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `header.php:34,36,51` ; `player.php:1-31` (multiples `style="..."`), `34-397` (script entier inline) ; `search.php:52` ; `single.php:22-24` (script `var postid`) ; `index.php:31` ; `guests.php:7,24` ; `explore.php:9` ; etc. (~26 occurrences `style="..."` au total)
- **Description** : Styles inline dispersés (≥ 26 occurrences `style="..."`), JS inline dans `player.php` (~360 lignes), `search.php` (`fbq('track')`), `single.php` (`var postid = ...`).
- **Impact** : Impossible à minifier, bundler, lint, ou versionner proprement. CSP `unsafe-inline` requis. Bundle Tailwind inutile si du CSS reste inline.
- **Recommandation** : Extraire le JS du player dans `js/player.js`, enqueuer conditionnellement. `var postid` → `wp_localize_script` propre. Styles inline → classes utilities (Tailwind ou CSS custom). Suppression du `fbq` (cf. OTHER-008).
- **Statut** : Résolu Phase 1 + Phase 4. **Phase 1 partiel** (`c5f0382` extraction `var postid` → `lmtData.post_id` + `b3f655b` extraction `player.php` JS → `js/player.js`). **Phase 4 final** (Axe B C5-C15) : tous les `style="..."` statiques décoratifs (~21 occurrences dans header / explore / guests / single / etc.) absorbés en utilities Tailwind, ex. `style="margin-bottom: 5px"` → `mb-[5px]`, `style="height: 85px; display: flex; align-items: center"` → `h-[85px] flex items-center`, etc. Les 5 `style="background-color:<?php get_field('color') ?>"` PHP-injected dynamiques restent inline par nécessité (D-M-4.2 — couleur ACF unique par post). Umami inline conservé (décision Phase 1, snippet officiel SaaS).

### [QC-008] Aucun docblock, naming PHP incohérent, pas de namespace
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `functions.php` (toutes fonctions)
- **Description** : Coexistence de styles `cf_*`, `revcon_*`, `wpb_*`, `tape_*`, `social__*` (double underscore), `loadmore_*`, `SearchFilter` (PascalCase). Pas un seul `/** @param ... */`.
- **Impact** : Onboarding impossible, IDE incapable d'aider, refacto à risque.
- **Recommandation** : Préfixer toutes les fonctions du thème par `lmt_*`. Ajouter docblocks PHPDoc. Si OOP : namespace `Lamixtape\Theme`.
- **Statut** : Résolu Phase 2 — 5 commits thématiques (QC-008) : `4eafbf3` search-related (cf_search_join/where/distinct, SearchFilter, wp_change_search_url → `lmt_search_*`), `8c9745a` backoffice/admin (revcon_change_post_label/object, no_wordpress_errors → `lmt_relabel_*` / `lmt_obfuscate_login_errors`), `ba3cad6` head cleanup (wpb_remove_version, my_deregister_scripts, wps_deregister_styles → `lmt_remove_generator_version` / `lmt_deregister_wp_embed` / `lmt_deregister_block_library_css`), `d1d15ca` comment-related (tape_comment, my_update_comment_fields/field → `lmt_comment_callback` / `lmt_comment_form_fields` / `lmt_comment_form_textarea` ; +1 ligne comments.php callback), `2a982ad` RSS/feed + post-link (wcs_post_thumbnails_in_feeds, posts_link_attributes_1/2 → `lmt_rss_post_thumbnail` / `lmt_post_link_class_prev|next`). 17 fonctions renommées + PHPDoc. Décision D2 : pas de namespace OOP, procédural maintenu.

### [QC-009] `.DS_Store` présents dans le thème
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `./.DS_Store`, `css/.DS_Store`
- **Description** : 2 fichiers `.DS_Store` macOS présents. Pas critique tant qu'il n'y a pas de git, mais à exclure dès l'init.
- **Impact** : Bruit dans le futur dépôt.
- **Recommandation** : Ajouter `.DS_Store` au `.gitignore` initial. Supprimer du dossier (`find . -name '.DS_Store' -delete`).
- **Statut** : Résolu Phase 0 + Phase 1 (`9606b78` ajouté au `.gitignore` initial — jamais trackés dans git ; Phase 1 step 1.1 cleanup disque sans commit nécessaire).

### [QC-010] Documentation interne (`audit-prompt.md`, `CLAUDE.md`, `AUDIT.md`) servie depuis le thème
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `audit-prompt.md`, `CLAUDE.md`, `AUDIT.md`
- **Description** : Fichiers de documentation (audit, mémoire de travail) servis par WordPress comme part of the theme. Inutiles en runtime, et contiennent des éléments stratégiques internes.
- **Impact** : Bruit dans le bundle déployé. Si les fichiers `.md` sont accessibles via URL directe (selon config serveur), exposition d'info.
- **Recommandation** : Soit déplacer hors du thème (`docs/` à la racine du repo, hors `wp-content/`), soit ajouter une règle `.htaccess`/Nginx qui bloque l'accès aux `*.md`. Garder `CLAUDE.md` est utile pour les futures sessions Claude Code, mais idéalement hors prod.
- **Statut** : Résolu Phase 1 (`89991d6` `audit-prompt.md`, `AUDIT.md`, `prompt-phase-0.md`, `screenshot.png` déplacés dans `_docs/`. `CLAUDE.md` reste à la racine par convention Claude Code. Note : règle `.htaccess`/Nginx pour bloquer l'accès direct aux `*.md` reste à mettre côté infra hors thème).

### [QC-011] HTML conditionnels IE9 (`<!--[if lt IE 9]>`)
- **Sévérité** : Basse
- **Axe** : Qualité
- **Fichier(s)** : `header.php:20-24`
- **Description** : `<!--[if lt IE 9]> ... html5shiv ... respond.js ...` — IE 11 a perdu son dernier support en 2022, IE 9 jamais.
- **Impact** : Code mort, charge inutilement deux scripts en cas d'IE.
- **Recommandation** : Supprimer entièrement les lignes 20-24 de `header.php`.
- **Statut** : Résolu Phase 1 (`d81e307` bloc IE9 conditional comments + html5shim/html5shiv/respond.js supprimés de `header.php`).

### [QC-012] Usage incorrect de `__()` sur la chaîne `'%s'`
- **Sévérité** : Basse
- **Axe** : Qualité / i18n
- **Fichier(s)** : `functions.php:192`
- **Description** : `printf( __( '%s' ), get_comment_author_link() )` — traduire `'%s'` n'a pas de sens.
- **Impact** : Pollution du `.pot` si jamais généré, code confus.
- **Recommandation** : Remplacer par `echo get_comment_author_link();` (sans `printf`/`__`).
- **Statut** : Résolu Phase 1 (`5ee885e` `printf(__('%s'), get_comment_author_link())` remplacé par `echo get_comment_author_link();` dans le callback `tape_comment`).

### [QC-013] Code mort newsletter `#subscribe-form` orphelin
- **Sévérité** : Basse
- **Axe** : Qualité
- **Fichier(s)** : `js/main.js:3-4,7-50`
- **Description** : `js/main.js` cible `$("#subscribe-form")` et `$("#subscribe-form-2")`, qui ne sont **présents dans aucun template** du thème, et **aucun plugin newsletter n'est installé** (vérifié dans `wp-content/plugins/` : pas de `mailchimp-*`, `mc4wp`, `newsletter`, `subscribe-*`). Les fonctions `submitSubscribeForm`, `ajaxMailChimpForm`, `isValidEmail` (~50 lignes) sont mortes.
- **Impact** : Bytes inutiles.
- **Recommandation** : Supprimer les lignes 3-4 et 7-50 de `js/main.js`.
- **Statut** : Résolu Phase 1 (`370eec3` ~60 lignes JS newsletter supprimées de `js/main.js` + `css/newsletter.css` supprimé + `@import` correspondant retiré de `style.css`. Aucun plugin newsletter installé, code 100% mort confirmé).

### [QC-014] Référence à `about.php` (template inexistant)
- **Sévérité** : Basse
- **Axe** : Qualité
- **Fichier(s)** : `functions.php:69`
- **Description** : `if( is_page_template('about.php') ) $classes[] = 'about';` — aucun fichier `about.php` n'existe dans le thème.
- **Impact** : Code mort.
- **Recommandation** : Supprimer la fonction `prefix_conditional_body_class` ou réécrire si une page "about" est prévue.
- **Statut** : Résolu Phase 1 (`d9c0699` fonction `prefix_conditional_body_class` + son `add_filter` supprimés. Le template `about.php` n'a jamais existé. Trace de boilerplate générique tracée dans `CLAUDE.md` section 6).

### [QC-NEW-001] `WP_POST_REVISIONS` redéfinie sans garde
- **Sévérité** : Moyenne
- **Axe** : Qualité (et Fiabilité côté REST)
- **Fichier(s)** : `functions.php:127` (avant Phase 1.NEW)
- **Découvert** : Phase 1, retour utilisateur post-cleanup (warning visible sur toutes les pages côté Local).
- **Description** : `define( 'WP_POST_REVISIONS', 3 );` est déclaré sans test `defined()` préalable. La constante est déjà définie dans `wp-config.php`, ce qui produit un `Warning: Constant WP_POST_REVISIONS already defined in functions.php on line 127` sur **toutes** les pages quand `WP_DEBUG_DISPLAY` est `true` (cas Local par défaut).
- **Impact** : Au-delà du bruit visuel, le warning HTML pollue les réponses **REST** : la sortie n'est plus du JSON pur, le `Content-Type: application/json` ment, et les callbacks JS qui parsent la réponse échouent ou affichent du HTML. Régression observée sur le bouton like (handler côté client cassé après l'ajout du compteur retour serveur en Phase 0).
- **Recommandation** : Wrapper la définition dans `if ( ! defined( 'WP_POST_REVISIONS' ) ) { ... }`. À terme (cf. PERF-014), supprimer la définition du thème et ne la conserver que dans `wp-config.php`.
- **Statut** : Résolu Phase 1 (`389dd3c` wrap `if ( ! defined('WP_POST_REVISIONS') )` ajouté dans `functions.php`. À terme cf. PERF-014 pour déplacement vers `wp-config.php`).

### [QC-NEW-002] Smooth scroll handler crashe sur les liens factices `href="#"`
- **Sévérité** : Moyenne
- **Axe** : Qualité (robustesse JS)
- **Fichier(s)** : `js/main.js:121-129` (bloc `// Smooth scrolling`)
- **Découvert** : Phase 1, retour utilisateur post-cleanup (rendu visible une fois les autres warnings PHP/JS de console nettoyés).
- **Description** : Le handler smooth scroll s'attache à tout `<a href^="#">` (sélecteur `'a[href^="#"]'`) et exécute `document.querySelector(this.getAttribute('href'))` au click. Pour les liens factices `href="#"` (modals donation/contact, like btn historique, etc., cf. A11Y-002), `querySelector('#')` est un sélecteur CSS invalide → `Uncaught SyntaxError: Failed to execute 'querySelector' on 'Document': '#' is not a valid selector`.
- **Impact** : Pas d'impact UX visible (les modals s'ouvrent quand même via leur propre handler Bootstrap, indépendant). Pollution console à chaque click sur un placeholder link. Code qui ne défend pas contre un cas trivial — peut casser des features futures (Sentry/error reporters strictes, rebuilds JS, etc.).
- **Recommandation** : Early-return si `href` est nul/vide ou égal à `'#'` AVANT l'appel à `querySelector`. Bug pré-existant depuis le commit initial (`9606b78`), masqué par d'autres erreurs console jusqu'à Phase 1. Le souci A11y sous-jacent (`href="#"` sur des éléments qui devraient être `<button>`) est tracé séparément sous **A11Y-002** et reste à traiter en Phase 5.
- **Statut** : Résolu Phase 1 (`0474328` early-return sur `href` null/vide/`'#'` dans le smooth scroll handler de `js/main.js` + commentaire TODO Phase 5 pointant vers A11Y-002).

---

## <a id="wp"></a>5. Bonnes pratiques WordPress

### [WP-001] `style.css` n'est pas enqueued (link CSS en dur dans header)
- **Sévérité** : Haute
- **Axe** : WP best practices
- **Fichier(s)** : `header.php:6` ; `functions.php` (absence)
- **Description** : `<link rel="stylesheet" href=".../style.css">` est codé en dur dans `header.php`. WP attend qu'un thème enqueue son `style.css` via `wp_enqueue_style('lmt-style', get_stylesheet_uri())`. Aucun child theme ne pourra surcharger correctement.
- **Impact** : Violation WP Coding Standards. Impossibilité de child theme. Pas de versionnement (cache busting) automatique.
- **Recommandation** : Supprimer le `<link>` en dur. Ajouter `wp_enqueue_style('lmt-main', get_stylesheet_uri(), [], wp_get_theme()->get('Version'))` dans `wp_enqueue_scripts`.
- **Statut** : Résolu Phase 1 (`97a7e96` `<link>` en dur supprimé de `header.php`, `style.css` réduit au header de thème WP, fonction `lmt_enqueue_assets()` créée pour enqueuer les 14 CSS thème via `wp_enqueue_style` avec ordre de cascade préservé).

### [WP-002] Pas de `theme.json`
- **Sévérité** : Haute
- **Axe** : WP best practices
- **Fichier(s)** : N/A
- **Description** : Le thème ne fournit aucun `theme.json`. Or depuis WP 5.8, `theme.json` permet de définir la palette, la typographie, les espacements, et de désactiver proprement Gutenberg. Le thème désactive `wp-block-library` à la main (functions.php:97-100), mais sans `theme.json`, l'intégration block editor reste fragile.
- **Impact** : Pas de palette globale, pas de spacings cohérents avec Gutenberg, pas de styles globaux.
- **Recommandation** : Ajouter un `theme.json` minimal (`version: 2`, `settings.color.palette`, `settings.typography.fontFamilies` avec Outfit, `settings.layout.contentSize`).
- **Statut** : Résolu Phase 2 (`945b5c5` `theme.json` v2 minimal — `settings.layout.contentSize: 1140px` + `wideSize: 1320px`. Pas de palette / fontFamilies / spacing — réservés Phase 4 Tailwind où ils auront leur source de vérité unique. Décision D3).

### [WP-003] `add_theme_support` minimal (uniquement `post-thumbnails`)
- **Sévérité** : Haute
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:6` (ligne unique)
- **Description** : Seul `post-thumbnails` est activé. Manquent : `title-tag` (WP gère le `<title>`), `html5` (forms/comments markup HTML5), `automatic-feed-links`, `responsive-embeds`, `editor-styles`, `align-wide`.
- **Impact** : Le thème ne joue pas son rôle d'interface entre WP et la page. Rank Math compense mais n'est pas garanti à 100%.
- **Recommandation** : Ajouter dans un `after_setup_theme` :
  ```php
  add_theme_support('title-tag');
  add_theme_support('html5', ['comment-form', 'comment-list', 'gallery', 'caption', 'search-form']);
  add_theme_support('automatic-feed-links');
  add_theme_support('responsive-embeds');
  add_theme_support('editor-styles');
  ```
- **Statut** : Résolu Phase 2 (`68f406d` `lmt_setup_theme()` sur `after_setup_theme` consolide `post-thumbnails` (déplacé du top-level) + ajoute `title-tag`, `html5` (comment-form/list, gallery, caption, search-form, style, script), `automatic-feed-links`, `responsive-embeds`, `editor-styles`. Aucun `<title>` hardcodé en `header.php`, donc pas de risque de doublon avec Rank Math (qui surcharge via `pre_get_document_title`). Test 2 Rank Math fallback skippé en mode marathon (D-MARATHON-4) ; Test 1 (Rank Math actif) à valider côté product à la closure des 8 captures).

### [WP-004] CSS et JS tiers non-enqueued (CDN en dur)
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `header.php:6,8,9` ; `footer.php:47` ; `style.css:11-13` ; `analytics.php:2` ; `player.php` (YouTube IFrame)
- **Description** : Bootstrap, jQuery, MediaElement, font Outfit, Umami, YouTube IFrame API : tous chargés via balises `<script>`/`<link>` en dur ou via `@import` dans `style.css`. Aucun n'est passé par `wp_enqueue_*`.
- **Impact** : Pas de gestion des dépendances par WP, pas de cache busting, pas de `defer/async` côté WP, pas de désenqueue propre par d'autres thèmes/plugins.
- **Recommandation** : Tout passer par `wp_enqueue_style()` et `wp_enqueue_script()`. Charger conditionnellement (ex. MediaElement seulement sur `is_singular('post')`).
- **Statut** : Résolu Phase 1 (`97a7e96` Bootstrap CSS + Outfit + 14 theme CSS via `wp_enqueue_style` + `e064272` Bootstrap bundle JS via `wp_enqueue_script` + `ed7bb07` MediaElement via `wp-mediaelement` WP-bundled + `863ee0f` jQuery CDN supprimé, jQuery WP-bundled utilisé via dependency chain). Note : chargement conditionnel par template (404.css sur `is_404()`, etc.) reporté à un follow-up commit (cf. note dans lmt_enqueue_assets pour préserver la cascade de la migration initiale). Umami reste inline (analytics.php — snippet officiel, defer).

### [WP-005] Hack "rename Posts → Playlist" sur le post type natif
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:105-132`
- **Description** : `revcon_change_post_label()` et `revcon_change_post_object()` modifient les labels du post type `post` au lieu de créer un CPT `mixtape`. Conséquences : (1) impossibilité d'avoir des posts "classiques" (blog actu, etc.), (2) toutes les fonctions WP qui ciblent `post_type=post` (RSS, archives, etc.) sont impactées, (3) migration future vers un vrai CPT = lourde (migration BDD `wp_posts.post_type`).
- **Impact** : Dette structurelle. Si Lamixtape veut un blog parallèle, il faudra créer un CPT `news` quand même.
- **Recommandation** : Décision business à prendre (cf. CLAUDE.md Q.1). Garder pour ce refacto, planifier la migration vers un vrai CPT en phase 2.
- **Statut** : Reporté décision business migration CPT, hors scope refacto thème actuel (cf. CLAUDE.md section 7 Q1). La migration impose : (1) création d'un CPT `mixtape` dans le thème, (2) migration BDD massive `UPDATE wp_posts SET post_type='mixtape' WHERE post_type='post'` sur ~370 posts, (3) redirections SEO 301 (URLs /<slug>/ inchangées si rewrite slug = ''), (4) export/import ACF, (5) maj sitemap Rank Math, (6) maj toutes les requêtes `WP_Query`/`get_recent_posts` du thème. Volume + risque trop élevés pour ce refacto (qui s'arrête à un thème stable post-Phase-6, déployable as-is). À reprendre lors d'une phase d'évolution structurelle ultérieure si Lamixtape veut un blog parallèle (besoin business identifié) ou si la dette devient bloquante.

### [WP-006] `WP_POST_REVISIONS` défini dans le thème (cf. PERF-014)
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:137`
- **Description** : Cf. PERF-014.
- **Impact** : Cf. PERF-014.
- **Recommandation** : Cf. PERF-014.
- **Statut** : Reporté infrastructure (doublon documentaire de PERF-014). Cf. PERF-014 pour le détail du Statut. La résolution complète impose de modifier `wp-config.php` (hors scope thème). Mitigation Phase 1 en place via wrap `if ( ! defined() )`.

### [WP-007] `setup_postdata` sur boucle custom sans `wp_reset_postdata` propre
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `single.php:114-117`
- **Description** : Boucle `foreach ($pageposts as $post) { setup_postdata($post); ... }` — pas de `wp_reset_postdata()` à la fin (la boucle se termine sans reset, ce qui peut polluer le `$post` global pour le code suivant comme `get_footer()`).
- **Impact** : Données de post incorrectes dans le footer ou les widgets après la boucle.
- **Recommandation** : Ajouter `wp_reset_postdata();` après `endforeach;`.
- **Statut** : Résolu Phase 2 (`f606aa8` `wp_reset_postdata()` ajouté après `endforeach;` dans la boucle previous-mixtapes de `single.php`. Restaure le `$post` global pour `get_footer()` et widgets aval).

### [WP-008] Pas de `add_theme_support('responsive-embeds')`
- **Sévérité** : Basse
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php` (absence)
- **Description** : Le thème intègre des iframes YouTube via le player ; sans `responsive-embeds`, les embeds Gutenberg ne sont pas responsive automatiquement.
- **Impact** : Embed Gutenberg dans le contenu = pas responsive.
- **Recommandation** : Ajouter (cf. WP-003).
- **Statut** : Résolu Phase 2 (`68f406d` `add_theme_support('responsive-embeds')` ajouté dans `lmt_setup_theme()`, voir WP-003).

### [WP-009] `wp_change_search_url` ne nettoie pas le query string
- **Sévérité** : Basse
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:56-62`
- **Description** : `wp_safe_redirect( get_home_url(...) . urlencode( get_query_var('s') ) )` — si `s=` contient déjà des caractères URL-encodés (ex. `%20`), le `urlencode` re-encode (`%2520`), brouillant l'URL.
- **Impact** : URL de recherche cassée pour des termes complexes.
- **Recommandation** : Utiliser `rawurlencode()` (ou laisser WP gérer via `add_query_arg`).
- **Statut** : Résolu Phase 2 (`ea5958f` `urlencode()` remplacé par `rawurlencode()` dans `lmt_search_url_redirect()` (anciennement `wp_change_search_url`). Évite le double-encodage `%2520` sur les termes contenant déjà des caractères encodés).

---

## <a id="tailwind"></a>6. Migration Bootstrap → Tailwind

### [TW-001] Bootstrap 4.4.1 fortement utilisé (grille + utilities + composants)
- **Sévérité** : Haute
- **Axe** : Tailwind
- **Fichier(s)** : Tous les templates (153 occurrences de classes BS au total)
- **Description** : Usage massif :
  - **Grille** : `container`, `row`, `col-lg-8`, `col-md-12`, `col-xs`, `col-2`, `col-4`
  - **Utilities** : `d-none`, `d-sm-none`, `d-md-block`, `d-flex`, `text-right`, `text-center`, `text-uppercase`, `text-truncate`, `text-lowercase`, `mb-0`, `mb-3`, `mb-4`, `mt-4`, `mt-5`, `pb-2`, `pb-4`, `pb-5`, `pt-1`, `pt-2`, `pt-3`, `pt-4`, `pt-5`, `pl-`, `pr-`, `mr-1`, `mr-2`, `mr-3`, `mr-n3`, `ml-1`, `ml-3`, `float-left`, `float-right`, `align-items-center`
  - **Composants** : `btn`, `btn-link`, `btn-outline-light`, `btn-xs`, `form-control`, `form-group`, `list-inline`, `list-inline-item`, `list-unstyled`, `modal`, `modal-dialog`, `modal-lg`, `modal-content`, `modal-body`, `close`, `embed-responsive`, `embed-responsive-16by9`, `img-fluid`, `collapse`, `multi-collapse`, `tab-content`
- **Impact** : Migration = travail sur **tous les templates**. Cohabitation BS+TW possible mais générant des conflits de reset CSS.
- **Recommandation** : Stratégie itérative par template. Mapping :
  - `container` → `container mx-auto px-4`
  - `row` / `col-md-8` → `flex flex-wrap` / `md:w-2/3`
  - `d-none d-sm-none d-md-none d-lg-block` → `hidden lg:block`
  - `text-center` → `text-center` (identique)
  - `mb-3` → `mb-3` (identique côté Tailwind v4 spacing scale par défaut)
  - `float-left/right` → `float-left/float-right` (identique)
  - `img-fluid` → `max-w-full h-auto`
  - `text-truncate` → `truncate`
  - `embed-responsive embed-responsive-16by9` → `aspect-video`
- **Statut** : Résolu Phase 4 — migration intégrale Bootstrap → Tailwind v4 sur branche `feature/tailwind-migration` mergée en main. Setup Axe A (`28f025f` Tailwind CLI standalone v4.1.18 + `f99b6d7` mapping doc + `c4f4331` @source PHP scan + `19a2b7a` `prefix(tw)` cohabitation), 11 templates migrés en Axe B (`8bed8bc` 404 + `1845e75` text + `a690430` explore + `fc2d3bf` guests + `4afb8d1` header + `9c0bb36` single + `9351af8` index + `77be723` category + `b7c9dcf` search + `73ed8dd` card-mixtape ; footer skippé Axe B car migration intégrale en Axe C), Bootstrap CSS supprimé (`0d763ef` enqueue + dossier `assets/vendor/bootstrap/` ~236 KB libérés), préfixe `tw:` strippé sur tous les templates (`3c7c13f` find/replace mécanique perl -i + rebuild Tailwind sans `prefix(tw)`). 4 régressions visuelles détectées et corrigées en CHECKPOINT 2 (`85198ac` svg display preflight, `88e844e` link underline override WP wp-block-library, `7cfe6f7` burger-menu dead CSS, `1b2fee6` --breakpoint-lg=62rem) + 4 régressions CHECKPOINT 3 (`32fad1f` flex-1→lg:w-1/3, `5e58712` navbar flex utilities, `64f7aeb` suppression fade-in, `a847a42` modal centering position fixed inset margin auto). Apprentissages techniques tracés dans CLAUDE.md section 4 (D-COHAB-1 prefix, TW-SCAN @source, TW-VERIFY grep -c minified).

### [TW-002] Modals dépendent de Bootstrap JS + jQuery + Popper
- **Sévérité** : Haute
- **Axe** : Tailwind
- **Fichier(s)** : `header.php:71-72` ; `footer.php:1-44`
- **Description** : `data-toggle="modal" data-target="#donatemodal"` repose sur `bootstrap.min.js` (qui dépend de jQuery + Popper.js). Tailwind n'inclut **pas** de JS de modal.
- **Impact** : Sans BS JS, les modals ne s'ouvrent pas. Migration Tailwind = obligation de remplacer ou de garder BS JS temporairement.
- **Recommandation** : Migrer vers `<dialog>` HTML natif :
  ```html
  <dialog id="donatemodal" class="..."><h2>Support us</h2>...</dialog>
  <button onclick="document.getElementById('donatemodal').showModal()">Donate</button>
  ```
  Ou Alpine.js (3 KB) si on veut du déclaratif. **Cohérent avec A11Y-008.**
- **Statut** : Résolu Phase 4 (Axe C) — `871b11d` markup `<dialog>` natif HTML5 + `js/dialogs.js` vanilla (event delegation, `closeAllDialogs` avant chaque `showModal()`, fermeture via close button / backdrop / Escape, focus trap + aria-modal automatiques côté browser). 9 triggers `data-toggle="modal"` remplacés par `data-lmt-dialog="..."` dans header/index/single/footer. `82aa39f` styles components `.lmt-dialog` / `.lmt-dialog::backdrop` / `.lmt-dialog-content` / `.lmt-dialog-close` dans `tailwind.input.css @layer components`. `8af8fac` Bootstrap JS bundle (`bootstrap.bundle.min.js` ~80 KB incluant Popper) supprimé de `lmt_enqueue_assets()`. `a847a42` correctif centrage modal CHECKPOINT 3 (`position: fixed; inset: 0; margin: auto`). Plus aucune dépendance Bootstrap JS / jQuery / Popper sur le système modal. La classe `.modal-content` est préservée comme alias sur `.lmt-dialog-content` pour maintenir la compatibilité avec les rules `#donatemodal .modal-content` existantes dans `css/donation.css`.

### [TW-003] Menu mobile déjà custom mais couplé visuellement aux classes BS
- **Sévérité** : Moyenne
- **Axe** : Tailwind
- **Fichier(s)** : `header.php:49-77` ; `js/main.js:100-138`
- **Description** : Le mobile menu est implémenté en jQuery custom (pas en BS Collapse), donc fonctionnellement indépendant. Mais visuellement il utilise `container`, `list-inline-item`, etc.
- **Impact** : Faible — point d'entrée facile pour la migration.
- **Recommandation** : Bonne candidate pour le **premier template migré** (avec `404.php` et `text.php`).
- **Statut** : Résolu Phase 4 (Axe B) — `4afb8d1` header.php migré : `container` → `container mx-auto px-4`, `list-inline text-uppercase` → `flex gap-x-2 uppercase`, `list-inline-item` retiré (le parent flex suffit), inline styles redondants supprimés (theme rules `#close-mobile-menu` / `#mobile-menu-overlay ul` / `#mobile-menu-overlay a` couvrent déjà). `5e58712` correctif CHECKPOINT 3 : ajout de `flex flex-wrap items-center justify-between` sur le container du `<nav>` pour remplacer la rule BS `.navbar > .container` désormais cassée par le rename `container` → `tw:container` (puis prefix-stripped). `64f7aeb` suppression de `fade-in delay-1` sur les éléments du header (animation incompatible avec l'infinite scroll, supprimée en bloc).

### [TW-004] Player utilise `embed-responsive embed-responsive-16by9` BS4
- **Sévérité** : Moyenne
- **Axe** : Tailwind
- **Fichier(s)** : `single.php:73-75` ; `player.css`
- **Description** : Wrapper `embed-responsive embed-responsive-16by9` pour l'iframe YouTube. À remplacer par `aspect-video` (Tailwind v4) ou `aspect-ratio: 16/9` CSS natif.
- **Impact** : Moyen — fonctionnel à migrer, sans casse.
- **Recommandation** : Remplacer par `<div class="aspect-video relative w-full">`.
- **Statut** : Résolu Phase 4 (Axe B) — `9c0bb36` dans `single.php`, le wrapper `<div class="embed-responsive embed-responsive-16by9" style="display:none">` autour de `#youtubePlayer` devient `<div class="aspect-video relative hidden">`. La classe `aspect-video` (Tailwind v4 utility natif) génère `aspect-ratio: 16/9` directement. Le `display: none` reste (le YouTube player est masqué par design — Lamixtape extrait l'audio uniquement, l'iframe vidéo n'est jamais visible).

### [TW-005] `mediaelementplayer.css` chargé sans usage visuel
- **Sévérité** : Moyenne
- **Axe** : Tailwind / Performance
- **Fichier(s)** : `style.css:12` ; `player.php:90-115` (init `features: []`)
- **Description** : MediaElement.js est initialisé avec `features: []` (aucun contrôle natif de MediaElement affiché — tout est custom dans `#footer-player`). Pourtant `mediaelementplayer.css` est chargée (~30 KB).
- **Impact** : 30 KB inutiles téléchargés/parsés.
- **Recommandation** : Désactiver l'`@import` MediaElement CSS dans `style.css`. Tester que le player fonctionne (les contrôles custom sont gérés par notre JS).
- **Statut** : Résolu Phase 4 (Axe D C20) — `63ce4b4` `wp_enqueue_style('wp-mediaelement')` retiré de `lmt_enqueue_assets()`. Le script `wp-mediaelement` reste enqueué (dépendance dure de `js/player.js` pour le décodage audio MP3 + la postMessage YouTube). WP n'auto-enqueue pas la CSS comme dépendance du script (vérifié dans le core : la CSS est enregistrée séparément et seulement enqueuée via `wp_video_shortcode()` / `wp_audio_shortcode()`, deux fonctions que le thème n'utilise pas). ~30 KB CSS gagnés sur chaque page front-end.

---

## <a id="autres"></a>7. Autres (SEO, RGPD, observabilité)

### [OTHER-001] Umami Analytics chargé sans bandeau de consentement formel
- **Sévérité** : Haute
- **Axe** : RGPD
- **Fichier(s)** : `analytics.php:2`
- **Description** : Umami (`cloud.umami.is`) est un analytics anonyme, pas de cookies tiers, pas d'ID utilisateur — la CNIL le considère généralement comme "exempté" du consentement (cf. doctrine 2022). MAIS : aucune mention dans une politique de confidentialité, aucun bandeau, aucun lien `Cookie policy` en footer. La conformité dépend de la documentation côté contenu, pas du code.
- **Impact** : Risque RGPD modéré (faible amende potentielle). Manque de transparence.
- **Recommandation** : Confirmer auprès du legal/CNIL que Umami Cloud (et non pas le self-hosted) bénéficie bien de l'exemption pour le périmètre Lamixtape. Ajouter une page `legal-notice` (déjà liée dans le menu mobile, header.php:74) qui mentionne Umami. Sinon, ajouter un bandeau de consentement.
- **Statut** : Reporté infrastructure + business (Phase 6 prep). Le fix nécessite (1) une décision legal/CNIL sur l'exemption d'Umami Cloud (business), et (2) la mise à jour du contenu de la page legal-notice + éventuel bandeau de consentement (côté contenu WP admin, pas dans le code thème). Page legal-notice déjà existante et liée dans le menu mobile (header.php:62 post-Phase-5). À reprendre par l'équipe legal + content. Note : Umami est aujourd'hui inline dans `analytics.php` avec attribut `defer` ; le snippet n'utilise pas de cookies (anonyme), donc le risque RGPD est techniquement bas mais la transparence est à formaliser.

### [OTHER-002] `add_theme_support('title-tag')` désactivé → `<title>` non géré par WP
- **Sévérité** : Haute
- **Axe** : SEO
- **Fichier(s)** : `header.php` (absence de `<title>`) ; `functions.php` (absence de `add_theme_support`)
- **Description** : Pas de `<title>` dans `<head>` de `header.php`, et pas d'`add_theme_support('title-tag')` qui laisserait WP générer le titre via `wp_head`. Rank Math compense en injectant un `<title>` via `wp_head`, mais si Rank Math est désactivé (debug, conflit), **aucun `<title>` n'est généré**.
- **Impact** : Dépendance forte à Rank Math, fragilité SEO.
- **Recommandation** : Ajouter `add_theme_support('title-tag')` (cf. WP-003). Rank Math reste compatible et continue de surcharger le `<title>` quand activé.
- **Statut** : Résolu Phase 2 (`68f406d` `add_theme_support('title-tag')` ajouté dans `lmt_setup_theme()` — voir WP-003 pour la liste complète des supports activés). Vérifié `functions.php:21` post-Phase-5. Backfill rétroactif Phase 6 prep — la résolution était couverte par le fix WP-003 mais le Statut n'avait pas été propagé sur OTHER-002.

### [OTHER-003] Aucune Open Graph / Twitter Card côté thème
- **Sévérité** : Moyenne
- **Axe** : SEO / Social
- **Fichier(s)** : `header.php` (absence)
- **Description** : Pas de `<meta property="og:title">`, `<meta property="og:image">`, `<meta name="twitter:card">` dans le `<head>`. Délégué à Rank Math.
- **Impact** : Cf. OTHER-002 — dépendance à Rank Math. Si Rank Math échoue, partages sociaux moches.
- **Recommandation** : Tester avec Rank Math désactivé et confirmer l'OG. Sinon, fallback dans `header.php` (post thumbnail comme `og:image`).
- **Statut** : Résolu Phase 6 (Axe B). Décision business : **fallback préventif** côté thème (Décision 4 utilisateur), plutôt qu'un audit conditionnel. Nouveau fichier `inc/seo.php` (chargé via `require_once` dans `functions.php`) qui hook `wp_head` priorité 20 et émet OG + Twitter Cards UNIQUEMENT si Rank Math n'est pas actif (détection canonique via `defined( 'RANK_MATH_VERSION' )`). Quand Rank Math est actif (cas par défaut Lamixtape), ce fallback est un no-op — pas de duplication de meta. Quand Rank Math est désactivé/désinstallé/échec, le thème émet par lui-même : `og:type` (article pour single, website pour le reste), `og:title`, `og:description` (excerpt 30 mots ou tagline), `og:url`, `og:site_name`, `og:locale`, `og:image` (post thumbnail si dispo), `twitter:card` (`summary_large_image` si image, `summary` sinon), `twitter:title`, `twitter:description`, `twitter:image`. Coverage des 8 templates (single, home, category, search, 404, page-templates colophon/legal-notice/explore/guests).

### [OTHER-004] Favicons et webmanifest référencés en `/...` sans garantie d'existence
- **Sévérité** : Moyenne
- **Axe** : SEO / UX
- **Fichier(s)** : `header.php:11-15`
- **Description** : `<link rel="icon" href="/favicon-32x32.png">` etc. — les chemins sont absolus à la racine du site, pas dans le thème. Si les fichiers ne sont pas à la racine du document root (probablement dans `/wp-content/uploads/` ou ailleurs), 404 sur favicon.
- **Impact** : Favicon manquant, manifest cassé, mauvaise PWA-ability.
- **Recommandation** : Vérifier que `favicon-32x32.png`, `apple-touch-icon.png`, `site.webmanifest`, `safari-pinned-tab.svg` existent à la racine du site. Sinon, déplacer dans `img/` du thème et corriger les chemins (`<?php echo esc_url(get_template_directory_uri()); ?>/img/favicon-32x32.png`).
- **Statut** : Reporté décision business + infrastructure (Phase 6 prep). La résolution impose de tester l'existence des 4 fichiers favicon à la racine du site (`curl -I https://lamixtape.fr/favicon-32x32.png` etc.) côté utilisateur. Si les fichiers existent → finding moot, juste un Statut "Vérifié OK" à poser. Si absents → décision : (1) déplacer les fichiers dans `img/` du thème + maj `header.php` chemins absolus → relatifs (changement code thème), ou (2) uploader les fichiers manquants à la racine du document root (changement infrastructure côté hébergeur). Décision technique mineure mais nécessite la vérification live à charge utilisateur.

### [OTHER-005] Pas de robots.txt / sitemap.xml côté thème
- **Sévérité** : Basse
- **Axe** : SEO
- **Fichier(s)** : N/A
- **Description** : Délégué à Rank Math (qui gère bien les sitemaps). Mention pour traçabilité.
- **Impact** : Aucun si Rank Math actif. À confirmer.
- **Recommandation** : Vérifier que `https://lamixtape.fr/sitemap_index.xml` répond bien.
- **Statut** : Reporté infrastructure (Phase 6 prep) — non-actionable côté code thème par construction. Rank Math est listé dans CLAUDE.md section 2 comme plugin actif avec sitemap functionnel. Vérification live `curl -I https://lamixtape.fr/sitemap_index.xml` à charge utilisateur. Si réponse 200 → finding résolu de fait. Si réponse 404 → reconfigurer Rank Math en admin WP (toujours hors code thème).

### [OTHER-006] Aucune structured data Schema.org pour les mixtapes
- **Sévérité** : Basse
- **Axe** : SEO
- **Fichier(s)** : `single.php` (absence)
- **Description** : Pas de JSON-LD pour `MusicPlaylist` / `MusicRecording` / `MusicGroup`. Pour un webzine musical, c'est une opportunité ratée (Google peut afficher des rich results pour les playlists).
- **Impact** : Pas de rich snippets, visibilité Google sub-optimale pour le secteur musique.
- **Recommandation** : Ajouter dans `single.php` un JSON-LD `MusicPlaylist` avec les tracks de l'ACF tracklist en `track` (`MusicRecording[]`).

### [OTHER-007] Aucun monitoring d'erreurs (PHP ou JS)
- **Sévérité** : Basse
- **Axe** : Observabilité
- **Fichier(s)** : N/A
- **Description** : Pas de Sentry, Bugsnag, Rollbar, ni même `error_log()` structuré. `console.log` actifs (cf. PERF-013) sont du diagnostic dev, pas du monitoring.
- **Impact** : Bugs prod non détectés (sauf signalement utilisateur).
- **Recommandation** : Considérer Sentry (free tier généreux) ou un simple `error_log` PHP redirigé vers un fichier monitoré par l'hébergeur.
- **Statut** : Reporté infrastructure (Phase 6 prep). Le monitoring de prod est par essence une couche infrastructure (intégration Sentry / Bugsnag / Rollbar avec credentials secrets, ou redirection `error_log` PHP côté hébergeur). À configurer lors d'une session ops/déploiement dédiée. Le code thème n'a aujourd'hui aucun emplacement où une intégration Sentry serait critique (les 2 endpoints REST custom `social/v2/likes` et `lamixtape/v1/posts` sont défensifs et ne lèvent que des `WP_Error` typés).

### [OTHER-008] Appel `fbq('track', 'Search')` orphelin (Pixel Facebook retiré)
- **Sévérité** : Basse
- **Axe** : Qualité
- **Fichier(s)** : `search.php:52`
- **Description** : `<script>fbq('track', 'Search');</script>` est appelé sans que le snippet Facebook Pixel soit chargé (ni dans le thème, ni dans aucun plugin trouvé). Confirmé par le contexte : pixel retiré historiquement, résidu orphelin.
- **Impact** : Erreur JS silencieuse en console (`Uncaught ReferenceError: fbq is not defined`) à chaque recherche.
- **Recommandation** : Supprimer la ligne 52 de `search.php`.
- **Statut** : Résolu Phase 1 (`d1cbe33` `<script>fbq('track','Search');</script>` supprimé de `search.php`. Pixel Facebook retiré historiquement, résidu orphelin confirmé).

---

## <a id="business"></a>Décisions business — résolues

### [Q9] Suppression du module commentaires
- **Statut** : Résolu Phase 2.5 (`9f24105` templates + `3cb6aad` callbacks/hooks + `50d490d` CSS/enqueue + `339330b` BDD cleanup doc + `d844b33` closure)
- **Décision** : Suppression définitive complète (Q1 = suppression code intégrale, Q2 = suppression BDD irréversible via WP-CLI, Q3 = badge 💬 supprimé pour cohérence UI, Q4 = pas d'annonce utilisateurs).
- **Périmètre traité** :
  - Code thème : `comments.php` supprimé (template orphelin), bouton 💬 + écosystème Bootstrap collapse / multi-collapse / `id=image|comments` retirés de `single.php` (Option C — image figée en HTML/CSS pur, plus aucun toggle JS BS4), 3 callbacks `lmt_comment_*` + leurs `add_filter` supprimés de `functions.php`, `'comment-form'` et `'comment-list'` retirés du tableau `add_theme_support('html5', ...)`, fichier `css/comment-form.css` (60 l.) supprimé, entrée enqueue `lmt-comment-form` retirée du map `$theme_css`.
  - BDD : `wp_comments` / `wp_commentmeta` confirmées vides post-suppression (Local n'avait jamais eu de commentaires), `comment_status` / `ping_status` forcés à `closed` sur tous les posts existants (~370+) via boucle bash, `default_comment_status` / `default_ping_status` confirmés à `closed` (déjà fixés par config Local antérieure).
  - Documentation : `_docs/phase-2.5-bdd-cleanup.md` posée pour traçabilité de la séquence WP-CLI (à répliquer en prod le jour du déploiement).
- **Diagnostic critique posé en 2.5.0** : le module commentaires côté **affichage** était déjà mort dans le thème — aucun appel à `comments_template()` ni `comment_form()` nulle part. Les filtres `comment_form_default_fields` / `comment_form_field_comment` ne firent jamais. L'altération visuelle effective de Phase 2.5 s'est donc limitée à : disparition du bouton 💬 sur `single.php` + disparition de la sidebar statique "Comments are now closed.". Pattern Phase 1 confirmé (*"decluttering reveals what was always there"*).
- **Backup pré-suppression** : conservé hors repo, cf. `_docs/phase-2.5-bdd-cleanup.md` pour le chemin absolu et la taille.

---

## 8. Synthèse chiffrée

### Findings par sévérité

| Sévérité | Nombre |
|---|:-:|
| **Critique** | 5 |
| **Haute** | 20 |
| **Moyenne** | 28 |
| **Basse** | 19 |
| **TOTAL** | **72** |

> Note : 70 findings dans l'audit initial (28 avril 2026), +2 findings ajoutés en Phase 1 (`QC-NEW-001` warning `WP_POST_REVISIONS`, `QC-NEW-002` smooth scroll SyntaxError). Tout finding ajouté post-audit utilise le suffixe `-NEW-NNN`.

### Findings par axe

| Axe | Critique | Haute | Moyenne | Basse | **Total** |
|---|:-:|:-:|:-:|:-:|:-:|
| Sécurité | 2 | 1 | 3 | 3 | **9** |
| Performance | 2 | 4 | 4 | 4 | **14** |
| Accessibilité | 0 | 4 | 5 | 2 | **11** |
| Qualité code | 1 | 4 | 7 | 4 | **16** |
| WP best practices | 0 | 3 | 4 | 2 | **9** |
| Migration Tailwind | 0 | 2 | 3 | 0 | **5** |
| Autres (SEO/RGPD/Obs) | 0 | 2 | 2 | 4 | **8** |
| **TOTAL** | **5** | **20** | **28** | **19** | **72** |

### Top 5 findings critiques (à régler avant tout autre travail)

1. **QC-001** — Repo non versionné (init git impératif).
2. **SEC-001** — Endpoint REST `likes` ouvert sans `permission_callback`/nonce.
3. **SEC-002** — Callback `social__dislike` non défini → 500.
4. **PERF-001** — Home charge 360+ articles d'un coup.
5. **PERF-002** — `single.php` query sur 1 000 000 posts filtrés par date.

### Verrous bloquants identifiés

- **Aucun versioning git** (QC-001) — bloque tout refacto sécurisé.
- **Aucun build tool** — choix Tailwind v4 + CLI standalone permet de ne pas en introduire.
- **Aucun staging** — impose un refacto itératif et testable en local.
- **Plugins custom non audités** (`chckr-yt`, `allow-multiple-accounts`) — phase 2.
- **Décision business pendante sur le CPT `mixtape`** — impacte la stratégie de migration BDD à terme.
