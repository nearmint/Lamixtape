# CLAUDE.md — Lamixtape.fr

## 1. Contexte projet

Lamixtape.fr est un webzine musical historique (2011–2022) qui publie des **mixtapes mensuelles curatées** par un roster interne et des invités. Le site est servi par WordPress avec un **thème custom standalone** (non-FSE, pas de child theme), construit sur Bootstrap 4.4.1 et jQuery, sans build tool. Pas de revenus publicitaires (donations PayPal). Environnements : **dev local + prod uniquement** (pas de staging). Stack PHP/JS volontairement minimaliste, mais l'absence d'outillage moderne (build, versioning, tests, i18n réelle) génère une dette technique significative à reprendre avant tout refacto fonctionnel.

## 2. État des lieux technique

### Arborescence haut niveau
```
lamixtape/
├── functions.php          # Tout le code PHP « métier » (305 l.)
├── header.php / footer.php
├── index.php              # Template Name: home → liste de toutes les mixtapes
├── single.php             # Page mixtape (inclut player.php)
├── category.php / search.php / 404.php / comments.php
├── explore.php / guests.php / text.php  # Page templates
├── player.php             # Partial : footer player + JS YouTube/MediaElement (inline)
├── analytics.php          # Snippet Umami (inline)
├── style.css              # Header de thème + 15 @import CDN+local
├── css/                   # 15 fichiers CSS, ~900 l. cumulées
├── js/main.js             # Newsletter (mort), like/dislike, burger, animations
├── img/                   # 7 fichiers (logos, illustrations, 404.gif)
└── audit.md / CLAUDE.md / AUDIT.md
```

### Stack
| Couche | Détail |
|---|---|
| **PHP** | Procédural, fichier unique `functions.php`, aucun namespace, aucune classe, aucun autoload, aucun `composer.json` |
| **JS** | jQuery 3.6.0 (CDN dans `<head>`), MediaElement.js 4.2.16 (CDN), YouTube IFrame API (chargée dynamiquement), Bootstrap 4.4.1 JS (CDN dans footer) |
| **CSS** | Bootstrap 4.4.1 (CDN via `@import` dans `style.css`), MediaElement CSS (CDN), Google Font Outfit (CDN), 15 fichiers CSS locaux concaténés via `@import` |
| **Build** | **Aucun** (pas de `package.json`, `gulpfile`, `vite.config`, etc.) |
| **theme.json** | Absent |
| **i18n** | Calls `__()/_e()/esc_html__()` présents, mais text-domain littéral `'text-domain'` (slug placeholder jamais remplacé) → traductions **non fonctionnelles** |
| **Versioning** | **Aucun** (pas de `.git` à la racine du thème ni du repo parent) |

### Plugins (présents dans `wp-content/plugins/`)
- **ACF Pro** — fournit les champs `color`, `highlight`, `likes_number`, `tracklist` (repeater) — **dépendance dure du thème**
- **Contact Form 7** — formulaire `id="66478de"` injecté dans le modal contact (footer.php:39)
- **Rank Math SEO** — gère titres/meta/sitemap (le thème n'active pas `add_theme_support('title-tag')`)
- **Akismet**, **WP Mail SMTP**, **Zapier** — utilitaires
- **chckr-yt**, **allow-multiple-accounts** — plugins custom (hors périmètre de ce refacto, voir Questions ouvertes)

### Custom Post Types, taxonomies, hooks
- **Pas de vrai CPT** : le post type natif `post` est **renommé** en "Playlist" via `revcon_change_post_label()` et `revcon_change_post_object()` (functions.php:105-132).
- Filtres sur la recherche : `posts_join`, `posts_where`, `posts_distinct` étendent la recherche aux `postmeta` (functions.php:13-42).
- Hook `pre_get_posts` : exclut les `page` du résultat de recherche.
- Hook `template_redirect` : `wp_change_search_url()` redirige `?s=` vers `/search/{terme}`.
- Comments : callback custom `tape_comment` (functions.php:175-201), formulaire customisé via `comment_form_default_fields` et `comment_form_field_comment`.
- Endpoint REST : `social/v2/likes/{id}` et `social/v2/dislikes/{id}` (functions.php:276-298).
- Cleanup `<head>` : suppression agressive de RSD, oEmbed, emojis, REST link, generator (functions.php:77-100).

### Dépendances tierces externes (CDN)
- `stackpath.bootstrapcdn.com/bootstrap/4.4.1/...` (CSS + JS)
- `code.jquery.com/jquery-3.6.0.min.js`
- `cdn.jsdelivr.net/npm/mediaelement@4.2.16`
- `fonts.googleapis.com/css2?family=Outfit`
- `cloud.umami.is/script.js` (analytics)
- `www.youtube.com/iframe_api`

## 3. Conventions et standards en vigueur

- **Naming PHP** : *aucune* convention cohérente. Coexistent `cf_search_join` (préfixe historique copié-collé), `revcon_change_post_label`, `wpb_remove_version`, `tape_comment`, `loadmore_enqueue`, `social__like` (double underscore), `SearchFilter` (PascalCase). Pas de préfixe thème (ex. `lmt_` ou `lamixtape_`).
- **Naming CSS** : surtout du Bootstrap-style + classes ad hoc en `kebab-case` (`fade-in`, `mixtape-list`, `font-smoothing`, `no--hover`, `like__btn`). Mélange BEM-ish et utilitaires.
- **Templates** : hiérarchie WP respectée pour les types principaux (`index/single/category/search/404`). Page templates déclarés via `Template Name:` en commentaire.
- **i18n** : tous les strings passent par `__()/esc_html__()` mais avec text-domain `'text-domain'` (placeholder jamais remplacé).
- **Échappement** : `esc_url`, `esc_html`, `esc_attr`, `esc_html__/_e/_attr_e` sont **utilisés systématiquement dans les templates** — bon point. En revanche, certaines requêtes SQL custom interpolent directement (cf. AUDIT.md SEC-003).
- **Standards formels** : *aucun*. Pas de `phpcs.xml`, pas de `.editorconfig`, pas de WordPress Coding Standards configuré.

## 4. Dette technique priorisée

| Axe | Findings totaux | Critique restant | Haute | Référence |
|---|:-:|:-:|:-:|---|
| **Process / Qualité** | 14 | 0 ✅ | 4 | `AUDIT.md#qc` |
| **Sécurité** | 9 | 0 ✅ | 1 | `AUDIT.md#securite` |
| **Performance** | 14 | 2 | 4 | `AUDIT.md#performance` |
| **Accessibilité** | 11 | 0 | 4 | `AUDIT.md#a11y` |
| **WP best practices** | 9 | 0 | 3 | `AUDIT.md#wp` |
| **Migration Tailwind** | 5 | 0 | 2 | `AUDIT.md#tailwind` |
| **Autres (SEO, RGPD, observabilité)** | 8 | 0 | 2 | `AUDIT.md#autres` |
| **TOTAL** | **70** | **2** | **20** | |

Critiques (à régler avant tout autre travail) — état au {{Phase 0}} :
1. **QC-001** ✅ **résolu Phase 0** (commit `9606b78` — init git + `.gitignore` + push GitHub).
2. **SEC-002** ✅ **résolu Phase 0** (commit `2d656f8` — feature dislike supprimée intégralement).
3. **SEC-001** ✅ **résolu Phase 0** (commit `f8107e0` — `permission_callback` + nonce REST + rate-limit transient hash IP).
4. **PERF-001** — `index.php` rend 360+ articles d'un coup (`posts_per_page => -1`). → cible Phase 3.
5. **PERF-002** — `single.php` exécute une `WP_Query` sur 1 000 000 lignes filtrées par date. → cible Phase 3.

> Phase 0 close : 3 critiques sur 5 résolues. Reste 2 critiques perfo (`PERF-001`, `PERF-002`) qui passent en priorité 1 pour la Phase 3 (sécurité & perf bloquantes), après les phases 1 (hygiène) et 2 (refacto structurel).

## 5. Recommandations stratégiques

### Stack cible recommandée

- **Tailwind CSS v4 + CLI standalone** (binaire unique, pas de Node/PostCSS à installer en prod, watch via `tailwindcss --watch`). Justification :
  - Le projet n'a **aucun build tool** aujourd'hui ; introduire Vite/Webpack uniquement pour Tailwind est disproportionné.
  - Tailwind v4 utilise un moteur Rust (`Oxide`) — performances de build > v3, et ne nécessite pas de `tailwind.config.js` complexe (configuration CSS-first via `@theme`).
  - Le CLI standalone se distribue comme un exécutable unique, intégrable à un script `composer run` ou un simple `Makefile`, sans dépendances.
  - Pas de PostCSS = pas de chaîne `autoprefixer`/`postcss-nested` à entretenir.
  - Migration depuis Bootstrap 4 : la plupart des utilities (`d-flex`, `mb-3`, `text-center`, `col-md-8`) ont des équivalents 1-pour-1 (`flex`, `mb-3`, `text-center`, `md:w-2/3`).
- **PHP 8.1+ minimum** (à confirmer côté hébergeur prod).
- **Alpine.js** (3 KB) ou `<dialog>` HTML natif pour remplacer les modals Bootstrap. Évite de réintroduire un framework JS lourd.
- **Garder jQuery temporairement** pour la cohabitation (player.php, main.js) puis dégager en phase 2.

### Approche de refacto recommandée — **itératif par template**

| Argument | Détail |
|---|---|
| Pour itératif | Pas de staging → un big bang en prod est trop risqué. La cohabitation Bootstrap+Tailwind est techniquement faisable (préfixage Tailwind via `prefix: 'tw-'` ou cohabitation directe avec resets CSS désactivés). |
| Pour itératif | Le thème est petit (~1700 l. PHP, 900 l. CSS) mais chaque template a des patterns uniques → travailler template par template permet de valider visuellement à chaque étape. |
| Contre big bang | Aucun test, aucun snapshot visuel, aucun CI → impossible de garantir une non-régression sur 360+ pages mixtape sans QA manuelle massive. |

### Ordre d'attaque suggéré

1. **Phase 0 — Prérequis (1 jour)** : `git init`, `.gitignore`, commit initial "as-is", correction SEC-001/SEC-002 (régression sécurité bloquante).
2. **Phase 1 — Hygiène (2-3 jours)** : extraction des `<style>`/`<script>` inline, enqueue propre de tous les assets, suppression code mort (newsletter, fbq, IE9, `about.php`), suppression `.DS_Store`, `audit.md`/`CLAUDE.md`/`AUDIT.md` hors `wp-content/themes/` ou ignorés.
3. **Phase 2 — Refacto structurel (3-5 jours)** : extraire la "card mixtape" en `template-parts/card-mixtape.php`, factoriser, déplacer les queries hors templates vers `inc/queries.php`, fixer le text-domain, fixer `posts_per_page`, paginer.
4. **Phase 3 — Sécurité & perf bloquante (2-3 jours)** : nonces, `permission_callback`, rate-limit transients, cache des queries random, lazy loading images, `srcset`.
5. **Phase 4 — Migration Tailwind (5-7 jours)** : setup CLI v4, migrer template par template (commencer par `404.php` puis `text.php` — les plus simples), purger Bootstrap CSS au fur et à mesure.
6. **Phase 5 — A11y & polish (2-3 jours)** : focus visible, skip-links, landmarks, modals natifs, `prefers-reduced-motion`, contrastes.
7. **Phase 6 — Outillage** : `theme.json` minimal, ajout `add_theme_support`, éventuellement `phpcs` + WPCS, et un workflow CI simple (lint + check syntaxe PHP).

## 6. Glossaire & spécificités métier

| Terme | Sens dans le code |
|---|---|
| **Mixtape / Playlist** | Le post type WP `post`, renommé "Playlist" dans l'admin. Une mixtape = 1 article, avec un champ ACF `tracklist` (repeater de tracks YouTube/MP3) |
| **Tracklist** | Champ ACF `repeater` avec sous-champs `url` (YouTube ou MP3) et `track` (nom). Itéré dans `single.php:48-56` |
| **Curator / Guest** | L'auteur WP de la playlist. Listés sur `/guests/` (page-template `guests.php`) |
| **Highlight** | Champ ACF booléen ; si `true`, ajoute un emoji 🔥 devant le titre |
| **Color** | Champ ACF couleur ; appliqué en `background-color` inline sur les `<article>` mixtape |
| **Like / Dislike** | Champs ACF `likes_number`/`dislikes_number` incrémentés via REST `social/v2/...` |
| **author-1** | L'admin du site (ID=1) ; le CSS le masque explicitement dans la liste des curators (`list-of-mixtapes.css:40-42`) — c'est ce que la query `guests.php` essaie aussi de filtrer côté SQL |

## 7. Questions ouvertes

| # | Sujet | Décision attendue | Impact |
|---|---|---|---|
| 1 | **Migration vers un vrai CPT `mixtape`** | Garder le post type `post` relabellisé pour ce refacto (décidé). Migration ultérieure à trancher. | URLs (permaliens), BDD (`wp_posts.post_type`), redirections SEO 301, exports/imports ACF, sitemap Rank Math, tous les `wp_get_recent_posts`/`WP_Query` à mettre à jour |
| 2 | **Audit des plugins custom (`chckr-yt`, `allow-multiple-accounts`)** | Hors périmètre, à planifier en **phase 2** | Inconnu tant que non audité ; `chckr-yt` semble lié au check des liens YouTube de la tracklist (probable cron + ACF), `allow-multiple-accounts` autorise plusieurs comptes par email (impact RGPD/auth) |
| 3 | **Version PHP cible en prod** | À confirmer auprès de l'hébergeur (recommandé : ≥ 8.1, idéalement 8.2) | Conditionne l'usage de syntaxes modernes (constructor promotion, enums, readonly) dans le refacto |
| 4 | **Contraintes éditoriales sur les couleurs `get_field('color')`** | Définir une palette validée (contraste WCAG AA garanti sur fond `#333` et sur texte blanc) | Aujourd'hui les curators saisissent une couleur libre via ACF → contraste non garanti |
| 5 | **Rate-limit des likes** | Choisir une stratégie : 1 like/IP/post (transient), captcha, ou laisser ouvert (cosmétique) | Détermine si on garde ou on supprime la feature like/dislike |
| 6 | **Newsletter** | Suppression confirmée (code mort) | Aucun (rien à faire côté contenu) |
| 7 | **`fbq` Pixel** | Suppression confirmée (résidu) | Aucun |
| 8 | **Cookies / RGPD** | Définir si Umami suffit (sans bandeau, car analyse anonyme), ou si un bandeau est requis par politique éditoriale | Conformité RGPD/CNIL |

## 8. Règles pour les futures sessions Claude Code

### Règle transversale — Aucun changement visuel sans validation
Aucune modification ne doit altérer le rendu UI/UX du site. Le refacto est strictement structurel, sécuritaire et de performance. Si une modification risque un changement visuel, arrête, annonce, attends validation. Le polishing UI mineur est possible mais doit être validé séparément.

### Bootstrap obligatoire avant tout refacto
1. **Premier travail à faire avant toute modification** : `git init`, écrire un `.gitignore` (au minimum : `.DS_Store`, `node_modules/`, `vendor/`, `.idea/`, `*.log`, `wp-config.php`), faire un **commit initial "as-is"** du thème. Sans cela, tout refacto est non-réversible — interdit.
2. Ne **jamais modifier en prod directement**. Le workflow sera : dev local → commit → push → déploiement sur prod (workflow à formaliser après init git).

### Conventions à respecter dans le refacto
- **Préfixe thème** : `lmt_` (pour `lamixtape`) sur toutes les nouvelles fonctions, classes, hooks, filters, options, transients, et noms de handles enqueue.
- **Text-domain** : remplacer `'text-domain'` par `'lamixtape'` partout (un seul `find/replace`) ET ajouter `load_theme_textdomain('lamixtape', get_template_directory() . '/languages')` dans `after_setup_theme`.
- **Enqueue** : tous les assets passent par `wp_enqueue_style/script` dans `wp_enqueue_scripts`. Aucun `<link>`/`<script>` en dur dans les templates. Aucun `@import` CDN dans `style.css`.
- **Échappement** : conserver l'usage systématique de `esc_*` dans les templates. Toute query custom passe par `$wpdb->prepare()`.
- **Templates parts** : extraire toute UI répétée (carte mixtape) en `template-parts/` via `get_template_part()`.
- **Pas de logique métier dans les templates** : les `WP_Query`, `add_filter('posts_where', ...)`, etc., vivent dans `inc/` ou dans des classes dédiées.
- **Tailwind** : v4 + CLI standalone, configuration CSS-first via `@theme`. Pas de Node en prod. Cohabitation Bootstrap acceptable pendant la phase de migration, mais à supprimer template par template.

### Fichiers sensibles à ne pas toucher sans validation explicite
- `functions.php` lignes 105-132 (renommage `post` → "Playlist") : changer ces strings = impacter tous les utilisateurs admin habitués.
- Les champs ACF (`color`, `highlight`, `likes_number`, `dislikes_number`, `tracklist`) : noms à conserver à l'identique (utilisés dans 360+ posts).
- L'endpoint REST `social/v2/likes|dislikes` : si on change la route, `js/main.js` doit suivre **et** les compteurs existants doivent être préservés (champ ACF `likes_number`).
- `wp-config.php` (hors thème) : ne jamais modifier sans confirmation.

### Workflow de travail attendu
- Toute modification = commit dédié, message conventionnel (`fix:`, `refactor:`, `perf:`, `feat:`, `chore:`, `docs:`).
- Toute migration de template = commit séparé, avec capture d'écran avant/après en commentaire de PR (ou note dans le commit).
- Toute suppression de code = précédée d'une recherche `grep`/`rg` pour confirmer l'absence d'usage.
- Tester en local sur **au moins** : `index`, `single` (mixtape), `category`, `search`, `404`, `explore`, `guests` avant de pousser.
- Si une décision business est requise, **arrêter et demander** plutôt que présumer.

### Ce que le thème **n'est pas**
- Pas un FSE / Block theme.
- Pas un child theme.
- Pas un thème Composer-installé.
- Pas un thème Sage/Roots.
- Pas testé (aucun PHPUnit, aucun snapshot, aucun Playwright).
- Pas linté (aucun phpcs, aucun ESLint).
