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

### [SEC-004] `cf_search_where` modifie la clause SQL via `preg_replace` sans audit du résultat
- **Sévérité** : Moyenne
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php:23-32`
- **Description** : Le filtre `posts_where` réécrit la clause SQL via une regex pour ajouter une condition `OR (postmeta.meta_value LIKE ...)`. Le `$1` capturé est directement réinjecté dans la chaîne. WP nettoie la requête en amont, mais ce pattern (réécriture SQL par regex) est fragile : le moindre changement de moteur SQL côté WP casse le filtre, et toute future modification du filtre peut introduire une injection.
- **Impact** : Code fragile + surface d'attaque latente (médiocre, pas exploitable en l'état).
- **Recommandation** : Remplacer par une **meta_query** native sur le `pre_get_posts` ou par un index plein-texte sur `postmeta.meta_value`. Voir le pattern documenté par WP Engine / Yoast pour étendre `s=` aux postmeta.

### [SEC-005] Aucune vérification de capability sur les actions REST
- **Sévérité** : Moyenne
- **Axe** : Sécurité
- **Fichier(s)** : `functions.php:276-298`
- **Description** : Les endpoints REST `likes/dislikes` ne contrôlent aucune capability. C'est un choix volontaire (anonymes), mais aucun garde-fou (nonce, transient, captcha) ne vient compenser.
- **Impact** : Cf. SEC-001 — pollution facile des métriques.
- **Recommandation** : Au minimum, exiger un nonce REST `X-WP-Nonce` envoyé par le JS, et limiter les requêtes à 1 par IP par heure par post via transient.

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

### [SEC-009] iframes YouTube créées sans `referrerpolicy` ni `sandbox`
- **Sévérité** : Basse
- **Axe** : Sécurité
- **Fichier(s)** : `player.php:121-133` (instanciation `YT.Player`)
- **Description** : L'iframe YouTube est créée par l'API YT.Player qui ne pose pas par défaut de `referrerpolicy` ni de `sandbox`. Le referrer Lamixtape fuit vers Google.
- **Impact** : Faible (analytics tiers), mais améliorable.
- **Recommandation** : Après `youtubePlayer = new YT.Player(...)`, manipuler l'iframe via `youtubePlayer.getIframe().setAttribute('referrerpolicy', 'no-referrer-when-downgrade')`. Pour `sandbox`, attention : rompt l'API JS YouTube.

---

## <a id="performance"></a>2. Performance

### [PERF-001] `index.php` charge **toutes** les playlists publiées d'un coup
- **Sévérité** : Critique
- **Axe** : Performance
- **Fichier(s)** : `index.php:38-41`
- **Description** : `new WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>-1])` rend l'intégralité du catalogue (≥ 360 mixtapes d'après le copy de la home) en HTML, dans une `<section>` unique, sans pagination, sans virtualisation, sans lazy.
- **Impact** : LCP catastrophique sur la home, payload HTML > 200 Ko, parsing CSS/DOM long sur mobile, INP dégradé. Croît linéairement avec la BDD.
- **Recommandation** : Paginer (50/page) avec `paginate_links`, OU implémenter un défilement infini AJAX (REST API custom + intersection observer), OU charger 30 articles puis "Load more" (pattern déjà suggéré par le nom `loadmore_enqueue` — fonctionnalité jamais finie).

### [PERF-002] `single.php` exécute une `WP_Query` sur 1 000 000 posts filtrés par date
- **Sévérité** : Critique
- **Axe** : Performance
- **Fichier(s)** : `single.php:99-111`
- **Description** : Le bloc "anciennes mixtapes" sous l'article courant utilise `posts_per_page=1000000` + un filtre `posts_where` qui injecte une comparaison `post_date < '...'`. Effets : (1) `OFFSET 0 LIMIT 1000000` dans la requête, (2) load complet en mémoire de tous les posts antérieurs à chaque vue de mixtape.
- **Impact** : TTFB qui croît avec la BDD, RAM PHP saturée sur les mixtapes anciennes (peu d'antérieurs) vs. récentes (300+ antérieurs). À terme : timeout PHP ou erreur 500.
- **Recommandation** : Limiter à 20-30 entrées avec `posts_per_page => 30` et `paginate_links`. Mieux : remplacer par `get_adjacent_post()` côté navigation, et déplacer la liste complète sur la home (avec pagination). Supprimer entièrement le filtre `posts_where` global (cf. PERF-009).

### [PERF-003] Bootstrap CSS chargé via `@import` dans `style.css`
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `style.css:11-13`
- **Description** : `@import url(https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css);` dans `style.css`. Les `@import` CSS sont **séquentiels** : le navigateur ne télécharge `bootstrap.min.css` qu'après avoir parsé `style.css`. Idem pour MediaElement CSS et la font Outfit. Multiplie le critical path.
- **Impact** : First Contentful Paint dégradé, render-blocking en cascade.
- **Recommandation** : Enqueuer chaque dépendance CSS via `wp_enqueue_style()` (handles distincts, dépendances explicites). Préférer auto-hébergement de Bootstrap (et MediaElement) dans `assets/vendor/`.

### [PERF-004] jQuery chargée deux fois (CDN dans `<head>` + dépendance enqueue)
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `header.php:8` + `functions.php:261`
- **Description** : `<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>` est en dur dans `header.php`, et `wp_enqueue_script('ajax-script', ..., array('jquery'), ...)` déclare `jquery` comme dépendance — WP enqueue alors **sa propre** jQuery bundlée. Résultat : deux versions de jQuery chargées, conflits potentiels (`$.fn.mediaelementplayer` peut viser une version, le code utilisateur l'autre).
- **Impact** : ~90 Ko inutiles, conflits de plugins jQuery, comportement non-déterministe.
- **Recommandation** : Supprimer la balise CDN dans `header.php`. Si une version spécifique est requise, `wp_deregister_script('jquery')` puis `wp_register_script('jquery', '...', [], '3.6.0', true)` dans un hook `wp_enqueue_scripts` à priorité basse. Mieux : viser le retrait progressif de jQuery.

### [PERF-005] Multiples `WP_Query` aléatoires (`orderby => rand`) par page
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `header.php:62` ; `index.php:21` ; `single.php:83` ; `404.php:9`
- **Description** : 4 endroits exécutent `new WP_Query(['orderby' => 'rand', 'posts_per_page' => 1])`. `ORDER BY RAND()` MySQL est **O(n)** sur la table `wp_posts` complète : sur 360+ posts c'est encore tolérable, sur 1 000+ c'est lourd. Et la home en exécute **2** (header + index), single en exécute 2 aussi (header + bouton random).
- **Impact** : Charge BDD inutile, TTFB augmenté.
- **Recommandation** : Cacher l'ID aléatoire en transient courte durée (`set_transient('lmt_random_post', $id, 5 * MINUTE_IN_SECONDS)`) ou pré-calculer un pool de 50 IDs aléatoires en cache. Variante : sélectionner `MAX(ID)`, tirer un random PHP, refaire un `get_post()`.

### [PERF-006] `cf_search_join` LEFT JOIN systématique sur `postmeta`
- **Sévérité** : Haute
- **Axe** : Performance
- **Fichier(s)** : `functions.php:13-19`
- **Description** : Toute recherche frontend déclenche un `LEFT JOIN postmeta`. La table `postmeta` est dénormalisée et **non-indexée** sur `meta_value` (par défaut WP). Sur 360 posts × N champs ACF, c'est OK ; à 10k+ ça devient catastrophique.
- **Impact** : Recherches lentes (>1s) à mesure que la BDD grossit.
- **Recommandation** : Limiter aux meta-keys utiles (filter `WHERE meta_key IN ('tracklist_%_track', ...)`), ou indexer `wp_postmeta(meta_value(191))`, ou migrer vers `WP_Query` standard avec un index plein-texte (`MATCH ... AGAINST`).

### [PERF-007] `category.php` sans pagination (`posts_per_page => -1`)
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `category.php:21-29`
- **Description** : `posts_per_page => -1` charge tous les posts de la catégorie. La pagination est même *codée puis commentée* (`category.php:64-67`). Une catégorie populaire (ex. "House", "Hip-hop") peut contenir > 100 posts.
- **Impact** : LCP dégradé sur les pages catégories populaires.
- **Recommandation** : Décommenter la pagination, fixer `posts_per_page => 30`, ou laisser `-1` mais avec un cache complet de la page (page cache).

### [PERF-008] `guests.php` exécute une `WP_Query` par utilisateur (N+1)
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `guests.php:30-34`
- **Description** : Pour chaque auteur listé, une `WP_Query('author=ID&posts_per_page=-1')` est exécutée pour afficher ses titres. Avec 50 auteurs × 7 mixtapes en moyenne, c'est 50 requêtes BDD séquentielles juste pour cette page.
- **Impact** : TTFB > 1s sur la page Guests, scaling linéaire avec le nombre d'auteurs.
- **Recommandation** : Une seule `get_posts(['posts_per_page' => -1, 'post_status' => 'publish'])` puis grouper en PHP par `post_author`. Ou utiliser un cache full-page sur cette URL (rarement modifiée).

### [PERF-009] `filter_where` redéclarée à chaque rendu de `single.php`
- **Sévérité** : Moyenne
- **Axe** : Performance / Fiabilité
- **Fichier(s)** : `single.php:102-108`
- **Description** : `function filter_where($where = '') { ... }` est définie au scope global **dans le template**. Si une autre partie de l'app inclut `single.php` deux fois (peu probable mais possible via shortcodes/REST), PHP émet `Cannot redeclare filter_where()` → fatal. De plus, le filtre `posts_where` est ajouté juste avant la query et retiré juste après — si une exception se lève entre les deux, le filtre reste actif et pollue toutes les queries suivantes.
- **Impact** : Risque de fatal error, risque de pollution des queries.
- **Recommandation** : Déclarer la fonction dans `functions.php` avec un nom préfixé (`lmt_filter_where_before_date`), et utiliser une closure si possible. Idéalement, **supprimer ce mécanisme** au profit d'un `date_query` natif WP.

### [PERF-010] `style.css` ne sert qu'à `@import` 15 fichiers CSS séparés
- **Sévérité** : Moyenne
- **Axe** : Performance
- **Fichier(s)** : `style.css:11-29`
- **Description** : 15 `@import url(...)` locaux + 3 externes = **18 requêtes CSS séquentielles**. HTTP/2 multiplexe, mais l'`@import` CSS-dans-CSS est toujours sérialisé.
- **Impact** : Critical path CSS allongé.
- **Recommandation** : Concaténer en un seul `style.css` (ou en bundles thématiques chargés par template via `wp_enqueue_style`). Avec Tailwind v4, la migration produira un seul fichier CSS final.

### [PERF-011] Pas de `loading="lazy"`, pas de `srcset`/`sizes` sur les images
- **Sévérité** : Basse
- **Axe** : Performance
- **Fichier(s)** : `single.php:63` ; `header.php:30` (logo logique) ; `index.php:32` ; `404.php:23`
- **Description** : `the_post_thumbnail_url()` est utilisée seule (sans `the_post_thumbnail($size, $attrs)`), donc pas de `srcset` automatique WP. Aucune balise n'a `loading="lazy"` explicite (WP en pose certaines depuis 5.5 mais pas systématiquement quand on construit le `<img>` à la main).
- **Impact** : Téléchargement d'images haute résolution sur mobile, bytes inutiles, LCP dégradé.
- **Recommandation** : Remplacer `the_post_thumbnail_url()` par `the_post_thumbnail('large', ['class' => 'img-fluid mt-4 illustration', 'loading' => 'lazy', 'alt' => ...])`. Forcer `loading="lazy"` sur toutes les `<img>` non-LCP.

### [PERF-012] Polices Google sans `preconnect` ni `font-display=swap` propre
- **Sévérité** : Basse
- **Axe** : Performance
- **Fichier(s)** : `style.css:13`
- **Description** : `@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap')` — l'URL contient bien `display=swap`, mais l'`@import` empêche les pre-resolve DNS. Pas de `<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>` non plus.
- **Impact** : FOIT/FOUT mal géré, ~100 ms perdus sur la connexion DNS+TLS.
- **Recommandation** : Auto-héberger la police (variable font Outfit, ~50 Ko woff2), ou ajouter `preconnect` dans `header.php`.

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

---

## <a id="a11y"></a>3. Accessibilité (WCAG 2.1 AA)

### [A11Y-001] Focus visible désactivé globalement
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `css/general.css:17-21,23-28`
- **Description** : `.btn:focus, button:focus, input:focus, select:focus, textarea:focus { outline: 0!important; box-shadow: none!important; }` — supprime tout indicateur de focus clavier sans aucun fallback visuel.
- **Impact** : **Violation WCAG 2.4.7 (niveau AA)**. Le site est inutilisable au clavier (impossible de savoir où on est).
- **Recommandation** : Supprimer ces règles. Définir un focus style cohérent : `:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }`. À traiter en priorité.

### [A11Y-002] Liens factices `href="#" data-toggle="modal"` non focusables proprement
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php:71-72,73-74` ; `single.php:63,91,92` ; `index.php:16,29,32` ; `footer.php:18`
- **Description** : Multiples `<a href="#" data-toggle="modal" data-target="#donatemodal">` — si le JS Bootstrap échoue (CDN down, JS désactivé), le lien n'a aucun effet. Pas de `role="button"`, pas de `aria-haspopup="dialog"`, pas de `aria-controls`. Au clavier, Enter ne déclenche pas l'action sur certains navigateurs (selon le focus state).
- **Impact** : Modals (donation, contact, image) non activables au clavier. Violation WCAG 2.1.1 (niveau A).
- **Recommandation** : Remplacer `<a href="#">` par `<button type="button">` avec un handler JS explicite. Ajouter `aria-haspopup="dialog"` et `aria-controls="donatemodal"`. Cohérent avec la migration vers `<dialog>` HTML natif (cf. TW-002).

### [A11Y-003] Hiérarchie de titres confuse (`<h1>` dans la nav, `<h2>` partout ailleurs)
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php:30` ; `index.php:47` ; `single.php:6` ; `category.php:38` ; etc.
- **Description** : La navbar pose un `<h1>Lamixtape</h1>` sur **toutes** les pages. Les pages elles-mêmes n'ont pas de `<h1>` propre (single utilise `<h2>` pour le titre de la mixtape, category aussi). Pour un lecteur d'écran, chaque page commence par "Lamixtape" comme titre principal, le contenu réel est en `<h2>`.
- **Impact** : Violation WCAG 1.3.1 (niveau A) — structure sémantique incorrecte. Mauvais SEO secondaire.
- **Recommandation** : Garder le logo Lamixtape comme `<a><span>` (ou `<h1>` uniquement sur la home). Les templates posent leur propre `<h1>` (titre de la mixtape, "Genre : House", "Search: foo", "404 — Looks like you got lost").

### [A11Y-004] Pas de skip-link, pas de `<main>`, pas de landmarks
- **Sévérité** : Haute
- **Axe** : A11y
- **Fichier(s)** : `header.php` (ouverture body) ; `footer.php` (fermeture)
- **Description** : Aucun `<a class="skip-link" href="#main">` (lien d'évitement vers le contenu). Aucune balise `<main>` (le contenu vit dans des `<section>` / `<article>` à la racine du `<body>`). La `<nav>` est unique mais sans `aria-label`. Pas de `<footer>` global (le `<footer>` dans `single.php:147` est vide).
- **Impact** : Violation WCAG 2.4.1 (niveau A) — pas de bypass blocks. Violation WCAG 1.3.1.
- **Recommandation** : Ajouter `<a class="skip-link" href="#main">Aller au contenu</a>` en première ligne de body. Wrapper le contenu de chaque template dans `<main id="main" tabindex="-1">`. Ajouter `aria-label="Navigation principale"` sur `<nav>`.

### [A11Y-005] Attribut `alt=""` posé sur des `<a>` (HTML invalide)
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `index.php:54` ; `single.php:14` ; `search.php:40` ; `category.php:51`
- **Description** : `<a class="mr-1" href="..." alt="View all posts in ...">` — `alt` n'est pas un attribut valide sur `<a>`. C'est probablement une confusion avec `title`.
- **Impact** : Information ignorée par tous les lecteurs d'écran. HTML invalide.
- **Recommandation** : Remplacer `alt=` par `title=` (ou mieux : `aria-label=` si le texte du lien est insuffisant).

### [A11Y-006] Mobile menu overlay sans `aria-hidden`/focus trap
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `header.php:49-77` ; `js/main.js:100-138`
- **Description** : `#mobile-menu-overlay` est masqué via `display: none` mais sans `aria-hidden="true"` initial. À l'ouverture, aucune gestion du focus trap : la touche Tab peut sortir du menu vers la page derrière. ESC ferme (✓), mais pas de focus return sur le burger après fermeture.
- **Impact** : Lecteur d'écran annonce des liens cachés ; clavier perdu.
- **Recommandation** : Ajouter `aria-hidden="true"` initial. À l'ouverture, mettre `aria-hidden="false"` + `inert` sur le reste de la page + focus sur le bouton close. Au close, restaurer focus sur `#burger-btn`. Considérer le pattern "dialog modal" (rôle `dialog`, `aria-modal="true"`).

### [A11Y-007] Animations sans `prefers-reduced-motion`
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `css/general.css:64-78` (fade-in) ; `css/player.css:23-39` (marquee) ; `css/player.css:137-145` (slide-up)
- **Description** : Animations `fade-in`, `marquee` (titre du track qui défile), `player-slide-up` actives sans média query `prefers-reduced-motion: reduce`.
- **Impact** : Violation WCAG 2.3.3 (niveau AAA, mais good practice AA). Inconfort vestibulaire pour utilisateurs sensibles.
- **Recommandation** : Wrapper les `@keyframes` et `transition` dans `@media (prefers-reduced-motion: no-preference) { ... }`, ou ajouter `@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; } }`.

### [A11Y-008] Modals Bootstrap sans focus trap/role dialog WCAG
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `footer.php:1-44`
- **Description** : Les 2 modals (`#donatemodal`, `#contactmodal`) reposent intégralement sur le JS BS4 pour le focus trap. Bootstrap 4.4 gère partiellement ce trap, mais la pratique moderne est d'utiliser `<dialog>` HTML natif ou un composant dédié (Reach UI, Headless UI). `aria-labelledby` pointe sur l'ID du modal lui-même au lieu du `<h2>` titre.
- **Impact** : Comportement clavier non garanti hors BS4.
- **Recommandation** : Migrer vers `<dialog>` natif (`<dialog id="donatemodal"><h2 id="donatemodal-title">...</h2></dialog>`) avec `dialog.showModal()`. Ajustement `aria-labelledby="donatemodal-title"`.
- **Note d'observation (Phase 1)** : Chromium émet en console à l'ouverture d'un modal `Blocked aria-hidden on an element because its descendant retained focus.` Manifestation directe du finding : Bootstrap 4.4 pose `aria-hidden="true"` sur le modal pendant que le focus s'y trouve, et les navigateurs récents flaguent ce conflit. **Pas une régression Phase 1** — bug intrinsèque à BS4. Résolution attendue avec la migration `<dialog>` (Phase 5).

### [A11Y-009] Couleurs ACF `color` appliquées sans contrôle de contraste
- **Sévérité** : Moyenne
- **Axe** : A11y
- **Fichier(s)** : `single.php:2,118` ; `index.php:44` ; `category.php:34` ; `search.php:30`
- **Description** : Le champ ACF `color` est appliqué brut en `background-color` sur les `<article>` qui contiennent du texte blanc et des liens curator. Aucun garde-fou : un curator peut saisir `#FFFFFF`, `#FFE066`, etc., rendant le texte illisible.
- **Impact** : Violation WCAG 1.4.3 (niveau AA — contraste 4.5:1) potentielle.
- **Recommandation** : Soit fournir une palette restreinte côté ACF (champ `select` ou `color_picker` avec presets validés), soit calculer la luminance côté PHP et choisir blanc/noir comme couleur de texte automatiquement, soit overlay sombre semi-transparent.

### [A11Y-010] Comment form : labels manquants (placeholders only)
- **Sévérité** : Basse
- **Axe** : A11y
- **Fichier(s)** : `functions.php:204-240`
- **Description** : `my_update_comment_fields()` génère les `<input>` avec `placeholder="Name"` mais sans `<label for="...">`. Le placeholder n'est pas un substitut accessible.
- **Impact** : Violation WCAG 1.3.1 / 4.1.2.
- **Recommandation** : Ajouter `<label for="author" class="sr-only">Name</label>` (et idem email/url). Garder le placeholder pour l'usage visuel.

### [A11Y-011] Player : pas de `<label>` lié au seekbar
- **Sévérité** : Basse
- **Axe** : A11y
- **Fichier(s)** : `player.php:22`
- **Description** : `<input type="range" id="seekbar" aria-label="Seek">` — le label est minimal mais ne décrit pas la track en cours, et pas de `aria-valuetext` pour annoncer "01:23 sur 03:45".
- **Impact** : Lecteur d'écran annonce "Seek, 0 sur 100" — peu utile.
- **Recommandation** : Ajouter `aria-valuetext` mis à jour par JS (ex. `seekbar.setAttribute('aria-valuetext', '01:23 sur 03:45')`).

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

### [QC-003] Bloc "card mixtape" dupliqué 4 fois
- **Sévérité** : Haute
- **Axe** : Qualité
- **Fichier(s)** : `index.php:43-60` ; `single.php:118-134` ; `category.php:33-58` ; `search.php:30-46`
- **Description** : Le `<article style="background-color: ...">` avec le titre, l'icône highlight, les tags catégorie, le curator, est réécrit textuellement dans 4 templates avec micro-variations cosmétiques.
- **Impact** : Toute modification visuelle = 4 modifications à synchroniser. Source garantie de bugs.
- **Recommandation** : Extraire en `template-parts/card-mixtape.php`. Appel via `get_template_part('template-parts/card-mixtape')` dans la loop.

### [QC-004] Text-domain littéral `'text-domain'` (placeholder jamais remplacé)
- **Sévérité** : Haute
- **Axe** : Qualité / i18n
- **Fichier(s)** : Tous les templates et `functions.php` (~40 occurrences)
- **Description** : Tous les `__()/_e()/esc_html__()` utilisent le slug `'text-domain'` (placeholder de générateur), jamais remplacé par un slug réel. `load_theme_textdomain` n'est appelé nulle part. Aucun fichier `.pot`/`.po`/`.mo`.
- **Impact** : Le site n'est traduisible **dans aucune langue**. Les outils i18n WP (Loco, WPML) ne trouveront pas le text-domain.
- **Recommandation** : `find/replace` global `'text-domain'` → `'lamixtape'`. Ajouter dans `functions.php` : `add_action('after_setup_theme', function() { load_theme_textdomain('lamixtape', get_template_directory() . '/languages'); });`. Générer un `.pot` initial.

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

### [QC-007] Inline `<style>` et `<script>` dispersés dans les templates
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `header.php:34,36,51` ; `player.php:1-31` (multiples `style="..."`), `34-397` (script entier inline) ; `search.php:52` ; `single.php:22-24` (script `var postid`) ; `index.php:31` ; `guests.php:7,24` ; `explore.php:9` ; etc. (~26 occurrences `style="..."` au total)
- **Description** : Styles inline dispersés (≥ 26 occurrences `style="..."`), JS inline dans `player.php` (~360 lignes), `search.php` (`fbq('track')`), `single.php` (`var postid = ...`).
- **Impact** : Impossible à minifier, bundler, lint, ou versionner proprement. CSP `unsafe-inline` requis. Bundle Tailwind inutile si du CSS reste inline.
- **Recommandation** : Extraire le JS du player dans `js/player.js`, enqueuer conditionnellement. `var postid` → `wp_localize_script` propre. Styles inline → classes utilities (Tailwind ou CSS custom). Suppression du `fbq` (cf. OTHER-008).

### [QC-008] Aucun docblock, naming PHP incohérent, pas de namespace
- **Sévérité** : Moyenne
- **Axe** : Qualité
- **Fichier(s)** : `functions.php` (toutes fonctions)
- **Description** : Coexistence de styles `cf_*`, `revcon_*`, `wpb_*`, `tape_*`, `social__*` (double underscore), `loadmore_*`, `SearchFilter` (PascalCase). Pas un seul `/** @param ... */`.
- **Impact** : Onboarding impossible, IDE incapable d'aider, refacto à risque.
- **Recommandation** : Préfixer toutes les fonctions du thème par `lmt_*`. Ajouter docblocks PHPDoc. Si OOP : namespace `Lamixtape\Theme`.

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

### [WP-006] `WP_POST_REVISIONS` défini dans le thème (cf. PERF-014)
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:137`
- **Description** : Cf. PERF-014.
- **Impact** : Cf. PERF-014.
- **Recommandation** : Cf. PERF-014.

### [WP-007] `setup_postdata` sur boucle custom sans `wp_reset_postdata` propre
- **Sévérité** : Moyenne
- **Axe** : WP best practices
- **Fichier(s)** : `single.php:114-117`
- **Description** : Boucle `foreach ($pageposts as $post) { setup_postdata($post); ... }` — pas de `wp_reset_postdata()` à la fin (la boucle se termine sans reset, ce qui peut polluer le `$post` global pour le code suivant comme `get_footer()`).
- **Impact** : Données de post incorrectes dans le footer ou les widgets après la boucle.
- **Recommandation** : Ajouter `wp_reset_postdata();` après `endforeach;`.

### [WP-008] Pas de `add_theme_support('responsive-embeds')`
- **Sévérité** : Basse
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php` (absence)
- **Description** : Le thème intègre des iframes YouTube via le player ; sans `responsive-embeds`, les embeds Gutenberg ne sont pas responsive automatiquement.
- **Impact** : Embed Gutenberg dans le contenu = pas responsive.
- **Recommandation** : Ajouter (cf. WP-003).

### [WP-009] `wp_change_search_url` ne nettoie pas le query string
- **Sévérité** : Basse
- **Axe** : WP best practices
- **Fichier(s)** : `functions.php:56-62`
- **Description** : `wp_safe_redirect( get_home_url(...) . urlencode( get_query_var('s') ) )` — si `s=` contient déjà des caractères URL-encodés (ex. `%20`), le `urlencode` re-encode (`%2520`), brouillant l'URL.
- **Impact** : URL de recherche cassée pour des termes complexes.
- **Recommandation** : Utiliser `rawurlencode()` (ou laisser WP gérer via `add_query_arg`).

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

### [TW-003] Menu mobile déjà custom mais couplé visuellement aux classes BS
- **Sévérité** : Moyenne
- **Axe** : Tailwind
- **Fichier(s)** : `header.php:49-77` ; `js/main.js:100-138`
- **Description** : Le mobile menu est implémenté en jQuery custom (pas en BS Collapse), donc fonctionnellement indépendant. Mais visuellement il utilise `container`, `list-inline-item`, etc.
- **Impact** : Faible — point d'entrée facile pour la migration.
- **Recommandation** : Bonne candidate pour le **premier template migré** (avec `404.php` et `text.php`).

### [TW-004] Player utilise `embed-responsive embed-responsive-16by9` BS4
- **Sévérité** : Moyenne
- **Axe** : Tailwind
- **Fichier(s)** : `single.php:73-75` ; `player.css`
- **Description** : Wrapper `embed-responsive embed-responsive-16by9` pour l'iframe YouTube. À remplacer par `aspect-video` (Tailwind v4) ou `aspect-ratio: 16/9` CSS natif.
- **Impact** : Moyen — fonctionnel à migrer, sans casse.
- **Recommandation** : Remplacer par `<div class="aspect-video relative w-full">`.

### [TW-005] `mediaelementplayer.css` chargé sans usage visuel
- **Sévérité** : Moyenne
- **Axe** : Tailwind / Performance
- **Fichier(s)** : `style.css:12` ; `player.php:90-115` (init `features: []`)
- **Description** : MediaElement.js est initialisé avec `features: []` (aucun contrôle natif de MediaElement affiché — tout est custom dans `#footer-player`). Pourtant `mediaelementplayer.css` est chargée (~30 KB).
- **Impact** : 30 KB inutiles téléchargés/parsés.
- **Recommandation** : Désactiver l'`@import` MediaElement CSS dans `style.css`. Tester que le player fonctionne (les contrôles custom sont gérés par notre JS).

---

## <a id="autres"></a>7. Autres (SEO, RGPD, observabilité)

### [OTHER-001] Umami Analytics chargé sans bandeau de consentement formel
- **Sévérité** : Haute
- **Axe** : RGPD
- **Fichier(s)** : `analytics.php:2`
- **Description** : Umami (`cloud.umami.is`) est un analytics anonyme, pas de cookies tiers, pas d'ID utilisateur — la CNIL le considère généralement comme "exempté" du consentement (cf. doctrine 2022). MAIS : aucune mention dans une politique de confidentialité, aucun bandeau, aucun lien `Cookie policy` en footer. La conformité dépend de la documentation côté contenu, pas du code.
- **Impact** : Risque RGPD modéré (faible amende potentielle). Manque de transparence.
- **Recommandation** : Confirmer auprès du legal/CNIL que Umami Cloud (et non pas le self-hosted) bénéficie bien de l'exemption pour le périmètre Lamixtape. Ajouter une page `legal-notice` (déjà liée dans le menu mobile, header.php:74) qui mentionne Umami. Sinon, ajouter un bandeau de consentement.

### [OTHER-002] `add_theme_support('title-tag')` désactivé → `<title>` non géré par WP
- **Sévérité** : Haute
- **Axe** : SEO
- **Fichier(s)** : `header.php` (absence de `<title>`) ; `functions.php` (absence de `add_theme_support`)
- **Description** : Pas de `<title>` dans `<head>` de `header.php`, et pas d'`add_theme_support('title-tag')` qui laisserait WP générer le titre via `wp_head`. Rank Math compense en injectant un `<title>` via `wp_head`, mais si Rank Math est désactivé (debug, conflit), **aucun `<title>` n'est généré**.
- **Impact** : Dépendance forte à Rank Math, fragilité SEO.
- **Recommandation** : Ajouter `add_theme_support('title-tag')` (cf. WP-003). Rank Math reste compatible et continue de surcharger le `<title>` quand activé.

### [OTHER-003] Aucune Open Graph / Twitter Card côté thème
- **Sévérité** : Moyenne
- **Axe** : SEO / Social
- **Fichier(s)** : `header.php` (absence)
- **Description** : Pas de `<meta property="og:title">`, `<meta property="og:image">`, `<meta name="twitter:card">` dans le `<head>`. Délégué à Rank Math.
- **Impact** : Cf. OTHER-002 — dépendance à Rank Math. Si Rank Math échoue, partages sociaux moches.
- **Recommandation** : Tester avec Rank Math désactivé et confirmer l'OG. Sinon, fallback dans `header.php` (post thumbnail comme `og:image`).

### [OTHER-004] Favicons et webmanifest référencés en `/...` sans garantie d'existence
- **Sévérité** : Moyenne
- **Axe** : SEO / UX
- **Fichier(s)** : `header.php:11-15`
- **Description** : `<link rel="icon" href="/favicon-32x32.png">` etc. — les chemins sont absolus à la racine du site, pas dans le thème. Si les fichiers ne sont pas à la racine du document root (probablement dans `/wp-content/uploads/` ou ailleurs), 404 sur favicon.
- **Impact** : Favicon manquant, manifest cassé, mauvaise PWA-ability.
- **Recommandation** : Vérifier que `favicon-32x32.png`, `apple-touch-icon.png`, `site.webmanifest`, `safari-pinned-tab.svg` existent à la racine du site. Sinon, déplacer dans `img/` du thème et corriger les chemins (`<?php echo esc_url(get_template_directory_uri()); ?>/img/favicon-32x32.png`).

### [OTHER-005] Pas de robots.txt / sitemap.xml côté thème
- **Sévérité** : Basse
- **Axe** : SEO
- **Fichier(s)** : N/A
- **Description** : Délégué à Rank Math (qui gère bien les sitemaps). Mention pour traçabilité.
- **Impact** : Aucun si Rank Math actif. À confirmer.
- **Recommandation** : Vérifier que `https://lamixtape.fr/sitemap_index.xml` répond bien.

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

### [OTHER-008] Appel `fbq('track', 'Search')` orphelin (Pixel Facebook retiré)
- **Sévérité** : Basse
- **Axe** : Qualité
- **Fichier(s)** : `search.php:52`
- **Description** : `<script>fbq('track', 'Search');</script>` est appelé sans que le snippet Facebook Pixel soit chargé (ni dans le thème, ni dans aucun plugin trouvé). Confirmé par le contexte : pixel retiré historiquement, résidu orphelin.
- **Impact** : Erreur JS silencieuse en console (`Uncaught ReferenceError: fbq is not defined`) à chaque recherche.
- **Recommandation** : Supprimer la ligne 52 de `search.php`.
- **Statut** : Résolu Phase 1 (`d1cbe33` `<script>fbq('track','Search');</script>` supprimé de `search.php`. Pixel Facebook retiré historiquement, résidu orphelin confirmé).

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
