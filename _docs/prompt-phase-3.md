# Prompt Claude Code — Phase 3 : Sécurité & performance bloquante

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. Phases 0/1/2/2.5 closes (~55 commits poussés sur `origin/main`, 31 findings résolus + Q9 décision business).

---

## Règle transversale (rappel)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.**
>
> **Exception explicite Phase 3** : l'introduction de l'infinite scroll par lots de 30 (PERF-001/002/007) modifie le comportement de chargement initial des pages catalogue (home, single previous-mixtapes, category) **sans modifier le résultat final perçu** — l'utilisateur voit toujours toutes les mixtapes en scrollant. Cette altération comportementale (pas visuelle au sens strict) est explicitement validée par l'utilisateur.
>
> Toute autre altération visuelle reste interdite sans validation.

## Contexte

`CLAUDE.md` et `_docs/AUDIT.md` à jour après Phase 2.5. PHP 8.2 confirmé en prod. Workflow git établi : commit-par-commit, push immédiat post-commit.

Décisions actées par l'utilisateur :

| # | Décision | Choix |
|---|---|---|
| Q1 | Stratégie pagination catalogue | **Infinite scroll par lots de 30** (illusion "tout afficher" sans charger 370+ posts d'un coup) |
| Q2 | Lazy loading images | `loading="lazy"` HTML natif (support universel sur navigateurs modernes) |
| Q3 | WebP conversion | **Reportée** — installation du plugin Performance Lab côté infrastructure (hors scope thème). PERF-005 reste tracé dans AUDIT.md comme "infrastructure todo". |
| Q4 | Mode | **Marathon** (pas de validation intermédiaire entre sous-étapes, tests à la fin) |

## Objectif Phase 3

Trois axes structurants, simultanés en marathon :

### Axe A — Pagination catalogue (PERF-001, PERF-002, PERF-007)
- Infinite scroll par lots de 30 sur `index.php` (home), `single.php` (anciennes mixtapes), `category.php`
- AJAX endpoint custom REST namespace `lamixtape/v1` (cohérent avec `social/v2` existant)
- IntersectionObserver côté JS (pas de scroll listener, perf moderne)
- Préservation totale du rendu visuel des cards mixtape (template-parts/card-mixtape.php créé Phase 2)

### Axe B — Performance images & assets (PERF-003, PERF-004, PERF-006, PERF-010, PERF-011, PERF-012, PERF-014)
- `loading="lazy"` natif sur toutes les `<img>` non-critiques (hors logo header)
- `srcset`/`sizes` via `wp_get_attachment_image()` ou helpers natifs WP
- Transients sur les WP_Query random (header.php aléatoire, etc.)
- Object cache utilisable (clés cohérentes via `wp_cache_*`)
- Préchargement Outfit en `<link rel="preload">` (PERF-014 si encore applicable post-Phase-1)
- Defer/async sur scripts non-critiques (PERF-011/012)

### Axe C — Sécurité durcissement (SEC-004, SEC-005, SEC-008, SEC-009)
- Renforcement des capabilities sur tous les endpoints REST custom
- Sanitization stricte des paramètres GET/POST des nouveaux endpoints AJAX (pagination)
- Rate-limit sur endpoint pagination (anti-scraping)
- Headers de sécurité côté thème (`X-Content-Type-Options`, `Referrer-Policy`) — à confirmer s'ils ne sont pas posés ailleurs

À l'issue de la phase :
- 0 page ne charge plus 370+ posts d'un coup au load initial
- Toutes les images du flux ont `loading="lazy"` + `srcset` cohérent
- Les queries aléatoires sont cachées (transients ou object cache)
- Tous les endpoints REST custom ont permission_callback + nonce + sanitization
- 8 templates visuellement identiques aux captures `_docs/captures-post-phase-2.5/` (modulo l'apparition progressive des cards en infinite scroll)

**Findings explicitement HORS périmètre Phase 3** :
- **PERF-005 (WebP)** → reporté infrastructure (plugin Performance Lab)
- **A11y, Tailwind, OTHER (RGPD/SEO/monitoring)** → Phases 4-6
- **Plugins custom** (`chckr-yt`, `allow-multiple-accounts`) → hors périmètre thème

## Décisions structurantes (déjà actées)

| ID | Décision | Application |
|---|---|---|
| **D1** | Lots de 30 par scroll | Constante `LMT_INFINITE_SCROLL_BATCH_SIZE = 30` dans `inc/queries.php` ou équivalent |
| **D2** | IntersectionObserver natif | Pas de scroll listener, pas de jQuery scroll, pas de plugin tiers |
| **D3** | Endpoint REST custom `lamixtape/v1/posts` | Namespace dédié, séparé de `social/v2` (sémantique différente) |
| **D4** | Skeleton loader pendant chargement AJAX | Card placeholder identique au markup card-mixtape pour éviter layout shift |
| **D5** | Préservation comportement actuel "scroll bottom = on continue" | Pas de bouton "Load more" cliquable, pas de pagination classique |
| **D6** | Lazy loading natif HTML | `loading="lazy"` + `decoding="async"` |
| **D7** | Performance Lab plugin = hors périmètre thème | Pas d'intervention dans le code thème pour WebP |
| **D8** | Caching transients | Durée par défaut 1h pour queries random, 24h pour structures lentes type curators count |

---

## Étape 3.0 — Préparation

### 3.0.1 Lecture obligatoire

Lire `CLAUDE.md`, `_docs/AUDIT.md`, et `_docs/prompt-phase-3.md` (ce prompt).

### 3.0.2 Captures de référence

`_docs/captures-post-phase-2.5/` SONT les captures pre-Phase-3 (Phase 2.5 close avec 0 diff visuel sur 7 templates + altération validée sur single).

### 3.0.3 Confirmation utilisateur

Confirmer en 5 lignes :
- Périmètre Phase 3 (3 axes A/B/C)
- Décisions D1-D8 intégrées
- Mode marathon activé (pas de validation intermédiaire entre sous-étapes)
- Discipline diagnostic-d'abord MAINTENUE (cf. exception critique ci-dessous)
- WebP reporté infrastructure (Q3)

### 3.0.4 Décisions pré-tranchées (D-MARATHON-3)

| ID | Cas | Décision |
|---|---|---|
| **D-M-3.1** | Si une fonction PHP nécessite renommage `lmt_*` non détecté en Phase 2 | Applique automatiquement, ne demande pas |
| **D-M-3.2** | Si une image n'a pas d'attribut `alt` | Ajoute `alt=""` (image décorative) par défaut. Pour les `alt` significatifs : laisse vide à remplir manuellement, marque dans le commit. |
| **D-M-3.3** | Conflits `wp_enqueue_script` ordre | Préserver l'ordre actuel des handles, n'introduire des deps explicites que si bug détecté |
| **D-M-3.4** | Test de pagination cassé (offset incorrect) | Diagnostic-d'abord, arrête et présente |
| **D-M-3.5** | Skeleton loader markup différent de card-mixtape | Suppose strict identique, ajuste si nécessaire après tests visuels |
| **D-M-3.6** | Endpoint REST `lamixtape/v1/posts` collision avec endpoint existant | Diagnostic-d'abord, arrête et présente |

### 3.0.5 EXCEPTION CRITIQUE (rappel)

Mode marathon NE LÈVE PAS la discipline diagnostic-d'abord.

Tu T'ARRÊTES si :
- Bug pré-existant qui apparaît
- Régression visible
- Erreur PHP inattendue
- Décision business non couverte

Pas de fix spéculatif.

---

## Axe A — Pagination catalogue (PERF-001, PERF-002, PERF-007)

### 3.A.1 Création de l'endpoint REST `lamixtape/v1/posts`

Dans `functions.php` (ou `inc/rest.php` à créer si tu veux séparer) :

```php
register_rest_route( 'lamixtape/v1', '/posts', array(
    'methods'             => WP_REST_Server::READABLE, // GET only
    'callback'            => 'lmt_rest_get_posts_paginated',
    'permission_callback' => '__return_true', // public read, mais avec rate-limit
    'args'                => array(
        'context'  => array(
            'required' => true,
            'enum'     => array( 'home', 'single_previous', 'category' ),
        ),
        'offset'   => array(
            'required'          => true,
            'type'              => 'integer',
            'minimum'           => 0,
            'sanitize_callback' => 'absint',
        ),
        'category' => array(
            'required'          => false,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ),
        'exclude'  => array(
            'required'          => false,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ),
    ),
) );
```

Le callback `lmt_rest_get_posts_paginated()` (dans `inc/queries.php` ou `inc/rest.php`) :
- Lit `context` et route vers la bonne query (home, single_previous, category)
- Réutilise `lmt_get_previous_mixtapes()`, `lmt_get_search_results()` etc. créées en Phase 2 quand pertinent
- Borne par `LMT_INFINITE_SCROLL_BATCH_SIZE` (= 30)
- Retourne un array de HTML rendu (réutilise `template-parts/card-mixtape.php` via `get_template_part`) + métadonnées (`has_more`, `next_offset`)
- Échappement strict de toute sortie (`wp_kses_post` si HTML, `esc_*` sinon)

### 3.A.2 Sécurisation de l'endpoint (cohérence SEC-001)

Même pattern que `social/v2/likes` (Phase 0) :
- Nonce REST vérifié dans le `permission_callback` (passer le nonce via `lmtData.nonce` déjà localisé)
- Rate-limit IP (transient `lmt_pagination_{hash_ip}`, 100 requêtes/heure max — bien plus permissif que likes vu le pattern d'usage)
- IP hashée via `wp_hash` pour conformité RGPD
- Validation stricte des `args` (déjà couvert par `validate_callback` + `sanitize_callback` ci-dessus)

### 3.A.3 Côté JS — IntersectionObserver

Nouveau fichier `js/infinite-scroll.js` :

```js
jQuery( function ( $ ) {
    'use strict';

    const $sentinel = $( '#lmt-infinite-sentinel' );
    if ( ! $sentinel.length ) return;

    const context = $sentinel.data( 'context' );
    const $container = $( '#lmt-mixtapes-container' );
    let offset = parseInt( $sentinel.data( 'initial-offset' ), 10 ) || 30;
    let loading = false;
    let hasMore = true;

    const loadMore = async () => {
        if ( loading || ! hasMore ) return;
        loading = true;

        // Skeleton loader
        const skeletons = Array( 6 ).fill( '<article class="lmt-card-skeleton"></article>' ).join( '' );
        $container.append( skeletons );

        try {
            const params = new URLSearchParams( {
                context,
                offset,
                _wpnonce: lmtData.nonce,
            } );
            // ajouter category / exclude si data-attributes présents
            if ( $sentinel.data( 'category' ) ) params.set( 'category', $sentinel.data( 'category' ) );
            if ( $sentinel.data( 'exclude' ) ) params.set( 'exclude', $sentinel.data( 'exclude' ) );

            const response = await fetch( `${lmtData.site_url}/wp-json/lamixtape/v1/posts?${params}`, {
                headers: { 'X-WP-Nonce': lmtData.nonce },
            } );

            if ( ! response.ok ) {
                console.error( '[infinite-scroll] HTTP', response.status );
                hasMore = false;
                return;
            }

            const data = await response.json();
            $container.find( '.lmt-card-skeleton' ).remove();
            $container.append( data.html );
            offset = data.next_offset;
            hasMore = data.has_more;
        } catch ( err ) {
            console.error( '[infinite-scroll] failed:', err );
            hasMore = false;
        } finally {
            loading = false;
        }
    };

    const observer = new IntersectionObserver( ( entries ) => {
        if ( entries[ 0 ].isIntersecting ) {
            loadMore();
        }
    }, { rootMargin: '400px' } );

    observer.observe( $sentinel.get( 0 ) );
});
```

Enqueue conditionnel dans `lmt_enqueue_assets()` :
```php
if ( is_home() || is_singular( 'post' ) || is_category() ) {
    wp_enqueue_script( 'lmt-infinite-scroll', $theme_uri . '/js/infinite-scroll.js', array( 'jquery' ), null, true );
}
```

### 3.A.4 Modifications templates

#### `index.php` (home)

- Conserver l'affichage des **30 premières** mixtapes au load initial (au lieu de toutes via `posts_per_page=-1`)
- Ajouter `<div id="lmt-mixtapes-container">` autour du `while ( have_posts() )` (ou du wrapper existant)
- Ajouter en fin de boucle : `<div id="lmt-infinite-sentinel" data-context="home" data-initial-offset="30"></div>`

#### `single.php` (anciennes mixtapes)

- `lmt_get_previous_mixtapes( get_the_ID() )` — limiter à 30 au load initial (modifier la signature : `lmt_get_previous_mixtapes( $current_post_id, $limit = 30 )`)
- Ajouter le sentinel : `<div id="lmt-infinite-sentinel" data-context="single_previous" data-initial-offset="30" data-exclude="<?php echo esc_attr( get_the_ID() ); ?>"></div>`
- **Retirer le commentaire `// PERF-002 tracked, pagination strategy in Phase 3`** posé en Phase 2

#### `category.php`

- Modifier `query_posts` ou la query principale pour `posts_per_page = 30` (au lieu de `-1` actuel)
- Ajouter le sentinel avec `data-context="category"` + `data-category="<?php echo esc_attr( get_queried_object_id() ); ?>"`
- **Retirer le commentaire `// PERF-007 tracked, pagination strategy in Phase 3`** s'il a été posé en Phase 2

### 3.A.5 Skeleton CSS

Ajouter dans `css/list-of-mixtapes.css` (ou créer `css/infinite-scroll.css` enqueued conditionnellement) :

```css
.lmt-card-skeleton {
    /* mêmes dimensions que .article-mixtape ou la classe utilisée par card-mixtape */
    background: linear-gradient(90deg, #2a2a2a 25%, #3a3a3a 50%, #2a2a2a 75%);
    background-size: 200% 100%;
    animation: lmt-skeleton-shimmer 1.5s infinite;
    border-radius: 4px;
    height: 200px; /* à adapter selon la card actuelle */
    margin-bottom: 16px;
}

@keyframes lmt-skeleton-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

@media (prefers-reduced-motion: reduce) {
    .lmt-card-skeleton {
        animation: none;
    }
}
```

À ajuster selon les dimensions réelles de la card-mixtape (vérifier dans le navigateur via DevTools).

### 3.A.6 Commits Axe A

| # | Commit | Findings |
|---|---|---|
| C1 | `feat(rest): add lamixtape/v1/posts endpoint with rate-limit and nonce (Axe A foundation)` | structure |
| C2 | `feat(home): infinite scroll on home (PERF-001)` | index.php + sentinel |
| C3 | `feat(single): infinite scroll on previous mixtapes (PERF-002)` | single.php + lmt_get_previous_mixtapes |
| C4 | `feat(category): infinite scroll on category archives (PERF-007)` | category.php |
| C5 | `feat(assets): add infinite-scroll.js + skeleton CSS` | js/infinite-scroll.js + skeleton CSS |

5 commits Axe A.

---

## Axe B — Performance images & assets (PERF-003, PERF-004, PERF-006, PERF-010, PERF-011, PERF-012, PERF-014)

### 3.B.1 Lazy loading natif (PERF-003)

Pour toutes les `<img>` du thème **hors logo header** :
- Ajouter `loading="lazy"` + `decoding="async"`
- Le logo header (LCP candidate) reste **sans** lazy loading (`loading="eager"` explicite ou pas d'attribut)

Vérification :
```bash
grep -rn "<img" --include="*.php" .
```

Lister chaque occurrence et appliquer `loading="lazy"` partout sauf logo header. Templates concernés : index.php, single.php, category.php, search.php, 404.php, explore.php, guests.php, text.php, header.php (logo seulement = pas de lazy), footer.php, template-parts/card-mixtape.php.

Commit : `perf(images): add native lazy loading + async decoding (PERF-003)`.

### 3.B.2 Srcset/sizes via helpers WP (PERF-004)

Remplacer les `<img src="..." />` en dur par `wp_get_attachment_image()` ou `the_post_thumbnail()` quand l'image vient d'un attachment WP. Bénéfice : srcset/sizes générés automatiquement par WP.

Cas typiques :
- Image de mise en avant des cards : `the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'decoding' => 'async' ) )` au lieu de `<img src="<?php echo get_the_post_thumbnail_url(); ?>" />`
- Image de l'article principal en single : `the_post_thumbnail( 'large', ... )`

Si certaines images sont stockées en ACF (champ image), utiliser :
```php
$image = get_field( 'cover' );
echo wp_get_attachment_image( $image['ID'], 'medium', false, array(
    'loading'  => 'lazy',
    'decoding' => 'async',
) );
```

Inventaire avant modif : grep `<img` + grep `get_field` ciblant les champs image.

Commit : `perf(images): use wp_get_attachment_image for srcset/sizes (PERF-004)`.

### 3.B.3 Transients sur queries random (PERF-006)

Si `header.php` (ou autre template) contient une `WP_Query` avec `orderby=rand`, l'envelopper dans un transient (durée 1h cf. D8) :

```php
$random_mixtape = get_transient( 'lmt_random_mixtape_header' );
if ( false === $random_mixtape ) {
    $random_mixtape = new WP_Query( array(
        'orderby'        => 'rand',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ) );
    set_transient( 'lmt_random_mixtape_header', $random_mixtape, HOUR_IN_SECONDS );
}
```

Inventaire : grep `orderby.*rand` dans tous les templates.

Note : `orderby=rand` + transient = la "random" devient la même pendant 1h pour un même cache hit. C'est acceptable (et c'est le comportement attendu d'un cache). Si tu veux du vrai random à chaque page load, le transient ne sert à rien — discuter avant.

Commit : `perf(queries): cache random WP_Query results in transients (PERF-006)`.

### 3.B.4 Object cache cohérent (PERF-010)

Vérifier si des queries lourdes sont réutilisées plusieurs fois dans la même requête HTTP. Si oui, cacher en mémoire via `wp_cache_get` / `wp_cache_set` avec un groupe `lamixtape` :

```php
$cached = wp_cache_get( 'curators_grouped', 'lamixtape' );
if ( false === $cached ) {
    $cached = lmt_get_posts_grouped_by_author();
    wp_cache_set( 'curators_grouped', $cached, 'lamixtape', HOUR_IN_SECONDS );
}
```

Cible primaire : `lmt_get_posts_grouped_by_author()` (créée Phase 2 pour PERF-008). Si appelée plusieurs fois → cache.

Commit : `perf(cache): use object cache for heavy grouped queries (PERF-010)`.

### 3.B.5 Preload Outfit (PERF-014 si applicable)

Vérifier si `assets/vendor/outfit/outfit.css` est déjà préchargé dans `<head>`. Sinon, ajouter dans `header.php` ou via filter `wp_resource_hints` :

```php
function lmt_preload_outfit_font( $urls, $relation_type ) {
    if ( 'preload' === $relation_type ) {
        $urls[] = array(
            'href' => get_template_directory_uri() . '/assets/vendor/outfit/outfit-latin.woff2',
            'as'   => 'font',
            'type' => 'font/woff2',
            'crossorigin' => 'anonymous',
        );
    }
    return $urls;
}
add_filter( 'wp_resource_hints', 'lmt_preload_outfit_font', 10, 2 );
```

Commit : `perf(fonts): preload Outfit woff2 in head (PERF-014)`.

### 3.B.6 Defer/async scripts non-critiques (PERF-011, PERF-012)

Audit des scripts enqueued dans `lmt_enqueue_assets()` :
- `lmt-main` : à garder normal (handler like)
- `lmt-player` : `defer` (chargé en footer déjà, defer renforce)
- `lmt-infinite-scroll` (nouveau) : `defer`
- `wp-mediaelement` : laisser tel quel (WP gère)
- Bootstrap bundle : laisser tel quel pour l'instant (Phase 4 va le supprimer)

Implémentation via filter `script_loader_tag` :

```php
function lmt_defer_scripts( $tag, $handle ) {
    $defer_handles = array( 'lmt-player', 'lmt-infinite-scroll' );
    if ( in_array( $handle, $defer_handles, true ) ) {
        return str_replace( ' src', ' defer src', $tag );
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'lmt_defer_scripts', 10, 2 );
```

Commit : `perf(scripts): defer non-critical scripts (PERF-011, PERF-012)`.

### 3.B.7 Commits Axe B

5-6 commits selon volume de modifications réelles.

---

## Axe C — Sécurité durcissement (SEC-004, SEC-005, SEC-008, SEC-009)

### 3.C.1 Audit des findings AUDIT.md

Avant toute action, **relire les findings SEC-004, SEC-005, SEC-008, SEC-009 dans AUDIT.md** pour comprendre exactement leur périmètre. Si certains se révèlent déjà résolus en Phase 1/2 (cleanup collateral), marque-les "Résolu Phase X" sans action code.

### 3.C.2 Application au cas par cas

Chaque finding traité en commit dédié :
- `fix(security): SEC-004 description courte`
- etc.

Si un finding nécessite une décision business → arrête et demande (exception critique).

### 3.C.3 Headers de sécurité côté thème

Si non déjà posés ailleurs (vérifier `.htaccess`, plugins de sécurité, hébergeur) :

```php
function lmt_send_security_headers() {
    if ( is_admin() ) return;
    header( 'X-Content-Type-Options: nosniff' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
}
add_action( 'send_headers', 'lmt_send_security_headers' );
```

**Attention** : si le site est derrière Cloudflare ou un autre CDN qui pose déjà ces headers, ne pas dupliquer (un browser peut afficher un warning). Vérifier avec `curl -I https://lamixtape.fr` avant de poser.

Commit : `feat(security): add basic security headers from theme (SEC-008?)`.

---

## Étape 3.10 — Closure Phase 3

### 3.10.1 Mise à jour `_docs/AUDIT.md`

Pour chaque finding fermé en Phase 3, ajouter `**Statut** : Résolu Phase 3 (SHA + ...)`. Findings concernés : PERF-001, PERF-002, PERF-003, PERF-004, PERF-006, PERF-007, PERF-010, PERF-011, PERF-012, PERF-014, SEC-004, SEC-005, SEC-008, SEC-009 (selon ce qui est réellement traité).

PERF-005 (WebP) reste **non résolu** dans le thème — ajouter une note spécifique : "Reporté infrastructure (plugin Performance Lab à activer côté admin WP, hors scope thème)".

### 3.10.2 Mise à jour `CLAUDE.md`

Section 4 (dette technique) : recompter findings résolus, mettre à jour la table.

Nouvelle subsection "Phase 3 close — récap" :
- Date
- Métriques (commits, fichiers, lignes, findings résolus)
- Bonus business / surprises éventuelles
- Apprentissages
- Pointeur Phase 4 (Bootstrap → Tailwind v4) — phase la plus risquée visuellement

### 3.10.3 Captures finales

Captures **post-Phase-3** des 8 templates de référence par l'utilisateur. Stocker dans `_docs/captures-post-phase-3/`.

Diff visuel manuel vs `_docs/captures-post-phase-2.5/` :
- 8 templates : **0 différence visuelle attendue au load initial** (les 30 premiers items sont rendus dans le HTML serveur, identiques à pre-Phase-3 sur le viewport au load)
- Comportement infinite scroll : les items 31+ apparaissent au scroll. Tester scroll à fond pour vérifier que TOUS les posts s'affichent au final (rien manqué).

### 3.10.4 Commit final + push

Commit unique : `docs: close Phase 3 (security & performance)`.

Confirmer Phase 3 close, **attendre GO avant Phase 4** (Bootstrap → Tailwind v4 — phase la plus délicate visuellement).

---

## Tests à exécuter en fin de marathon (par l'utilisateur)

### Tests fonctionnels critiques

| Test | Page | Attendu |
|---|---|---|
| Infinite scroll home | `/` | 30 mixtapes au load, scroll bas → 30 de plus, etc. jusqu'à toutes les ~370 |
| Infinite scroll single | `/<une-mixtape>/` | 30 anciennes mixtapes au load, scroll bas → 30 de plus |
| Infinite scroll category | `/category/<une-cat>/` | 30 mixtapes au load, scroll bas → 30 de plus jusqu'au bout |
| Pas de doublons en infinite scroll | Vérification visuelle | Pas de mixtape qui apparaît 2 fois dans la liste |
| Like fonctionne | Single | Bouton 🔥 OK (200 ou 429) |
| Player MP3 + YouTube | Single | Lecture, bascule entre tracks |
| Modals contact + donate | Toutes pages | Ouverture OK |
| Search | `/search/<terme>` | Résultats corrects (rappel Phase 1 Q005) |

### Tests sécurité

| Test | Commande | Attendu |
|---|---|---|
| Endpoint pagination sans nonce | `curl -i https://lamixtape.local/wp-json/lamixtape/v1/posts?context=home&offset=0` | 403 |
| Endpoint pagination rate-limit | 100+ requêtes en boucle | 429 après seuil |
| Endpoint pagination méthode POST | `curl -i -X POST .../lamixtape/v1/posts` | 405 |
| Endpoint pagination context invalide | `?context=injection` | 400 |

### Tests performance

| Test | Outil | Attendu |
|---|---|---|
| Network tab home au load | DevTools | ~30 cards rendues serveur, pas 370 |
| Lazy loading images | DevTools Network → filtre Img | Images hors viewport non chargées au load initial |
| Transient random fonctionne | DevTools Application → Storage / WP admin | Transient `lmt_random_mixtape_header` présent |
| Outfit preload | DevTools Network | Font woff2 chargée en preload, pas tardivement |

---

## Règles de travail (rappel)

- **Mode marathon** : pas de validation intermédiaire, tests à la fin
- **Discipline diagnostic-d'abord MAINTENUE** : si bug/régression/décision business → arrêt et présentation
- **Commit-par-commit** avec push immédiat après chaque commit
- **Préfixe `lmt_*`** sur toute nouvelle fonction
- **Aucun changement visuel** au-delà de l'altération comportementale validée (infinite scroll)

## Checkpoint final de Phase 3

| Élément | Statut |
|---|---|
| Axe A (PERF-001/002/007 + endpoint REST) | Liste SHA + résultat tests |
| Axe B (PERF-003/004/006/010/011/012/014) | Liste SHA + résultat tests |
| Axe C (SEC-004/005/008/009) | Liste SHA |
| `CLAUDE.md` mis à jour ? | Oui/Non |
| `_docs/AUDIT.md` Statuts ajoutés ? | Oui/Non + liste IDs |
| Captures post-Phase-3 confirment 0 diff au load initial ? | Oui/Non (utilisateur) |
| Infinite scroll testé jusqu'au bout ? | Oui/Non (utilisateur) |
| Findings restants pour Phase 4 | Liste IDs |

Confirmer Phase 3 close, **attendre GO avant Phase 4**.
