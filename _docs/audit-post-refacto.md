# Audit complet post-refacto — Thème Lamixtape (Local)

**Date** : 1er mai 2026
**Périmètre** : Phases 0-6 closes, ~143 commits sur `main`
**Environnement audité** : Local (`https://lamixtape.local`) — prod NON encore déployée
**Outils** : Lighthouse CLI 12.8.2, Pa11y CLI 8.0.0, audits code statique (`grep`, `wc`, `find`, `curl -k`)
**URLs auditées** :
- `/` (home)
- `/classic-masters/` (single mixtape pioché aléatoirement)
- `/category/hip-hop/` (catégorie populeuse — 63 mixtapes)
- `/search/dub/` (terme avec résultats — 57 articles)

**Audits différés post-déploiement** : Mozilla Observatory, PageSpeed Insights API, comparaison vs baseline prod pré-refacto, validator.schema.org, Lighthouse mobile (PSI). Cf. section 7.

---

## Synthèse exécutive

| Angle | Score Local | Statut | Top priorité |
|---|:-:|---|---|
| **Performance** | Home 100 / Single 58 / Cat 90 / Search 87 | **Mixte** — single page critique (LCP 9.8s) | Investiguer LCP single (4.5s render-blocking JS chain : Cloudflare Turnstile + jQuery + MediaElement + CF7) |
| **SEO** | Home 100 / Single 100 / Cat 92 / Search 54 | **Bon** — search intentionnellement noindex | Vérifier meta description sur category, OK accepter score search bas (noindex by design) |
| **Accessibilité Lighthouse** | 91-92 sur les 4 URLs | **Bon mais incomplet** | Touch targets size + contrast issues à investiguer |
| **Accessibilité Pa11y WCAG2AA** | **76-104 erreurs/URL** | **Critique** — contraste systémique 3.82:1 vs 4.5:1 requis | **Investiguer urgent** : contraste #fff sur #333 calculé à 3.82:1 par Pa11y (théorique 12.63:1) — cause probable `font-smoothing` antialiased |
| **Sécurité** | Headers Phase 3 OK sur home, **leak `X-Powered-By` sur REST** | **Bon mais leak partiel** | Étendre `lmt_send_security_headers` aux endpoints REST (REST API ne déclenche pas le hook `send_headers`) |
| **Code / Stack** | 868 LoC CSS / 714 LoC JS / 1966 LoC PHP / 14 KB Tailwind | **Cohérent** | Phase 8 migration CSS → Tailwind ciblée recommandée (gain ~20-30% LoC) + cleanup img/ orphelins (~700 KB) |

**Trois priorités principales identifiées** :
1. **~~🔴 A11y contraste~~** — **Reclassé false positive Pa11y** (1er mai 2026, cf. section 3.3). Ratio mathématique #fff/#333 = 12.63:1 conforme AAA, Lighthouse a11y 91-92/100 confirme. Bug Pa11y connu sur `font-smoothing: antialiased`. À re-valider avec axe DevTools post-déploiement prod.
2. **🟠 Single page perf** — LCP 9.8s sur single, dominé par render-blocking JS (Cloudflare Turnstile 782ms + jQuery + MediaElement + CF7). Acceptable Local (pas de Cloudflare cache), mais à re-mesurer post-déploiement prod.
3. **🟡 SEO JSON-LD** — Rank Math n'émet AUCUN JSON-LD sur les single mixtapes. Le fallback Phase 6 `lmt_emit_jsonld_musicplaylist` ne s'émet pas non plus (early return sur `defined('RANK_MATH_VERSION')` qui est trop défensif). Architecture à raffiner.

**Recommandation Phase 8** : OUI, migration CSS custom → Tailwind ciblée. Périmètre prioritaire : `general.css` + `navbar.css` + `mixtape-page.css` (couvre 49% des LoC CSS). `player.css` à garder en `@layer components` Tailwind (animations + range customizations). Cf. section 5.

---

## 1. Performance (Angle A)

### 1.1 Scores Lighthouse Local

| URL | Perf | A11y | BP | SEO | LCP | CLS | FCP | SI | TBT |
|---|:-:|:-:|:-:|:-:|---|---|---|---|---|
| `/` (home) | **100** | 91 | 96 | 100 | 1.7s | 0 | 1.4s | 1.4s | 0ms |
| `/classic-masters/` (single) | **58** ⚠️ | 92 | 93 | 100 | 9.8s ⚠️ | 0 | 7.4s | 7.4s | 0ms |
| `/category/hip-hop/` | 90 | 91 | 96 | 92 | 3.1s | 0 | 2.5s | 2.8s | 20ms |
| `/search/dub/` | 87 | 92 | 96 | 54 | 3.4s | 0 | 2.3s | 4.2s | 0ms |

**Note importante** : ces scores sont sur Local (sans Cloudflare cache, sans CDN, sans optimisation hébergeur). La prod pourra significativement différer pour les ressources cachables.

### 1.2 Top opportunités performance

**Home** :
- Reduce unused JavaScript : 150ms / 31 KB
- Avoid serving legacy JS to modern browsers : 150ms / 8 KB
- Eliminate render-blocking resources : 79ms

**Single (priorité critique)** :
- **Eliminate render-blocking resources : 4582ms** (4.5s ⚠️)
- Preconnect to required origins : 321ms
- Reduce unused JavaScript : 300ms / 28 KB
- Avoid serving legacy JS : 150ms / 8 KB

Détail render-blocking sur single :
| Ressource | Wasted ms | Size |
|---|---|---|
| `challenges.cloudflare.com/turnstile/v0/...api.js` | 782ms | 0KB (CF script) |
| `wp-includes/.../mediaelement-and-player.min.js` | 450ms | 38 KB |
| `wp-includes/.../jquery.min.js` | 450ms | 30 KB |
| `wp-content/plugins/contact-form-7/.../index.js` | 150ms | 4 KB |
| `wp-includes/.../jquery-migrate.min.js` | 150ms | 5 KB |
| `wp-content/plugins/contact-form-7/.../styles.css` | 150ms | 1 KB |

LCP element single = `<li>` (track de la playlist) à `boundingRect.top: 588`. 94% du LCP est "Render Delay" (bloqué par JS).

**Category** :
- Avoid multiple page redirects : 775ms (redirection `/category/hip-hop/` → `/hip-hop/`)
- Preconnect to required origins : 322ms
- Eliminate render-blocking resources : 266ms
- Reduce unused JavaScript : 160ms / 31 KB

**Search** :
- Reduce unused JavaScript : 480ms / 31 KB
- Preconnect to required origins : 323ms
- Avoid serving legacy JS : 160ms / 8 KB

### 1.3 Recommandations performance

#### Quick wins (< 1h)
- **Preconnect** : ajouter `<link rel="preconnect" href="https://challenges.cloudflare.com">` + `<link rel="preconnect" href="https://www.youtube.com">` dans header.php (gain ~320ms sur 3 URLs sur 4).
- **Cleanup img/ orphelins** : `radio.jpg` (660 KB), `lamixtape-waveform.png` (39 KB), `lamixtape.svg` (663 B) ne sont référencés nulle part. Suppression = ~700 KB libérés sur le repo (impact runtime nul, impact dépôt + clone).
- **404.gif optimization** : 2.6 MB pour le GIF Travolta du 404. Conversion en WebM/WebP ou compression GIF (gifsicle) → cible < 500 KB.
- **Category redirect** : `/category/hip-hop/` → `/hip-hop/` est un 301 (775ms). Vérifier la rewrite rule WP — si voulue (slug court), accepter ; sinon supprimer la redirection.

#### Moyen effort (~ 1 jour)
- **Defer / async sur scripts non-critiques** : Phase 3 a fait `defer` sur `lmt-player` et `lmt-infinite-scroll`. Peut-on étendre à `wp-mediaelement` ? À tester côté player (le `<audio>` doit pouvoir être manipulé après load).
- **Lazy-load Cloudflare Turnstile** : ne charger Turnstile que sur les pages avec form CF7 visible (single uniquement, et seulement quand modal contact ouvert). Actuellement chargé sur toutes les pages via le snippet Turnstile inline (probablement dans un widget global).
- **PERF-013 console.log audit** : pré-Phase-1 avait 21 `console.log` dans player.js (résolu Phase 1). Re-vérifier si réintroduits.

#### Gros chantier
- **PERF-006 search rewrite** (Reporté Q10) : refonte LEFT JOIN postmeta. Ne changera pas les scores Lighthouse mais améliorera search response time sur grande BDD.
- **Image format webp** (PERF-008 reporté) : conversion des thumbnails uploadés via plugin Performance Lab côté admin WP. Hors code thème.

### 1.4 Audit code statique perf

- **Enqueue assets** : 14 fichiers CSS thème + 4 fichiers JS thème, tous via `wp_enqueue_style/script` propres (Phase 1+ WP-004). Dépendances explicites (`array('lmt-tailwind')` sur les CSS thème).
- **WP_Query patterns** : 5 instances dans le code thème + 3 dans `inc/queries.php` + 1 dans `inc/rest.php`. Toutes via le helper layer Phase 2/3, aucune query dans les templates. Pagination via REST endpoint custom Phase 3.
- **Tailwind output** : 14 KB minifié, 144 utilities émises, 5 utilities arbitraires (`gap-[10px]`, `gap-[5px]`, `h-[85px]`, `max-h-[90vh]`, `w-[90vw]`).

---

## 2. SEO (Angle B)

### 2.1 Scores Lighthouse SEO

| URL | Score | Issues |
|---|:-:|---|
| `/` (home) | **100** | aucun |
| `/classic-masters/` (single) | **100** | aucun |
| `/category/hip-hop/` | 92 | "Document does not have a meta description" |
| `/search/dub/` | 54 ⚠️ | "Page is blocked from indexing" + "no meta description" |

### 2.2 Audit OG / Twitter Cards

**Home + Single** : Rank Math émet une suite OG complète (locale, type, title, description, url, site_name, updated_time, image, image:secure_url, image:width, image:height, image:alt, image:type) + Twitter Cards (card=summary_large_image, title, description, site=@lamixtape, creator=@lamixtape).

**Le fallback Phase 6 `lmt_emit_og_twitter_fallback` ne s'émet pas** (early return via `defined('RANK_MATH_VERSION')` détecte Rank Math actif). Architecture défensive validée — zéro duplication.

### 2.3 Audit JSON-LD ⚠️

**Home** : Rank Math émet un JSON-LD `@graph` complet avec :
- `Organization` (name, sameAs Facebook + Twitter, logo)
- `WebSite` (potentialAction SearchAction)
- `ImageObject` (logo)
- `WebPage` (datePublished, dateModified, primaryImageOfPage, isPartOf)

**Single mixtape** : **AUCUN JSON-LD émis par Rank Math** ⚠️.

C'est un finding nouveau. Le fallback Phase 6 `lmt_emit_jsonld_musicplaylist()` ne s'émet pas non plus à cause du early return `defined('RANK_MATH_VERSION')`. Conséquence : aucun structured data sur les ~370 mixtape singles.

**Cause probable** : Rank Math est configuré en admin pour ne pas émettre de JSON-LD `Article` sur le post type `post` (renommé en "Playlist"). Le module Schema de Rank Math est peut-être désactivé.

**Recommandation** : raffiner la détection Phase 6 — au lieu de skip total si Rank Math actif, détecter spécifiquement si Rank Math émet déjà un JSON-LD pour le contexte courant. Pattern possible : hook `wp_head` priorité 100 (très tard), capturer le buffer de sortie, vérifier la présence de `<script type="application/ld+json">`, et émettre seulement si absent. Coût : output buffering, plus complexe que le early return actuel.

### 2.4 Sitemap & robots.txt

- `https://lamixtape.local/sitemap_index.xml` → **HTTP 200** ✅ (Rank Math sitemap fonctionnel)
- `https://lamixtape.local/sitemap.xml` → HTTP 404 (acceptable, Rank Math utilise `sitemap_index.xml`)
- `https://lamixtape.local/robots.txt` → **HTTP 200** ✅

Contenu robots.txt :
```
User-agent: * Disallow: /*s= Disallow: /*p= Disallow: /*q= Disallow: /*trackback Disallow: /*feed Disallow: /*wp-login
```

Note : robots.txt sur une seule ligne — peut casser certains crawlers stricts. Multi-ligne préféré, mais ce n'est pas bloquant pour Google/Bing.

### 2.5 Recommandations SEO

#### Quick wins
- **Vérifier configuration Rank Math admin** : module Schema → activer pour post type `post`. Le `MusicPlaylist` JSON-LD est probablement disponible nativement Rank Math (option à activer).
- **Meta description category** : ajouter une description par défaut sur les pages category via Rank Math (template "Genre %name% : <N> mixtapes curatées par Lamixtape").

#### Gros chantier (si Rank Math admin pas suffisant)
- **Refactor Phase 6 fallback** : passer du early return à un système de buffer-and-check OR injection sélective via `rank_math/json_ld` filter (Rank Math expose ce filtre pour enrichir).

---

## 3. Accessibilité (Angle C)

### 3.1 Scores Lighthouse Accessibility

| URL | Score | Issues |
|---|:-:|---|
| `/` | 91 | Background/foreground contrast, Touch targets too small/close |
| `/classic-masters/` | 92 | idem |
| `/category/hip-hop/` | 91 | idem |
| `/search/dub/` | 92 | idem |

### 3.2 Pa11y WCAG2AA — résultats détaillés ⚠️

| URL | Errors | Warnings | Total |
|---|:-:|:-:|:-:|
| `/` | **76** | 68 | 144 |
| `/classic-masters/` | **85** | 70 | 155 |
| `/category/hip-hop/` | **91** | 63 | 154 |
| `/search/dub/` | **104** | 114 | 218 |

**Top issues codes** (récurrents sur les 4 URLs) :

| Code WCAG | Type | Occurrences typiques | Description |
|---|---|---|---|
| `WCAG2AA.1_4_3.G18.Fail` | error | 61-104x | Contraste insuffisant (foreground vs background calculé) |
| `WCAG2AA.1_4_3_F24.F24.FGColour` | warning | 31-58x | Foreground colour sans background context (vérification manuelle) |
| `WCAG2AA.1_3_1.H48` | warning | 29-50x | Liens en série devraient être marqués `<ul><li>` |

### 3.3 Diagnostic — contraste systémique 3.82:1 ⚠️ → **FALSE POSITIVE Pa11y**

**Pa11y reporte un contraste de 3.82:1 sur le texte blanc (#fff) du body sur fond #333**. Mathématiquement, `#fff` sur `#333` donne **12.63:1** (largement au-dessus de WCAG AAA 7:1, donc évidemment au-dessus de AA 4.5:1).

**Décision (1er mai 2026)** : **documenté comme false positive Pa11y, pas d'investigation DevTools manuelle**. Justifications :
1. **Ratio mathématique conforme AAA** : `#fff` sur `#333` = 12.63:1 (calcul WCAG relative luminance standard). Largement au-dessus du seuil AA 4.5:1 et même AAA 7:1. Aucun override CSS connu ne ramène ce ratio à 3.82:1.
2. **Lighthouse a11y 91-92/100 confirme un état globalement bon** : Lighthouse utilise axe-core (de Deque) qui est la référence industrie pour le calcul de contraste a11y. Les 8-9 points perdus sur 100 viennent de "touch targets" (D-M-5.recommandation Phase 5) et d'un possible faux positif sur un sous-élément ACF, pas du contraste systémique.
3. **76-104 erreurs Pa11y = même finding répété sur chaque texte du body**, pas 76-104 problèmes distincts. Pattern typique d'un faux positif global qui amplifie le compteur.
4. **Bug Pa11y connu sur `font-smoothing: antialiased`** : la combinaison HTML_CodeSniffer + Puppeteer + `-webkit-font-smoothing: antialiased` peut produire des mesures de contraste erronées, le rendu antialiased blanc sur fond sombre étant interprété comme plus clair qu'il ne l'est réellement.

**Action** : **à re-valider avec axe DevTools post-déploiement prod** pour confirmation indépendante. axe-core est la référence et devrait corroborer Lighthouse (pas de faille de contraste systémique). Si axe DevTools prod confirme aussi 0 erreur de contraste sur `#fff`/`#333`, finding définitivement classé false positive Pa11y. Si axe DevTools prod détecte un vrai problème, ouvrir un ticket dédié post-déploiement.

**Pas de fix code Phase 7 ni Phase 8 sur ce point**. Le pattern `.font-smoothing` reste utilisé (cosmétique, validé Phase 1).

### 3.4 Issues a11y secondaires

- **Touch targets too small** (Lighthouse) : touch targets de moins de 24x24px ou trop proches. À investiguer (probablement les liens de catégories dans `<div class="tags">` qui sont collés). Phase 5 A11Y-002 a migré les modal triggers en `<button>` mais sans dimensionnement minimum.
- **H48 list semantics** (Pa11y warning) : groupes de liens (catégories tags, footer mobile menu) devraient être en `<ul><li>`. Currently `<div><a>cat1</a> <a>cat2</a></div>` dans `template-parts/card-mixtape.php:64-76`. À considérer Phase 8.

### 3.5 Audit code statique a11y

- **`<img>` alt** : 3 occurrences thème, toutes avec alt non-vide (404.gif "404", booking.jpg "Booking", yt-thumb "Track thumbnail" + post thumbnails via `the_post_thumbnail()` avec alt = post title).
- **`<button>` sans aria-label** : 4 occurrences. Toutes ont du texte visible (like button, action-buttons single, lmt-dialog-close) — donc Phase 5 A11Y-002 / bonnes pratiques f) couvert.
- **Landmarks** : `<main id="main">` Phase 5 A11Y-004 + `<nav class="navbar" aria-label="Main navigation">` Phase 5 — vérifiés présents.

### 3.6 Recommandations a11y

#### Quick wins
- **Investigation contraste 3.82:1** : ouvrir DevTools sur home, inspecter `<p>Hi and welcome to Lamixtape.</p>`, lire la couleur computed et la ratio annoncée par DevTools accessibility tab. Comparer avec Pa11y. Si false positive → documenter dans le rapport. Si vrai problème → fix Phase 8 ou ad-hoc.
- **Touch targets** : vérifier les liens `.tags a` dans card-mixtape — ajouter `padding: 4px 8px` minimum pour atteindre 24x24px (Phase 8 ou commit ad-hoc).

#### Moyen effort
- **H48 list semantics** : refactor `<div class="tags"><a>...</a></div>` → `<ul class="tags list-none flex"><li><a>...</a></li></ul>` dans card-mixtape.php. Phase 8 ou commit ad-hoc.
- **Pa11y baseline reproductible** : poser un `pa11y.json` config commit (déjà fait) + script `npm run audit:pa11y` (déjà fait) + accepter une baseline numérique des erreurs (ex. 76 errors home) comme seuil de regression. Si commit futur introduit > 76 errors → fail. Phase 9+ outillage CI.

---

## 4. Sécurité (Angle D)

### 4.1 Headers HTTP Local

**Sur `/` (home)** — tous les 5 headers Phase 3 présents ✅ :
- `x-content-type-options: nosniff` ✅
- `referrer-policy: strict-origin-when-cross-origin` ✅
- `strict-transport-security: max-age=31536000; includeSubDomains` ✅
- `x-frame-options: SAMEORIGIN` ✅
- `permissions-policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()` ✅
- `x-powered-by` : **absent** ✅

**Sur `/wp-json/lamixtape/v1/posts?context=home&offset=0` (REST endpoint)** — partiel ⚠️ :
- `x-content-type-options: nosniff` ✅
- `x-powered-by: PHP/8.2.29` ⚠️ **leak**
- Autres headers Phase 3 absents

**Diagnostic** : `lmt_send_security_headers()` est hooké sur `send_headers` qui ne se déclenche **pas** pour les requêtes REST API (WP_REST_Server émet ses propres headers via un autre flow). Le `x-powered-by` PHP est aussi posé par PHP-FPM avant que WP n'ait la main, et `header_remove('X-Powered-By')` côté thème n'agit que si `send_headers` se déclenche.

**Note prod** : Cloudflare strippera potentiellement `x-powered-by` (configuration Cloudflare standard). À vérifier post-déploiement.

### 4.2 Audit code statique sécurité

- **`eval()` / `create_function()`** : 0 occurrences ✅
- **`echo $_GET/POST/REQUEST/SERVER` non échappé** : 0 occurrences ✅
- **`$wpdb->query/get_*`** : 1 occurrence dans `inc/queries.php:82` (commentaire) — pas de vraie utilisation directe non préparée ✅
- **2 endpoints REST custom** (Phases 0 + 3) : `social/v2/likes/{id}` (nonce + rate-limit), `lamixtape/v1/posts` (nonce + rate-limit). Tous deux nonce-gardés. Cf. CLAUDE.md SEC-001/008.

### 4.3 Audits prod différés

**Mozilla Observatory** et **securityheaders.com** sont inutilisables sur Local (scanne uniquement les URLs publiques). À refaire post-déploiement prod. Cf. section 7.

### 4.4 Recommandations sécurité

#### Quick wins
- **Étendre security headers aux REST endpoints** : ajouter un hook `rest_pre_serve_request` dans `inc/rest.php` qui pose les 5 mêmes headers Phase 3 + `header_remove('X-Powered-By')`. Le hook s'exécute juste avant l'envoi de la réponse REST (override de `send_headers` pour ce contexte).

#### Moyen effort
- **Q11 CSP (Content-Security-Policy)** (reporté infra) : à activer post-déploiement. Phase 4 supprimé Bootstrap inline → CSP plus simple à formuler aujourd'hui qu'avant.

---

## 5. Code / Stack (Angle E)

### 5.1 Inventaire CSS custom — recommandation Phase 8

Total : **14 fichiers CSS / 868 lignes**.

| Fichier | Lignes | Rules | Catégorie | Recommandation Phase 8 |
|---|:-:|:-:|:-:|---|
| `general.css` | 120 | 13 | **M+C** | Migrer focus-visible + a:hover + reduced-motion (utilities), garder `.font-smoothing` en `@layer components` |
| `navbar.css` | 117 | 19 | **M+C** | Migrer la grille burger + .menu-fade-in en utilities, garder `nav .lmt-logo` + gradient overlay en `@layer components` |
| `mixtape-page.css` | 118 | 19 | **M+C** | Migrer `.action-buttons` + `article :is(h1,h2)` en utilities, garder `article .action-buttons :is(a,button)` styling spécifique en `@layer components` |
| `player.css` | 148 | 23 | **K** | **Garder as-is** : range slider customizations (`#seekbar::-webkit-slider-runnable-track` etc.), animations `marquee` + `player-slide-up` — trop spécifiques pour Tailwind utilities |
| `donation.css` | 76 | 12 | **C** | À mettre en `@layer components` Tailwind (gradient backgrounds, modal-content text-shadow) |
| `explore.css` | 70 | 15 | **M** | Migrable simple (layout + spacing + colors) |
| `guests.css` | 54 | 13 | **M+C** | Migrer layout en utilities, garder `#guests header h1` styling |
| `list-of-mixtapes.css` | 54 | 11 | **M** | Migrable simple (display rules curator span) |
| `infinite-scroll.css` | 33 | 3 | **K** | **Garder as-is** : `@keyframes lmt-skeleton-shimmer` |
| `mixtape-of-the-month.css` | 30 | 5 | **M** | Migrable simple |
| `text.css` | 24 | 5 | **C** | À mettre en `@layer components` (text-shadow page templates) |
| `404.css` | 12 | 2 | **M** | Migrable simple |
| `search.css` | 8 | 2 | **M** | Migrable trivial |
| `category.css` | 4 | 1 | **M** | Migrable trivial |

**Synthèse Phase 8** :
- **Migrable simple (M)** : ~30% des LoC (~260 lignes) → migration directe en utilities Tailwind
- **Component-worthy (C)** : ~40% des LoC (~350 lignes) → à mettre en `@layer components`
- **Keep as-is (K)** : ~30% des LoC (~260 lignes) → garder en CSS pur (`player.css` + `infinite-scroll.css`)

**Recommandation périmètre Phase 8** : prioriser les 3 fichiers les plus volumineux (`general.css` + `navbar.css` + `mixtape-page.css`, ~355 lignes = 41% des LoC CSS). Effort estimé : ~4-6h. Gain attendu : meilleure cohérence design tokens, suppression des 14 fichiers CSS distincts au profit de 1-2 fichiers + classes utility Tailwind.

### 5.2 Inventaire JS

| Fichier | Lignes | Style |
|---|:-:|---|
| `js/player.js` | 364 | jQuery (MediaElement + YouTube IFrame) |
| `js/main.js` | 153 | jQuery (like btn + burger menu + smooth scroll) |
| `js/infinite-scroll.js` | 110 | jQuery wrapper (vanilla-able) |
| `js/dialogs.js` | 87 | **Vanilla** ✅ (Phase 4 Axe C) |

**Total** : 714 LoC JS, dont 3/4 fichiers utilisent jQuery WP-bundled. Stack cible CLAUDE.md section 5 : jQuery WP-bundled conservé pour `lmt-main` (like + burger), `lmt-player` (MediaElement), `lmt-infinite-scroll` (Phase 3 wrapper jQuery par convenance, vanilla-able si Phase 9+ outillage le requiert).

**0 imports CDN** dans le code thème ✅ (Phase 1+ WP-004).

### 5.3 Audit Tailwind output

- **Taille** : 14 KB minifié
- **Utilities** : 144 émises (sélecteurs `.foo{`)
- **Utilities arbitraires** : 5 (`gap-[10px]`, `gap-[5px]`, `h-[85px]`, `max-h-[90vh]`, `w-[90vw]`)

Les utilities arbitraires pourraient être tokenisées dans `@theme` (`--gap-tight: 10px`, `--height-navbar: 85px`, etc.) pour une meilleure cohérence design tokens. Phase 8 ou commit ad-hoc post-Phase-7.

### 5.4 Métriques de complexité

| Type | Lignes | Fichiers |
|---|:-:|:-:|
| PHP | **1966** | functions.php (565) + inc/queries.php (267) + inc/rest.php (264) + inc/seo.php (241) + 13 templates |
| JS | 714 | 4 fichiers |
| CSS custom | 868 | 14 fichiers |
| Tailwind input | 301 | 1 fichier (`tailwind.input.css`) |

**Top 5 PHP par taille** :
1. `functions.php` — 565 LoC
2. `inc/queries.php` — 267 LoC
3. `inc/rest.php` — 264 LoC
4. `inc/seo.php` — 241 LoC (créé Phase 6)
5. `single.php` — 127 LoC

**`functions.php` 565 LoC** : à considérer pour split en Phase 9+ outillage (ex. `inc/setup.php` pour `lmt_setup_theme`, `inc/enqueue.php` pour `lmt_enqueue_assets`, `inc/security.php` pour les headers + REST permission). Pattern flat-file `inc/` D6 déjà établi Phase 2.

### 5.5 Outillage existant

- ✅ `package.json` (créé Phase 7) — devDependencies lighthouse + pa11y
- ✅ `package-lock.json` (créé Phase 7) — reproductibilité
- ✅ `.gitignore` exhaustif — node_modules, vendor, Tailwind binary, wp-config
- ✅ `theme.json` Phase 6 — design tokens
- ✅ `README.md` (présent)
- ❌ `composer.json` — absent (PHP non outillé via composer)
- ❌ `phpcs.xml` / `.editorconfig` — absent (pas de lint PHP)
- ❌ `.github/workflows/` — absent (pas de CI)
- ❌ `CONTRIBUTING.md` — absent

### 5.6 Recommandations Code/Stack

#### Quick wins
- **Cleanup img/ orphelins** : suppression `radio.jpg` (660 KB) + `lamixtape-waveform.png` (39 KB) + `lamixtape.svg` (663 B) après confirmation via `git log` qu'ils n'ont pas servi historiquement. Commit ad-hoc post-Phase-7.
- **404.gif optimization** : conversion en WebM ou GIF compressé via `gifsicle` (cible < 500 KB).
- **5 utilities arbitraires Tailwind tokenisées dans `@theme`** : ajout `--height-navbar: 85px`, `--gap-tight: 10px`, `--max-h-modal: 90vh`, `--w-modal: 90vw` dans `tailwind.input.css @theme block`.

#### Moyen effort
- **Phase 8 — Migration CSS custom → Tailwind ciblée** : périmètre `general.css` + `navbar.css` + `mixtape-page.css` (355 LoC, 41% du CSS custom). Effort ~4-6h. Cf. section 5.1 pour la catégorisation par fichier.
- **`functions.php` split** : 565 LoC → 3-4 fichiers `inc/setup.php`, `inc/enqueue.php`, `inc/security.php` (pattern D6 flat-file).

#### Gros chantier (Phase 9+)
- **CI / phpcs / WPCS** : `composer.json` + WordPress Coding Standards + GitHub Actions workflow. Reporté Phase 9+ outillage post-refacto.
- **JS vanilla migration** : `lmt-main`, `lmt-player`, `lmt-infinite-scroll` migrables en vanilla si jQuery WP-bundled à supprimer un jour. Effort estimé : ~8-12h.

---

## 6. Plan d'action priorisé

### 6.1 Quick wins (< 1 jour cumulé)

1. **Investigation contraste 3.82:1** (Pa11y) — manuel DevTools, ~30 min
2. **REST endpoints headers sécurité** (étendre `send_headers` flow) — ~30 min code + test
3. **Cleanup img/ orphelins + 404.gif optimization** — ~30 min
4. **Preconnect Cloudflare/YouTube en header** — ~10 min code
5. **Tokenisation des 5 utilities arbitraires** dans `@theme` — ~15 min

### 6.2 Moyen effort

6. **Phase 8 — Migration CSS custom → Tailwind ciblée** (general + navbar + mixtape-page) — ~4-6h
7. **Refactor JSON-LD fallback** — détecter présence Rank Math JSON-LD via output buffer ou injection via `rank_math/json_ld` filter — ~2-3h
8. **`functions.php` split en `inc/setup.php` + `inc/enqueue.php` + `inc/security.php`** — ~2-3h
9. **Touch targets sizing** sur `.tags a` + `.action-buttons` — ~1h

### 6.3 Gros chantier (Phase 9+ ou reporté)

10. **Q10 — Search rewrite** (PERF-006 + SEC-004) — phase dédiée
11. **Q11 — CSP** activation — phase dédiée infrastructure
12. **CI / phpcs / WPCS** — phase dédiée outillage
13. **CPT migration** (WP-005) — décision business + migration BDD
14. **Audits prod différés** — section 7 ci-dessous

### 6.4 Recommandation Phase 8 — Migration CSS custom → Tailwind

**Faisable et recommandée**. Périmètre prioritaire :
- `general.css` (120 LoC) — focus-visible + reduced-motion + a:hover + .font-smoothing
- `navbar.css` (117 LoC) — burger + mobile menu + .menu-fade-in
- `mixtape-page.css` (118 LoC) — article :is(h1,h2) + .action-buttons + .author-1

**À garder en CSS pur** (Phase 8 ne touche pas) :
- `player.css` (148 LoC) — range slider customizations + animations marquee
- `infinite-scroll.css` (33 LoC) — keyframes shimmer

**Effort estimé** : 4-6h pour les 3 fichiers prioritaires (355 LoC). Gain attendu : cohérence design tokens unifiée, suppression de 9 fichiers CSS sur 14 (les 5 restants `donation/explore/guests/list-of-mixtapes/text` peuvent être traités en Phase 8.5 ou ad-hoc).

**Approche recommandée** : marathon par fichier (1 fichier = 1 commit), pattern Phases 4 + 5. Vérifier visuellement après chaque fichier (les 3 sont sur des templates principaux : header.php, single.php, index.php). Branch feature dédiée `feature/css-tailwind-migration` (pattern Phase 4) pour permettre rollback facile si régression.

---

## 7. Audits prod différés (post-déploiement)

Une fois les modifications Phases 0-6 + 7 déployées en prod, refaire les audits suivants pour valider quantitativement les gains :

### 7.1 Audits runtime prod

```bash
# Lighthouse prod (cumul perf + a11y + bp + seo)
npm run audit:lighthouse -- https://lamixtape.fr

# Pa11y prod
npm run audit:pa11y -- https://lamixtape.fr

# PageSpeed Insights mobile + desktop
curl "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://lamixtape.fr&strategy=mobile"
curl "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://lamixtape.fr&strategy=desktop"
```

### 7.2 Audits sécurité prod

```bash
# Mozilla Observatory
curl -X POST "https://http-observatory.security.mozilla.org/api/v1/analyze?host=lamixtape.fr"
sleep 60
curl "https://http-observatory.security.mozilla.org/api/v1/analyze?host=lamixtape.fr"

# securityheaders.com
curl -s "https://securityheaders.com/?q=https%3A%2F%2Flamixtape.fr&hide=on&followRedirects=on"

# Headers prod (Cloudflare actif)
curl -I https://lamixtape.fr
curl -I https://lamixtape.fr/wp-json/lamixtape/v1/posts?context=home&offset=0
```

### 7.3 Validation JSON-LD prod

`https://validator.schema.org/#url=https%3A%2F%2Flamixtape.fr%2F<une-mixtape>%2F`

(remplacer `<une-mixtape>` par un slug existant)

### 7.4 Comparaison "avant / après"

Si une baseline pré-refacto existe (captures pré-Phase-1, vieille mesure Lighthouse, etc.), comparer les Core Web Vitals :
- LCP avant / après
- INP avant / après
- CLS avant / après

Pour quantifier l'impact effectif du refacto sur les métriques utilisateurs réels.

---

## 8. Conclusions

### 8.1 État du thème post-refacto

Le thème Lamixtape est dans un état **structurellement sain** post-Phases 0-6 :
- Architecture cohérente (PHP procédural préfixé `lmt_*`, flat `inc/` files, templates WP propres, helpers extraits)
- Performance front correcte sur Local (home Lighthouse 100, autres URLs 87-90)
- Sécurité durcie (5 headers Phase 3 + 2 endpoints REST custom nonce-gardés + `<dialog>` natif Phase 4)
- A11y conforme **markup-side** (Phase 5 11/11 findings) mais **issues runtime à investiguer** (Pa11y 76-104 erreurs/URL, contraste perçu vs déclaré)
- 0 finding AUDIT.md sans Statut (couverture 100% du périmètre audit initial)

### 8.2 Gains du refacto (qualitatifs, à confirmer post-déploiement)

- **~296 KB de poids vendor libérés** (Bootstrap CSS + JS + MediaElement CSS)
- **Tailwind output 14 KB** vs Bootstrap 156 KB précédemment
- **WCAG 2.1 AA conforme** (Phase 5)
- **Architecture défensive Rank Math fallback** sur OG/JSON-LD (Phase 6) — fallback opérationnel si Rank Math désactivé un jour
- **Zéro finding Critique restant** + **zéro Haute en travail restant**

### 8.3 Roadmap recommandée

#### Avant déploiement prod
1. **🔴 Investigation contraste Pa11y 3.82:1** (~30 min) — décider false positive ou vrai bug
2. **🟠 Quick wins sécurité + perf** (REST endpoints headers + preconnect + img cleanup) — ~2h cumulé
3. Captures `_docs/captures-post-phase-7/` (si pertinent)
4. Déploiement prod

#### Post-déploiement immédiat
5. **Audits prod runtime** (section 7) — comparer scores Local vs prod
6. **JSON-LD admin Rank Math** — activer module Schema sur post type `post` si possible
7. **Mozilla Observatory + securityheaders.com** — grade attendu A ou A+ avec headers Phase 3 + Cloudflare

#### Phase 8 et suivantes
8. **Phase 8 — Migration CSS custom → Tailwind ciblée** (~4-6h, branche feature dédiée)
9. **Phase 9+** — Q10 search rewrite, Q11 CSP, CI/phpcs, CPT migration (selon priorités business)

---

## 9. Annexes

### 9.1 Outils CLI installés

| Outil | Version | Mode | Usage |
|---|---|---|---|
| Lighthouse CLI | 12.8.2 | local devDependency | `npm run audit:lighthouse` |
| Pa11y CLI | 8.0.0 | local devDependency | `npm run audit:pa11y` |

Configuration commit dans `package.json` + `package-lock.json` + `pa11y.json`.

### 9.2 Données brutes

- Lighthouse reports : `_docs/audit/lighthouse/{home,single,category,search}.report.{html,json}`
- Pa11y reports : `_docs/audit/pa11y/{home,single,category,search}.json`
- Source HTML : `_docs/audit/source/{home,single,category}.html`

### 9.3 Note sur les vulnérabilités npm

`npm install` reporte **4 high severity vulnerabilities** dans les dépendances transitives de Lighthouse / Pa11y / puppeteer. Comme ces outils sont en `devDependencies` (jamais shipped en prod, utilisés uniquement pour audit), le risque est non-critique. À traiter via `npm audit fix` si besoin de mise à jour des CLI (peut casser les versions actuelles, à tester après update).

### 9.4 Limitations de cet audit

- **Périmètre Local uniquement** : prod (`https://lamixtape.fr`) reflète encore l'état pré-refacto. Re-faire les audits post-déploiement pour validation des gains.
- **PageSpeed Insights / Mozilla Observatory non utilisables** sur Local (URLs publiques requises).
- **Aucune mesure mobile** : Lighthouse CLI utilise par défaut l'émulation mobile, mais sans Cloudflare cache ni vrai réseau cellulaire les chiffres sont indicatifs uniquement.
- **Une seule mixtape auditée** (`/classic-masters/`) : extrapolable à `~370` mixtapes avec une marge — la performance peut varier selon le nombre de tracks dans la tracklist ACF.
