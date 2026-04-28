# Prompt Claude Code — Phase 2 : Refacto structurel PHP

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. La Phase 1 doit être close (32 commits poussés sur `origin/main`, dernier commit `bf2aee6`, 8 captures post-Phase-1 commitées dans `_docs/captures-post-phase-1/`, diff visuel pre/post Phase 1 = 0 confirmé côté product).

---

## Règle transversale (toutes phases — déjà en vigueur)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.** Toute modification est strictement structurelle, sécuritaire ou de performance. Si une modif risque de produire une différence visuelle, tu **arrêtes**, l'annonces, et attends validation avant de poursuivre. Cette Phase 2 est **100 % refacto structurel PHP** : les captures `_docs/captures-post-phase-1/` SONT les captures pre-Phase-2 et doivent être identiques aux captures post-Phase-2.

Cette règle est déjà documentée dans `CLAUDE.md` section 8 ("Règles pour les futures sessions Claude Code").

## Contexte

`CLAUDE.md` et `_docs/AUDIT.md` à jour. PHP 8.2 confirmé en prod. Workflow git établi : commit-par-commit, push après chaque commit, validation utilisateur entre commits critiques. Remote SSH `git@github.com:nearmint/Lamixtape.git`. Branche `main`. Convention `**Statut**` dans AUDIT.md à appliquer en fin de chaque finding résolu.

## Objectif Phase 2

Préparer le thème à un refacto Tailwind sain (Phase 4) en :
1. Adoptant un text-domain réel (`lamixtape`) au lieu du placeholder `'text-domain'`
2. Renommant toutes les fonctions thème en `lmt_*` avec docblocks PHPDoc
3. Extrayant la logique métier (queries SQL, filtres) vers `inc/queries.php` (flat files)
4. Factorisant la "card mixtape" dupliquée 4× en `template-parts/card-mixtape.php`
5. Ajoutant `theme.json` minimal et `add_theme_support` étendu
6. Bénéfice gratuit en passant : fix de PERF-007, PERF-008, PERF-009 (touchent les mêmes lignes que QC-002)

Findings ciblés (cf. `_docs/AUDIT.md`) :
- **Hautes** : QC-002 (logique métier templates), QC-003 (card mixtape ×4), QC-004 (text-domain), WP-002 (theme.json), WP-003 (add_theme_support)
- **Moyennes** : QC-008 (naming + docblocks), WP-007 (wp_reset_postdata), PERF-007, PERF-008, PERF-009 (absorbés par QC-002)
- **Basses** : WP-008 (responsive-embeds), WP-009 (wp_change_search_url)

À l'issue de la phase :
- Le thème est traduisible (`lamixtape` text-domain + load_theme_textdomain)
- Toutes les fonctions thème portent le préfixe `lmt_*` et un docblock PHPDoc
- La logique métier vit dans `inc/` (pas dans les templates)
- 1 seule définition de la "card mixtape" (template-parts/)
- Les queries critiques sont bornées (plus de `posts_per_page=1000000`)
- `theme.json` minimal en place
- `add_theme_support` couvre title-tag, html5, automatic-feed-links, responsive-embeds, editor-styles
- 0 différence visuelle vs `_docs/captures-post-phase-1/`

**Findings explicitement HORS périmètre Phase 2** :
- **Q9 (suppression module commentaires)** → Phase 2.5 dédiée après Phase 2
- **PERF-001 (home pagination)** → Phase 3 (changement UX, mérite discussion dédiée)
- **PERF-002 (single.php previous-mixtapes posts_per_page=1000000 → borné)** → Phase 3, traité conjointement avec PERF-001 (même problème de fond = pagination catalogue, même discussion UX load-more / paginate_links / infinite). Borner à 30 maintenant violerait la règle "no visual change" (la liste perd 330+ entrées).
- **A11y, Tailwind, autres PERF, autres OTHER** → Phases 3-6

## Décisions structurantes (déjà actées en discussion stratégique pré-Phase-2)

| ID | Décision | Application |
|---|---|---|
| **D1** | Q9 commentaires reportée Phase 2.5 dédiée | Ne toucher à RIEN de la chaîne commentaires (comments.php, callback `tape_comment`, `my_update_comment_fields/field`, `css/comment-form.css`, `comments_template()` calls) en Phase 2 |
| **D2** | Naming refactor = `lmt_*` prefix + docblocks PHPDoc, **pas** de namespace OOP | Garder le procédural, juste préfixer + documenter |
| **D3** | `theme.json` minimal : `version: 2` + `settings.layout.contentSize` uniquement | Pas de palette colorimétrique, pas de fontFamilies (réservé Phase 4 Tailwind) |
| **D4** | PERF-007, PERF-008, PERF-009 inclus dans Phase 2 (couplés à QC-002). **PERF-002 (single.php previous-mixtapes 1M) explicitement reporté à Phase 3** avec PERF-001 — même problème UX de fond, refus du changement visuel en Phase 2 | `single.php` previous-mixtapes garde `posts_per_page = -1` en 2.6 C2 |
| **D5** | Pagination strategy (PERF-001 + PERF-002) sans objet pour Phase 2 | Décision repoussée à Phase 3, indication préliminaire = load-more |
| **D6** | Structure `inc/` = **flat files** (pas de classes) | `inc/queries.php`, `inc/setup.php`, etc. selon besoin |
| **D7** | `add_theme_support('title-tag')` activé en 2.3 avec **tests Rank Math obligatoires** (R4) | Voir étape 2.3 pour la procédure de test |

---

## Étape 2.0 — Préparation

### 2.0.1 Lecture obligatoire

Avant tout commit, lire intégralement :
- `CLAUDE.md` (mémoire de travail, conventions, décisions actées)
- `_docs/AUDIT.md` (findings + statuts à jour)

Référencer les IDs de findings (`QC-002`, `QC-003`, `QC-004`, `QC-008`, `WP-002`, `WP-003`, `WP-007`, `WP-008`, `WP-009`, `PERF-007`, `PERF-008`, `PERF-009`) dans tous les messages de commit. **PERF-002 hors scope** (reporté Phase 3).

### 2.0.2 Captures de référence

**Pas de re-capture nécessaire** : `_docs/captures-post-phase-1/` SONT les captures pre-Phase-2 (Phase 1 close avec garantie no-visual-change tenue à 100 %, donc l'état visuel actuel est strictement identique).

**Exception — étape 2.7 (card mixtape)** : risque visuel R2 identifié (4 templates avec micro-variations `delay-2`/`-3`/`-7`, `mr-n3` parfois absent, etc.). **Re-capture spécifique des 4 templates concernés** (`/`, single mixtape, `/category/<une-cat>/`, `/search/<terme>`) à prendre **avant** d'attaquer 2.7, pour comparaison fine. Aucune re-capture pour les autres étapes.

### 2.0.3 Confirmation utilisateur

Confirmer à l'utilisateur :
- Lecture CLAUDE.md + AUDIT.md OK
- Décisions D1-D7 bien intégrées
- Plan d'attaque 2.1 → 2.10 (sans 2.8 PERF-001 ni 2.9 Q9)
- Captures pre-Phase-2 = `_docs/captures-post-phase-1/`

**Attendre GO avant 2.1.**

---

## Étape 2.1 — Trivial wins (WP-007, WP-008, WP-009)

3 fixes mécaniques à très faible risque. Regroupables en 1 ou 3 commits selon la sensibilité atomique du moment.

### 2.1.1 WP-007 — `wp_reset_postdata` après loop custom dans `single.php`

`single.php` lignes ~115-117 : `foreach ($pageposts as $post) { setup_postdata($post); ... }` se termine sans `wp_reset_postdata()`. Ajouter `wp_reset_postdata();` après le `endforeach;` pour restaurer le `$post` global (sinon pollution pour `get_footer()` et widgets).

Commit : `fix(php): reset postdata after custom loop in single.php (WP-007)`.

### 2.1.2 WP-008 — `add_theme_support('responsive-embeds')`

Inclus naturellement dans 2.3 (add_theme_support étendu). Pas de commit séparé.

### 2.1.3 WP-009 — `wp_change_search_url` — `urlencode` → `rawurlencode`

`functions.php` (ex-ligne 56-62, à re-grep) : `wp_safe_redirect(get_home_url(...) . urlencode(get_query_var('s')))`. Si `s=` contient déjà des caractères encodés, double encoding (`%2520`). Remplacer `urlencode` par `rawurlencode` (ou mieux : `add_query_arg`).

Commit : `fix(php): use rawurlencode in search redirect (WP-009)`.

### 2.1.4 Validation

Test rapide : single mixtape (vérifier que la liste des "anciennes mixtapes" affiche correctement et que le footer/widgets après ne sont pas pollués) + recherche avec un terme contenant un espace ou un caractère spécial (`/search/foo bar` ou `/search/jazz fusion`) → URL finale propre.

**Attendre GO avant 2.2.**

---

## Étape 2.2 — `theme.json` minimal (WP-002)

Créer `theme.json` à la racine du thème avec **uniquement** :

```json
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 2,
    "settings": {
        "layout": {
            "contentSize": "1140px",
            "wideSize": "1320px"
        }
    }
}
```

Pas de `color.palette`, pas de `typography.fontFamilies`, pas de `spacing` (réservés à Phase 4 Tailwind où ils auront leur source de vérité).

Commit : `feat(theme): add minimal theme.json (WP-002)`.

### Validation

- Aucun changement visible côté frontend (theme.json sans palette = pas d'override)
- Côté admin Gutenberg : la sidebar éditeur peut afficher le `contentSize` comme indicateur, sans changer le rendu réel
- Vérifier qu'aucune erreur PHP n'apparaît à l'activation du thème

**Attendre GO avant 2.3.**

---

## Étape 2.3 — `add_theme_support` étendu (WP-003)

Ajouter dans `functions.php` une fonction `lmt_setup_theme()` hookée sur `after_setup_theme` :

```php
function lmt_setup_theme() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'html5', array( 'comment-form', 'comment-list', 'gallery', 'caption', 'search-form', 'style', 'script' ) );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    // 'post-thumbnails' déjà actif depuis le commit initial
}
add_action( 'after_setup_theme', 'lmt_setup_theme' );
```

L'ancien `add_theme_support('post-thumbnails')` au top de `functions.php` peut être déplacé dans `lmt_setup_theme()` pour cohérence (1 seul endroit) **ou** laissé en place (pas de bug). Choix au moment du commit, à signaler.

### Tests R4 obligatoires (Rank Math `<title>`)

**Avant push, tester** :

1. **Test 1 — Rank Math actif** :
   - Charger home, single mixtape, category, search, 404 (5 templates)
   - View Page Source → vérifier `<title>...</title>` dans le `<head>`
   - **Attendu** : 1 seul `<title>`, contenu généré par Rank Math (pas du WP par défaut)
   - Drapeau rouge : 2 `<title>` dans le source (= conflit Rank Math + WP)

2. **Test 2 — Rank Math désactivé temporairement** (depuis l'admin WP) :
   - ⚠️ **Désactivation de Rank Math UNIQUEMENT sur l'environnement Local. NE JAMAIS désactiver Rank Math sur la prod pendant ce test.** Si pas d'environnement Local fonctionnel, **sauter ce Test 2** et noter le risque dans le message de commit (`Rank Math fallback test SKIPPED — no Local env available`).
   - Charger les 5 mêmes pages
   - **Attendu** : 1 `<title>` généré par WP via `wp_get_document_title()`, contenu cohérent (titre du post + nom du site)
   - Drapeau rouge : pas de `<title>` (= title-tag pas pris en compte)
   - **Réactiver Rank Math immédiatement après le test**

3. **Test 3 — vérifier responsive-embeds** : insérer un embed YouTube dans le contenu d'un post via Gutenberg → l'embed doit être responsive (s'adapter à la largeur).

Commit : `feat(theme): add_theme_support title-tag, html5, embeds, editor-styles (WP-003, WP-008)`.

**Attendre GO après tests R4 réussis avant 2.4.**

---

## Étape 2.4 — Text-domain global (QC-004)

### 2.4.1 Inventaire avant modif

Grep préventif :
```bash
grep -rn "'text-domain'" --include="*.php" .
grep -rn '"text-domain"' --include="*.php" .
```

Compter le nombre exact d'occurrences. Présenter à l'utilisateur **avant** find/replace.

### 2.4.2 Find/replace global

Remplacer `'text-domain'` → `'lamixtape'` (et `"text-domain"` → `"lamixtape"`) dans tous les `*.php` du thème (y compris `functions.php`).

Outil : `perl -i -pe "s/'text-domain'/'lamixtape'/g" *.php` ou équivalent. **Re-grep après pour confirmer 0 occurrence restante.**

### 2.4.3 `load_theme_textdomain`

Ajouter dans `lmt_setup_theme()` (créée en 2.3) :

```php
load_theme_textdomain( 'lamixtape', get_template_directory() . '/languages' );
```

Créer le dossier `languages/` (vide pour l'instant — un fichier `.pot` peut être généré plus tard via WP-CLI `wp i18n make-pot`). Optionnel : ajouter un `.gitkeep` pour tracker le dossier vide.

### 2.4.4 Validation

- Site charge sans erreur PHP
- Plugin de traduction (Loco Translate, WPML) reconnaît le text-domain `lamixtape` et propose de générer les `.po`
- Aucun changement visuel (les strings sont en anglais en dur, pas encore de fichier de traduction)

Commit unique : `feat(i18n): replace 'text-domain' placeholder with 'lamixtape' + load_theme_textdomain (QC-004)`.

**Attendre GO avant 2.5.**

---

## Étape 2.5 — Naming `lmt_*` + docblocks PHPDoc (QC-008)

Le plus mécanique mais avec une grosse surface : ~15 fonctions à renommer + leurs `add_filter`/`add_action` callbacks à mettre à jour.

### 2.5.1 Inventaire

Grep toutes les fonctions définies dans `functions.php` :
```bash
grep -nE "^function " functions.php
```

Lister chaque fonction avec son préfixe actuel et son rename cible :

| Actuel | Cible `lmt_*` |
|---|---|
| `cf_search_join` | `lmt_search_postmeta_join` |
| `cf_search_where` | `lmt_search_postmeta_where` |
| `cf_search_distinct` | `lmt_search_distinct` |
| `SearchFilter` | `lmt_search_post_type_filter` |
| `wp_change_search_url` | `lmt_search_url_redirect` |
| `revcon_change_post_label` | `lmt_relabel_post_menu` |
| `revcon_change_post_object` | `lmt_relabel_post_object` |
| `wpb_remove_version` | `lmt_remove_generator_version` |
| `no_wordpress_errors` | `lmt_obfuscate_login_errors` |
| `posts_link_attributes_1` | `lmt_post_link_class_prev` |
| `posts_link_attributes_2` | `lmt_post_link_class_next` |
| `tape_comment` | `lmt_comment_callback` |
| `my_update_comment_fields` | `lmt_comment_form_fields` |
| `my_update_comment_field` | `lmt_comment_form_textarea` |
| `wcs_post_thumbnails_in_feeds` | `lmt_rss_post_thumbnail` |
| `my_deregister_scripts` | `lmt_deregister_wp_embed` |
| `wps_deregister_styles` | `lmt_deregister_block_library_css` |

(liste à valider par grep réel — peut varier selon ce qui reste après Phase 1)

**Important** : `lmt_enqueue_assets`, `lmt_social_like`, `lmt_social_like_permission`, `lmt_setup_theme` (ajoutée en 2.3) sont déjà conformes — **ne pas re-renommer**.

### 2.5.2 Stratégie de rename

Pour chaque rename : modif **double** (la `function name()` ET son `add_filter('hook', 'name')`/`add_action('hook', 'name')`). Sinon le hook ne fire plus.

Vérification post-rename : `grep -n "old_name" .` doit retourner 0 résultat dans le code (peut subsister dans CLAUDE.md / AUDIT.md / commit messages — historique légitime).

### 2.5.3 Docblocks PHPDoc

Pour chaque fonction renommée, ajouter un docblock minimal :

```php
/**
 * Brief one-line description.
 *
 * @param  type  $param  Description.
 * @return type
 */
function lmt_xxx( $param ) {
```

Pas de prose excessive — juste le contrat (params + return). Les fonctions de hook qui retournent `void` peuvent omettre `@return`.

### 2.5.4 Découpage en commits

Stratégie atomique : **groupes thématiques cohérents** plutôt que 1 commit par fonction (15 commits = trop de bruit).

Découpage proposé :
1. `refactor(php): rename and docblock search-related functions to lmt_* (QC-008)` — `cf_search_*`, `SearchFilter`, `wp_change_search_url`
2. `refactor(php): rename and docblock backoffice/admin functions to lmt_* (QC-008)` — `revcon_*`, `no_wordpress_errors`
3. `refactor(php): rename and docblock head cleanup functions to lmt_* (QC-008)` — `wpb_remove_version`, `my_deregister_scripts`, `wps_deregister_styles`
4. `refactor(php): rename and docblock comment-related functions to lmt_* (QC-008)` — `tape_comment`, `my_update_comment_*`
5. `refactor(php): rename and docblock RSS/feed functions to lmt_* (QC-008)` — `wcs_post_thumbnails_in_feeds`, `posts_link_attributes_*`

5 commits cohérents, chacun avec validation.

### 2.5.5 Validation

Après chaque commit du 2.5 : tester que le site charge sans erreur PHP (notamment "function not defined" sur les hooks). Recharger 2-3 templates.

**Attendre GO entre chaque sous-commit du 2.5 (étape la plus à risque mécanique).**

---

## Étape 2.6 — Création `inc/queries.php` + extraction queries (QC-002 + PERF-007, PERF-008, PERF-009)

**Cœur métier de Phase 2.** Étape la plus structurante.

### 2.6.1 Création de la structure `inc/`

```
inc/
└── queries.php   # toutes les WP_Query custom du thème
```

Optionnellement créer aussi `inc/setup.php` pour y déplacer `lmt_setup_theme()` et autres fonctions de configuration générique — à discuter au moment où on l'attaque (peut rester dans `functions.php` si on veut limiter le scope).

### 2.6.2 Extraction des 3 queries identifiées

Présenter à l'utilisateur **avant** chaque extraction :

#### A. `single.php:99-111` — query "anciennes mixtapes"

Aujourd'hui : `WP_Query("paged=...&order=DESC&posts_per_page=1000000")` + `add_filter('posts_where', 'filter_where')` redéclarée à chaque rendu (PERF-009).

Cible : fonction `lmt_get_previous_mixtapes( $current_post_id )` dans `inc/queries.php` :
- Closure pour le `posts_where` (pas de fonction globale → règle PERF-009)
- **`posts_per_page` reste à `-1`** (commentaire PHP obligatoire dans le code : `// PERF-002 tracked, pagination strategy in Phase 3 with PERF-001`). Borner à 30 maintenant retirerait 330+ mixtapes de la liste affichée → changement visuel, interdit en Phase 2.
- Retourne un `WP_Query` ou un array de `WP_Post`
- Le commit C2 traite **QC-002 (extraction) + PERF-009 (closure)** uniquement. PERF-002 reste ouvert.

`single.php` consomme : `$previous = lmt_get_previous_mixtapes(get_the_ID()); foreach ($previous as $post) { ... }`.

#### B. `search.php:24-29` — query de recherche

Aujourd'hui (post-Phase-1 fix QC-005) : `new WP_Query(['s' => get_search_query(false), 'posts_per_page' => -1])`.

Cible : fonction `lmt_get_search_results()` dans `inc/queries.php` qui encapsule la query (toujours `posts_per_page = -1` — limit déjà discuté, pagination Phase 3).

`search.php` consomme : `$results = lmt_get_search_results(); if ($results->have_posts()) { ... }`.

#### C. `guests.php:14-34` — query users + posts par auteur (PERF-008 N+1)

Aujourd'hui : `$wpdb->get_results()` brute (SQL custom interpolé, déjà flagué SEC-003) + N WP_Query par auteur dans la loop d'affichage.

Cible : 2 fonctions dans `inc/queries.php` :
- `lmt_get_curators()` — via `get_users(['exclude' => [1], 'orderby' => 'nicename', 'fields' => ['ID', 'user_nicename', 'nickname']])`. Règle SEC-003 (SQLi théorique) en bonus.
- `lmt_get_posts_grouped_by_author()` — **1 seule** `get_posts(['posts_per_page' => -1, 'post_status' => 'publish'])` puis grouping PHP par `post_author` → fix PERF-008.

`guests.php` consomme : 1 boucle sur curators, 1 sous-boucle sur les posts groupés (déjà chargés, plus de N+1).

### 2.6.3 Découpage en commits

| # | Commit | Findings |
|---|---|---|
| C1 | `refactor(php): create inc/queries.php scaffold + load from functions.php` | structure |
| C2 | `refactor(php): extract previous-mixtapes query to inc/queries.php (QC-002, PERF-009)` | single.php |
| C3 | `refactor(php): extract search query to inc/queries.php (QC-002)` | search.php |
| C4 | `refactor(php): extract guests query, fix N+1 (QC-002, PERF-008, SEC-003)` | guests.php |

4 commits, validation utilisateur après chaque.

### 2.6.4 Validation par commit

- **C1** : site charge sans erreur PHP (le require/include de `inc/queries.php` depuis `functions.php` doit fonctionner)
- **C2** : single mixtape → la liste des "anciennes mixtapes" affiche **strictement à l'identique** (même nombre d'entrées, même ordre, même rendu). PERF-002 reste ouvert volontairement (cf. D4).
- **C3** : `/search/<terme>` → résultats identiques à avant
- **C4** : `/guests/` → liste des curators + leurs mixtapes, identique visuellement, mais avec ~50× moins de queries SQL (vérifiable via Query Monitor plugin si installé)

**Attendre GO entre chaque sous-commit du 2.6.**

---

## Étape 2.7 — Card mixtape → `template-parts/card-mixtape.php` (QC-003)

### 2.7.1 Re-capture pre-extraction

**Obligatoire** vu R2 : prendre 4 captures spécifiques (home, single, category, search) **avant** l'extraction, pour comparaison fine post-extraction. Stocker dans `_docs/captures-pre-2.7/`.

### 2.7.2 Inventaire des micro-variations

Comparer les 4 instances de la "card mixtape" :
- `index.php:43-60`
- `single.php:118-134` (boucle "anciennes mixtapes")
- `category.php:33-58`
- `search.php:30-46`

Pour chaque instance, lister :
- Classes CSS différentes (`fade-in delay-2` vs `delay-3` vs `delay-7`)
- Classes additionnelles (`mr-n3` parfois absent, `d-none d-sm-none d-md-none d-lg-block` absent dans single)
- Présence ou non de l'icône highlight
- Présence ou non du nom du curator (selon breakpoint visible)
- Bracket de la title (`<a>` autour ou pas)

Présenter le tableau à l'utilisateur **avant** l'extraction.

### 2.7.3 Création `template-parts/card-mixtape.php`

Template paramétrable via `$args` (passé par `get_template_part('template-parts/card-mixtape', null, $args)`) :

```php
// $args attendu :
// - 'delay'             => int (1, 2, 3, 7…) pour la classe fade-in delay-N
// - 'show_curator'      => bool (true par défaut, false sur certains breakpoints)
// - 'show_highlight'    => bool (true par défaut)
// - 'extra_classes'     => string (classes supplémentaires)
```

Le contenu PHP du template-part lit `$args` et applique les bonnes variations.

### 2.7.4 Refacto des 4 templates

Remplacer les 4 instances par :
```php
get_template_part( 'template-parts/card-mixtape', null, array(
    'delay'          => 3,        // ou 2, ou 7 selon le contexte
    'show_curator'   => true,
    'extra_classes'  => '',
));
```

Commit unique : `refactor(templates): extract card-mixtape to template-parts (QC-003)`.

### 2.7.5 Validation

**Diff visuel pixel-précis** sur les 4 captures pre-extraction (stockées en 2.7.1) vs post-extraction. **0 différence attendue.**

Tester aussi :
- Click sur une card → permalink correct
- Hover effects (transitions, animations) identiques

Si une différence apparaît : rollback `git revert` immédiat, on creuse les variations manquées. **Pas de fix spéculatif.**

**Attendre GO avant 2.10.**

---

## Étape 2.10 — Closure Phase 2

### 2.10.1 Mise à jour `_docs/AUDIT.md`

Pour chaque finding fermé en Phase 2, ajouter le bloc `**Statut** : Résolu Phase 2 (SHA + ...)` à la fin de sa section (convention établie). Findings concernés : QC-002, QC-003, QC-004, QC-008, WP-002, WP-003, WP-007, WP-008, WP-009, PERF-007, PERF-008, PERF-009, SEC-003 (bonus via 2.6 C4). **PERF-002 reste ouvert** (reporté Phase 3 avec PERF-001).

### 2.10.2 Mise à jour `CLAUDE.md`

Section 4 (Dette) : recompter les findings résolus, mettre à jour la table avec les nouveaux totaux (résolus / restants).

Nouvelle subsection "Phase 2 close — récap" sur le modèle Phase 1 close :
- Date
- Métriques (commits, fichiers, lignes, findings résolus)
- Bonus business / surprises éventuelles
- Apprentissages clés
- Pointeur Phase 2.5 (commentaires) puis Phase 3 (perf)

### 2.10.3 Captures finales

Captures **post-Phase-2** des 8 templates de référence par l'utilisateur. Stocker dans `_docs/captures-post-phase-2/`. Diff visuel manuel vs `_docs/captures-post-phase-1/` → **0 différence attendue** (refacto pur structurel).

Si diff : à investiguer **avant** clôture officielle Phase 2 côté product.

### 2.10.4 Commit final + push

Commit unique : `docs: close Phase 2 (audit statuses + CLAUDE.md update)`.

Confirmer à l'utilisateur que Phase 2 est close et **attendre son feu vert** avant Phase 2.5 (Q9 commentaires) puis Phase 3 (perf).

---

## Règles de travail (rappel — déjà en vigueur depuis Phase 0)

- **Lecture obligatoire de `CLAUDE.md` et `_docs/AUDIT.md`** au démarrage
- **Commit-par-commit** avec push après chaque commit (workflow validé)
- **Validation utilisateur** entre chaque sous-étape risquée (rythme Phase 1)
- **Discipline diagnostic-d'abord** : si un bug inattendu apparaît, présenter le diagnostic (cause + impact + options de fix) **avant** d'écrire la moindre ligne de code
- **Si une décision business émerge** (ex. limite de pagination, format des labels back-office) → arrêter et demander
- **Aucun changement visuel** (règle transversale, voir tête de prompt)
- **`**Statut**`** dans AUDIT.md à ajouter en fin de chaque finding fermé
- **Ne pas toucher** : commentaires (Q9 reportée), pagination home (PERF-001 reportée), text-domain `'text-domain'` est traité en 2.4 et nulle part ailleurs
- **Préfixe `lmt_*`** sur toute nouvelle fonction (déjà convention)

## Checkpoint final de Phase 2

À la fin de la phase, produire un récap synthétique :

| Élément | Statut |
|---|---|
| WP-002 (theme.json) | SHA + diff stat |
| WP-003 (add_theme_support) | SHA + diff stat + résultats tests R4 |
| WP-007 / WP-008 / WP-009 | SHA + diff stat |
| QC-004 (text-domain) | SHA + nb occurrences remplacées |
| QC-008 (naming + docblocks) | Liste des SHA + nb fonctions renommées |
| QC-002 + PERF-007/008/009 (PERF-002 reste ouvert, reporté Phase 3) | Liste des SHA + structure `inc/` |
| QC-003 (card mixtape) | SHA + 4 templates refacto + résultat diff visuel |
| `CLAUDE.md` mis à jour ? | Oui/Non + sections |
| `_docs/AUDIT.md` Statuts ajoutés ? | Oui/Non + liste IDs |
| Captures post-Phase-2 confirment 0 diff visuel ? | Oui/Non (utilisateur) |
| Findings restants pour Phase 2.5 (Q9) puis Phase 3 | Liste IDs |

Confirmer Phase 2 close, **attendre GO avant Phase 2.5**.
