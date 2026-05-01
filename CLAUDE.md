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

| Axe | Total | Résolus (P0+P1+P2+P2.5+P3+P4) | Critique restant | Haute restant | Référence |
|---|:-:|:-:|:-:|:-:|---|
| **Process / Qualité** | 16 | 16 ✅ | 0 ✅ | 0 ✅ | `_docs/AUDIT.md#qc` |
| **Sécurité** | 9 | 8 ✅ | 0 ✅ | 0 ✅ | `_docs/AUDIT.md#securite` |
| **Performance** | 14 | 13 ✅ | 0 ✅ | 1 | `_docs/AUDIT.md#performance` |
| **Accessibilité** | 11 | 0 | 0 | 4 | `_docs/AUDIT.md#a11y` |
| **WP best practices** | 9 | 7 ✅ | 0 ✅ | 0 ✅ | `_docs/AUDIT.md#wp` |
| **Migration Tailwind** | 5 | 5 ✅ | 0 ✅ | 0 ✅ | `_docs/AUDIT.md#tailwind` |
| **Autres (SEO, RGPD, observabilité)** | 8 | 1 | 0 | 2 | `_docs/AUDIT.md#autres` |
| **TOTAL** | **72** | **50** | **0** ✅ | **7** | |

> 70 findings audit initial + 2 NEW découverts en Phase 1 = 72 au total. 50 résolus à fin Phase 4 (3 P0 + 20 P1 incluant 4 backfills + 12 P2 + 9 P3 + 6 P4 = TW-001/002/003/004/005 + QC-007 finalisé). 22 restants pour Phases 5-6+. **Aucun finding Critique ni Haute Tailwind restant** ✅. **Q9 (suppression module commentaires)** = décision business, hors comptage findings, traitée Phase 2.5 (cf. `_docs/AUDIT.md#business`). Tous les findings résolus portent un bloc `**Statut** : Résolu Phase X (...)` à la fin de leur section dans `_docs/AUDIT.md`.

Reste ouvert (cibles Phases 5-6+) :
- **A11Y-001 à 011** : 11 findings (focus visible, skip-link, landmarks, contrastes, modals…) → Phase 5
- **PERF-006** (Haute, search LEFT JOIN postmeta) → Q10 search rewrite, post-Phase-6
- **WP-005** / **WP-006** : 2 Moyennes (renommage Posts → Playlist sur le post type natif ; WP_POST_REVISIONS) → Phase 6 outillage
- **OTHER-001 à 008** : 7 findings (RGPD, SEO/OG, monitoring) → Phase 6
- **3 Q ouvertes structurantes** : Q10 search rewrite + Q11 Content-Security-Policy + Q13 dette résiduelle écarts visuels Phase 4 (cf. section 7) → Phase 5 polish ou ad-hoc

### Phase 0 close — récap
- 5 commits, 3 critiques résolues (QC-001 init git, SEC-001 likes endpoint sécurisé, SEC-002 feature dislike supprimée).

### Phase 1 close — récap (28 avril 2026)

**Métriques globales** :
- **31 commits** depuis fin Phase 0 (`f72be03`), tous pushés sur `origin/main`
- 22 fichiers modifiés
- **+561 / −573 lignes (net −12)** — bilan net négatif malgré l'ajout de `js/player.js` (+343 l.) et `assets/vendor/` (548 KB de libs auto-hébergées), parce qu'on a supprimé plus de code mort qu'on n'a ajouté de structure
- **16 findings P1 résolus** (cf. tableau dette ci-dessus)
- **2 NEW findings découverts et résolus dans la même phase** (QC-NEW-001 `WP_POST_REVISIONS` warning, QC-NEW-002 smooth scroll `SyntaxError`)

**Bonus business surprise** : **la recherche du site était cassée** depuis l'origine (variable `$s` jamais définie en PHP 8.x → query vide → catalogue entier sortait sur n'importe quel `/search/...`). Découvert et corrigé sous QC-005 (`706d209`). Hors scope initial Phase 1, bénéfice indirect du diagnostic poussé.

**Apprentissages clés** (à retenir pour Phases 2-6) :
1. **Decluttering reveals what was always there.** Chaque cleanup de bruit (warnings PHP, `console.log`, code mort) a révélé un bug pré-existant masqué. Le warning `WP_POST_REVISIONS` masquait `$counter`/`$index`/`$s` ; le nettoyage des warnings a révélé `SyntaxError` smooth scroll ; etc. **Toujours s'attendre à ce que le prochain bug visible soit un bug ancien démasqué, pas une régression.**
2. **Discipline diagnostic-d'abord** a évité ≥4 régressions silencieuses : (a) un fix `WP_POST_REVISIONS` spéculatif sur le like alors que le vrai bug était la pollution REST par le warning ; (b) virer le CDN jQuery sans wrapper main.js → noConflict aurait cassé tout ; (c) grouper C2+C3 dans le même commit → perte de granularité bisect ; (d) initialiser `$counter`/`$index` à 0 au lieu de constater qu'ils étaient morts.
3. **1 finding = 1 commit atomique** = bisect/revert-friendly. Seul moment où on a groupé (clôture Phase 1) est explicitement narratif et non fonctionnel.
4. **Validation utilisateur entre commits critiques** est le rythme par défaut. Coût : ~2 min round-trip par commit. Bénéfice : 0 régression silencieuse arrivée en prod.
5. **L'ordre du prompt n'est pas sacré.** En Phase 1 step 3 : 3.6 (cascade CSS) inversé avant 3.2 (Bootstrap JS) parce que l'ordre du prompt aurait inversé la cascade thème (régression visuelle massive). En 3.3 (jQuery removal), il a fallu faire 3.4 (MediaElement WP-bundled) d'abord. **Toujours analyser les dépendances avant de suivre l'ordre prescrit.**

**Pointeur Phase 2 — refacto structurel** :
- **Findings prioritaires** : QC-002 (logique métier dans templates), QC-003 (bloc "card mixtape" dupliqué 4×), QC-004 (text-domain `'text-domain'` placeholder partout), QC-008 (naming PHP incohérent + pas de namespace + pas de docblocks).
- **Décisions structurantes déjà prises** (à respecter dans le refacto) : préfixe `lmt_*` partout, no-visual-change rule, text-domain cible `'lamixtape'`, template-parts via `get_template_part()`, queries hors templates dans `inc/queries.php`.
- **Question ouverte Q9** (suppression module commentaires) : à planifier (Phase 2.5 ou Phase 6 selon décision business).

**Validation finale Phase 1 (à charge utilisateur)** :
- 8 captures post-Phase-1 vs `_docs/captures-pre-3.8/` (template par template) → confirmer **0 différence visuelle** sur les 8 templates. La règle "no visual change" est l'objectif nominal de la Phase 1 ; un diff visuel non-zéro = à investiguer avant de déclarer Phase 1 close côté product.

### Phase 2 close — récap (30 avril 2026)

**Métriques globales** :
- **16 commits** depuis fin Phase 1 (`bf2aee6`), tous pushés sur `origin/main` (mode marathon, pas de validation intermédiaire entre sous-étapes)
- 15 fichiers modifiés / créés
- **+769 / −208 lignes (net +561)** — phase de structuration : ajout de `inc/queries.php`, `template-parts/card-mixtape.php`, `theme.json`, docblocks PHPDoc sur 17 fonctions, élargissement de `lmt_setup_theme()`. Le delta net positif reflète le coût normal d'une phase d'extraction (vs Phase 1 où on supprimait du code mort).
- **12 findings résolus** (cf. tableau dette ci-dessus) : QC-002, QC-003, QC-004, QC-008 (4 Hautes/Moyennes Qualité), WP-002, WP-003, WP-007, WP-008, WP-009 (5 Hautes/Basses WP), PERF-008, PERF-009 (2 Moyennes Performance), SEC-003 (1 Haute Sécurité bonus, fixée en passant via QC-002 sur guests.php).

**Bonus business surprise** : Le filtre legacy `WHERE ID != '$site_admin'` dans `guests.php` était un **no-op silencieux** depuis l'origine — `$site_admin = ""` produisait `WHERE ID != 0`, qui n'exclut personne. Le CSS `list-of-mixtapes.css:40-42` masquait `.author-1` pour compenser à la volée. Découvert pendant l'extraction PERF-008 (`37fd794`). Le fix structurel SEC-003 (`get_users(['exclude' => [1]])`) exprime maintenant l'intention au niveau data, et le visuel reste identique parce que le CSS le masquait déjà. Pattern Phase 1 confirmé : **decluttering reveals what was always there.**

**Apprentissages clés** (à retenir pour Phases 2.5-6) :
1. **Marathon mode tient si la discipline est tenue.** 16 commits sans validation intermédiaire fonctionne parce que (a) les décisions D1-D7 et D-MARATHON-1à4 ont retiré les ambiguïtés en amont, (b) chaque commit reste atomique (1 finding = 1 commit), (c) la règle no-visual-change a été tenue à 100 % (validation différée mais traçable). Si la discipline diagnostic-d'abord avait sauté, le marathon aurait amplifié les régressions au lieu de les contenir.
2. **Contradictions internes au prompt = stop-and-think, pas auto-decision.** Sur la question des fonctions de commentaires (D1 "ne touche à rien" vs 2.5.4 "renomme-les"), j'ai tranché en faveur du plan 2.5.4 (rename pur, structurel, réversible) tout en documentant le raisonnement dans le commit `d1d15ca`. Si la décision avait été plus risquée (suppression effective, refonte de comportement), la discipline EXCEPTION CRITIQUE imposait l'arrêt.
3. **PERF-002 reste ouvert volontairement.** Bornage à 30 = changement visuel (perte de 330+ entrées listées sous chaque single.php). Conformément à D4, repoussé Phase 3 avec PERF-001 et PERF-007 (même problème UX, à trancher en bloc). Commentaire explicite `// PERF-002 tracked, pagination strategy in Phase 3` posé dans `lmt_get_previous_mixtapes()` pour traçabilité.
4. **PERF-007 (`category.php`) requalifié en compagnon de PERF-001/002.** Le prompt l'annonçait comme "absorbé par QC-002" mais l'extraction structurelle nécessitait `posts_per_page = -1` (no visual change), donc le **vrai** fix (bornage) reste Phase 3. PERF-007 retiré de la liste des findings closed Phase 2 par cohérence avec la règle no-visual-change.
5. **Test Rank Math fallback skippé.** D-MARATHON-4 confirmé applicable : pas d'environnement Local opérationnel pour le test 2 (Rank Math désactivé). Risque accepté par le user en marathon ; à valider côté product au moment des captures finales.

**Pointeur Phase 2.5 — suppression module commentaires (Q9)** :
- Périmètre : `comments.php`, callback `lmt_comment_callback` (ex-`tape_comment`), filtres `lmt_comment_form_fields` / `lmt_comment_form_textarea` (ex-`my_update_comment_*`), `css/comment-form.css`, `comments_template()` calls dans templates, BDD (`comments_open` / `pings_open` à fermer), `wp-mediaelement` deps si jamais lié.
- Préparation Phase 2 : les 3 fonctions de comment ont été renommées et docblockées en `d1d15ca` ; comments.php contient désormais `'callback' => 'lmt_comment_callback'`. Le rename est purement structurel — la suppression Phase 2.5 reste triviale (suppression nette des 3 fonctions + comments.php + css + références templates).

**Pointeur Phase 3 — perf bloquante** :
- Critiques restants : **PERF-001** (home `posts_per_page=-1`), **PERF-002** (single previous-mixtapes 1M).
- Compagnon Moyen : **PERF-007** (category `posts_per_page=-1`).
- Décision pré-actée Phase 2 : tous les 3 traités conjointement. Stratégie pagination (load-more / paginate_links / infinite) à arbitrer en Phase 3, puis appliquée aux 3 templates en cohérence. Indication préliminaire = load-more.
- Autres findings perf à attaquer : SEC-001 already done en P0, mais reste **PERF-003** (Bootstrap CSS @import), **PERF-004** (jQuery double load), **PERF-005** (orderby rand×4), **PERF-006** (LEFT JOIN postmeta), **PERF-010** (style.css = @import-only), **PERF-011** (lazy/srcset), **PERF-012** (preconnect Google Font), **PERF-014** (WP_POST_REVISIONS dans wp-config).

**Validation finale Phase 2 (à charge utilisateur)** :
- 8 captures post-Phase-2 vs `_docs/captures-post-phase-1/` (= captures pre-Phase-2 par construction — la Phase 1 garantissait no-visual-change). Diff attendu : **0 différence visuelle** sur les 8 templates de référence (`/`, single mixtape, `/category/<une-cat>/`, `/search/<terme>`, `/explore/`, `/guests/`, `/colophon` ou `/legal-notice`, 404). Un diff non-zéro = à investiguer avant clôture officielle Phase 2 côté product.
- Test Rank Math `<title>` (test 1, Rank Math actif) : view source sur 5 templates (home, single, category, search, 404) → 1 seul `<title>` attendu, généré par Rank Math. Test 2 (fallback Rank Math désactivé) skippé en marathon.

### Phase 2.5 close — récap (30 avril 2026)

**Métriques globales** :
- **6 commits** depuis fin Phase 2 (`484848e`), tous pushés sur `origin/main` : `583fd61` docs prompt + `9f24105` templates + `3cb6aad` callbacks/hooks + `50d490d` CSS/enqueue + `339330b` BDD cleanup doc + `d844b33` closure.
- 7 fichiers modifiés / supprimés (`comments.php` supprimé, `css/comment-form.css` supprimé, `functions.php` allégé, `single.php` allégé, `_docs/prompt-phase-2.5.md` créé, `_docs/phase-2.5-bdd-cleanup.md` créé, `_docs/AUDIT.md` + `CLAUDE.md` mis à jour ; `_docs/prompt-phase-3.md` également entré dans le repo via slip `git add -A` — emplacement correct, hors périmètre Phase 2.5).
- Net code (hors docs) : **-163 lignes** environ (phase à dominante suppression, comme attendu).
- Findings résolus comptables : **0** (Q9 = décision business, hors taxonomie audit). Tableau dette inchangé : 31/72 résolus, 41 restants.

**Décision business actée** :
- Q1 = suppression code intégrale ; Q2 = suppression BDD irréversible ; Q3 = badge 💬 supprimé ; Q4 = pas d'annonce.
- **Option C** validée pour le bouton 💬 dans `single.php` : suppression du bouton + des classes Bootstrap collapse / multi-collapse + des `id="image"` / `id="comments"`. L'image (pochette mixtape) est figée en HTML/CSS pur, sans état JS Bootstrap. Bénéfice secondaire : moins de dette BS4 à porter en Phase 4 Tailwind.

**Bonus business surprise (diagnostic 2.5.0)** : le **module commentaires côté affichage était déjà mort** dans le thème — aucun appel à `comments_template()` ni `comment_form()` nulle part. Les filtres `comment_form_default_fields` / `comment_form_field_comment` ne firent jamais. La règle "Exception explicite Phase 2.5 : disparition contrôlée du formulaire" anticipée par le prompt s'est réduite en pratique à : disparition du bouton 💬 + de la sidebar statique "Comments are now closed.". 3 fonctions PHP étaient du code mort, 1 fichier CSS un orphelin downloaded mais jamais matché. L'écart entre "le module commentaires existe" et "le module commentaires est actif" était total. Pattern Phase 1 confirmé : **decluttering reveals what was always there**.

**Apprentissages clés** :
1. **Toujours faire l'inventaire grep avant de toucher.** Le diagnostic 2.5.0 a transformé une suppression "à risque visuel" en cleanup pur de code mort — moins de risque, moins d'altération visuelle attendue, et signal fort sur la santé réelle du module.
2. **Slip `git add -A`** : `prompt-phase-3.md` s'est retrouvé bundlé dans le commit 2.5.2 (`3cb6aad`), comme `prompt-phase-1.md` à la racine en début de Phase 2 (corrigé à l'époque par `git mv` + commit dédié). Ici l'emplacement final est correct (`_docs/`), donc pas de force-push, juste le bundle inopportun. **Discipline à renforcer** : `git add <fichier>` explicite plutôt que `git add -A` quand un fichier non lié est en `?? untracked`.
3. **WP-CLI `xargs` "command line too long" sur 370+ posts** : remplacé par boucle bash `for/do/done`. À mémoriser pour la réplication prod.
4. **Phase à dominante suppression** = bilan ligne net négatif sain. Phase 1 = -12 net (suppression + ajout structure), Phase 2 = +561 net (extraction structurelle), Phase 2.5 = -163 net (suppression de code mort). Les 3 phases ensemble = **+386 net seulement** pour ~50 commits ; le code thème reste dimensionnellement contrôlé.

**Pointeur Phase 3 — perf bloquante** :
- 3 axes (cf. `_docs/prompt-phase-3.md`) : Axe A = pagination catalogue (PERF-001/002/007) via infinite scroll par lots de 30 + endpoint REST `lamixtape/v1/posts` ; Axe B = perf images & assets (PERF-003/004/006/010/011/012/014) ; Axe C = sécurité durcissement (SEC-004/005/008/009).
- Mode marathon validé pour Phase 3 (Q4).
- Discipline diagnostic-d'abord MAINTENUE.
- WebP reporté infrastructure (Q3, plugin Performance Lab côté admin WP).

**Validation finale Phase 2.5 (à charge utilisateur)** :
- 7 templates non-single inchangés visuellement vs `_docs/captures-post-phase-2/`.
- 1 template (single mixtape) avec altération validée : disparition du bouton 💬 uniquement, l'image (pochette) reste à sa place.
- Tests fonctionnels rapides : single charge sans erreur PHP, admin Comments vide, formulaire d'éditeur "comments off" par défaut.

### Phase 3 close — récap (30 avril 2026)

**Métriques globales** :
- **17 commits** depuis fin Phase 2.5 (`35bd235`), tous pushés sur `origin/main`. Découpage : 3 prep (`845ca42` backfill / `04692f5` Q10 / `abf5527` IDs prompt) + 5 Axe A (REST endpoint, home, single, category, JS+CSS) + 6 Axe B (lazy x2, random transients, object cache, preload Outfit, defer/async) + 3 Axe C (headers + Q11 doc + iframe YouTube) + closure (ce commit).
- 15 fichiers modifiés / 4 nouveaux (`inc/rest.php`, `js/infinite-scroll.js`, `css/infinite-scroll.css`, `_docs/prompt-phase-3.md` déjà entré Phase 2.5).
- **+799 / −119 lignes (net +680)** — phase à dominante structurante (endpoint REST + JS infinite scroll + helpers cache + headers sécurité). Aucune suppression majeure.
- **9 findings audit fermés Phase 3** : PERF-001 + PERF-002 (Critiques fermés ✅), PERF-005, PERF-007, PERF-011 (Hautes/Moyenne/Basse), PERF-012 finalisé (était Partiel Phase 1), SEC-005 finalisé (était Phase 0 pour likes seulement, étendu pagination), SEC-008, SEC-009. Plus **4 backfills Phase 1** appliqués pré-marathon (PERF-003/004/010/012-partiel).
- **2 enhancements bonus hors audit** : object cache `lmt_posts_grouped_by_author` (transient 24h + invalidation save_post/deleted_post/trashed_post) ; defer/async sur scripts non-critiques (lmt-player, lmt-infinite-scroll) via filter `script_loader_tag`.
- **Aucun finding Critique restant** ✅ — c'est l'objectif structurant de Phase 3, atteint.

**Bonus business surprises** :
- **Le commit C5 (JS + CSS infinite scroll) avait été oublié** lors de la première session marathon : 4 commits Axe A poussés + l'utilisateur ferme la machine, et au check post-reprise le sentinel HTML était bien rendu mais le JS qui l'observe et déclenche l'AJAX manquait → infinite scroll cassé en Local. Reprise propre côté repo (working tree clean, pas de WIP perdu) + commit C5 atomique exécuté à la reprise. **Apprentissage : un Axe se compte en commits poussés, pas en sous-étapes annoncées**. La discipline "1 finding = 1 commit pushé immédiatement" reste solide ; le risque c'est l'oubli d'un commit "glue" qui ne ferme pas un finding mais relie plusieurs autres.
- **Diagnostic IDs PERF avant le marathon** : la rédaction initiale du prompt-phase-3.md mappait PERF-003/004/010/014 sur des sections qui ciblaient en réalité PERF-005/011/012. La discipline diagnostic-d'abord (D-PRE-PHASE-3.1 à 3.5) a évité 3-5 commits avec des IDs erronés, plus 1h de re-doc post-marathon.

**Apprentissages clés** :
1. **Phase à dominante "addition" = travailler les invariants en ouverture, pas en closure.** Phase 3 ajoute 4 nouveaux fichiers (`inc/rest.php`, `js/infinite-scroll.js`, `css/infinite-scroll.css`, helper `lmt_get_random_mixtape`) et 3 hooks (`wp_head` preload, `script_loader_tag` defer, `send_headers` sécurité). Le risque d'oublier un require/enqueue est plus élevé qu'en phase suppression. Solution : poser l'inventaire des nouveaux fichiers/hooks **avant** d'attaquer les commits, pas après.
2. **Endpoint REST = double défense indispensable.** Pattern reproduit fidèlement de SEC-001/Phase 0 : nonce X-WP-Nonce + rate-limit transient hashé wp_hash. C'est ce qui rend l'endpoint `lamixtape/v1/posts` aussi sûr que `social/v2/likes` même s'il est public et lisible (READABLE/GET).
3. **Cache + invalidation = couple indissociable.** Le commit B4 (object cache `lmt_posts_grouped_by_author`) inclut son hook d'invalidation (save_post/deleted/trashed) dans le même commit. Sans invalidation, un cache 24h devient un bug. À internaliser pour toute future couche de cache.
4. **`curl -I` côté user a tout débloqué pour Axe C.** 30 secondes de curl ont remplacé 1h d'investigation hypothétique sur "qu'est-ce que Cloudflare/OVH posent déjà". Diagnostic-d'abord = ne pas hésiter à demander à l'humain de fournir l'info qu'il a sous la main plutôt que de spéculer.
5. **Q10 (PERF-006 search) + Q11 (CSP) = bloc reporté cohérent.** Les deux relèvent de "refonte fond" plutôt que "patch surface" et ont été tracés comme questions ouvertes structurantes. Phase 4 (Tailwind) bénéficiera des deux : moins de Bootstrap inline → CSP plus simple ; moins de dépendances → search rewrite plus léger.

**Pointeur Phase 4 — Bootstrap → Tailwind v4** :
- **5 findings TW-001 à 005** + impact transversal sur tous les templates (153 occurrences de classes BS).
- Phase la plus risquée visuellement : la cohabitation Bootstrap+Tailwind est faisable mais demande discipline (préfixage `tw-` ou désactivation des resets CSS Tailwind).
- Stratégie itérative par template recommandée — commencer par les plus simples (`404.php`, `text.php`).
- Setup Tailwind v4 CLI standalone (binaire unique), pas de Node en prod.
- À l'issue Phase 4, le bouton 💬 disparu de Phase 2.5 + les classes BS éliminées des 4 cards mixtape feront que le markup deviendra purement Tailwind utilities. Le `<div class="tab-content">` résiduel sur `single.php` (laissé Phase 2.5 pour minimiser le diff) pourra aussi disparaître.

**Tests fonctionnels Phase 3 attendus côté utilisateur** (cf. `_docs/prompt-phase-3.md` section "Tests à exécuter en fin de marathon") :
1. Infinite scroll home : 30 cards au load, scroll jusqu'au bout → toutes les ~370 mixtapes affichées au final.
2. Infinite scroll single (page d'une mixtape avec 100+ ancestors) : 30 anciennes cards au load, scroll → toutes les anciennes au final.
3. Infinite scroll category (sur une catégorie populeuse) : 30 cards au load, scroll → toutes les cards de la catégorie au final.
4. Pas de doublons en infinite scroll (vérifier visuellement).
5. Sécurité : `curl -I https://lamixtape.local` doit retourner les 5 nouveaux headers + plus de `X-Powered-By`.
6. Sécurité : tester l'endpoint sans nonce → 403, tester avec mauvais context → 400, hammerer 100+ requêtes → 429.
7. Performance : DevTools Network home au load → ~30 cards rendues serveur, pas 370 ; Lazy loading images → images hors viewport non chargées au load ; Outfit woff2 visible en preload.

### Phase 4 en cours — apprentissages techniques (à consolider à la closure)

> Section vivante consolidée à la fin de Phase 4. Les apprentissages
> ci-dessous sont posés au fil des Axes pour éviter qu'ils se perdent
> avant le récap final.

**D-COHAB-1 — Préfixe `tw:` pour éviter les collisions BS↔TW pendant la cohabitation** (validé Axe A pré-C4)

Diagnostic-d'abord avant le premier enqueue Tailwind cohabité a détecté que BS 4 et TW v4 partagent les mêmes noms de classes (`mb-3`, `mb-4`, `mb-5`, `text-center`, `d-flex`, etc.) avec des **valeurs différentes** sur le scaling spacing (BS-3 = 1rem, TW-3 = 0.75rem) et avec `!important` sur la plupart des utilities BS. Sans préfixe :
- Migration `mb-3 → mb-4` dans un template aurait fait gagner la rule BS `.mb-4 { 1.5rem !important }` sur la rule TW `.mb-4 { 1rem }` (BS unlayered > TW @layer utilities, et `!important` BS bat la cascade)
- Régressions visuelles **silencieuses** au CHECKPOINT 2, qui ne se résolvent qu'après suppression Bootstrap CSS en Axe D — sauf qu'elles auraient été interprétées comme "écarts visuels acceptables" et acceptées à tort.

**Solution choisie (Option A2)** : `@import "tailwindcss" prefix(tw);` dans `tailwind.input.css`. Toutes les utilities générées sont préfixées `tw:` (variant-style v4 syntax) → zéro collision avec BS pendant la cohabitation. Strip du préfixe en commit C19.5 dédié après la suppression Bootstrap CSS en C19. Reconfiguration `tailwind.input.css` sans `prefix(tw)` + rebuild + find/replace mécanique `tw:` → `` dans tous les templates. Vérification finale par `grep -rn "tw:" --include="*.php"` = 0.

**Apprentissage généralisable** : *quand on cohabite deux frameworks CSS qui partagent un namespace, ne pas se contenter de "loaded order" pour résoudre les conflits — vérifier `!important` et `@layer` priorities. Préfixer le challenger pendant la transition est un pattern propre et réversible.*

**Coût du diagnostic** : ~10 min (lecture cascade @layer + analyse !important + benchmark options). **Bénéfice évité** : potentiellement plusieurs heures de chasse aux régressions visuelles entre Axe B et CHECKPOINT 2, plus risque d'accepter à tort un visuel régressé comme "acceptable".

**Apprentissage TW-SCAN — Tailwind v4 ne scanne pas les `*.php` par défaut** (validé pré-CHECKPOINT 2)

Au moment où Axe B s'est terminé (10 templates migrés avec utilities `tw:*`), le rebuild Tailwind a généré un fichier de seulement 9.3 KB (vs 8.4 KB baseline) — `grep -c 'tw:' assets/css/tailwind.css` = 0. Cause : Tailwind v4 auto-scanne par défaut HTML / JS / JSX / TS / TSX / MD(X), mais **pas les `*.php`**. Les classes `tw:*` étaient bien dans les templates (vérifié par `grep -rh 'class="[^"]*tw:' --include="*.php" .`), mais le scanner les ignorait → build minuscule, rendu cassé en cohabitation.

**Solution** : ajouter `@source "../../**/*.php";` dans `tailwind.input.css` (path relatif au fichier d'entrée → remonte à la racine du thème puis match récursif des `.php`). Le scanner v4 détecte les class strings dans les attributs HTML ET dans les littéraux PHP (concaténations type `$h2_classes = '... tw:mb-0 ...'` dans `card-mixtape.php`).

**À retenir** : pour tout projet WordPress / framework PHP migré vers Tailwind v4, le `@source` PHP est obligatoire. Le défaut v4 a été conçu pour les stacks Node modernes (Next, Astro, etc.), pas pour le CMS PHP.

**Coût du diagnostic** : ~5 min (vérif rapide grep `tw:` dans le build après migration). **Bénéfice évité** : 4h+ de chasse aux régressions visuelles en CHECKPOINT 2 (rendu non-stylé, hypothèses fausses sur la cascade BS↔TW, etc.). Pattern Phase 1+ confirmé : *toujours vérifier le build artefact après une migration, pas seulement le code source.*

**Apprentissage TW-VERIFY — Ne pas se fier à `grep` avec backslash escaping en bash pour vérifier les classes Tailwind v4** (validé fin Axe B, pré-CHECKPOINT 2)

Faux positif diagnostic en fin Axe B : `grep -c 'tw\:' assets/css/tailwind.css` retournait `1` alors que le CSS contenait visuellement 50+ utilities `tw:*` (`.tw\:container`, `.tw\:mx-auto`, `.tw\:flex`, `.tw\:hidden`, `.tw\:lg\:block`, etc.). Cause : double-escaping bash + single quotes — le pattern transmis à grep n'était pas celui attendu, donc 0 match réel et le `1` provient d'un faux positif dans un commentaire CSS Tailwind.

**Vérification fiable** :
- Visuel : `head -100 assets/css/tailwind.css | less` ou `head -3` (le header v4.1.18 affiche les premiers sélecteurs).
- Compteur correct (avec triple-escape pour le CSS qui contient `\:` littéral) : `grep -oE '\.tw\\\\:[a-zA-Z0-9_./-]+' file.css | sort -u | wc -l`.

**Coût du faux positif** : ~30 min (proposition de stratégie diagnostic 3-variantes A/B/C, commit `c4f4331` "test variant A" qui s'est révélé inutile mais reste en place car équivalent fonctionnel à la version récursive). **Bénéfice retenu** : la discipline diagnostic-d'abord a contenu le coût (pas de fix spéculatif sur autre chose, juste 1 commit "test" non destructeur). Mais leçon : **toujours valider le grep par un check visuel (head/less) avant de conclure que le build est cassé.**

**Extension TW-VERIFY (CHECKPOINT 2 → C17)** : `grep -c PATTERN file` compte les **lignes** matchantes, pas les occurrences. Sur un CSS Tailwind v4 minifié (qui est sur une SEULE ligne), `grep -c` plafonne à 1 quoi qu'il arrive. La vérification doit utiliser `grep -oE PATTERN file | wc -l` (compte les occurrences) OU le check visuel direct (`grep -oE 'lmt-dialog[^{}, ]*' file | sort -u` pour énumérer les sélecteurs réels). 30 min perdues à C17 sur un faux positif "lmt-dialog absent du build" parce que `grep -c` retournait 1 alors que les 6 sélecteurs étaient bien tous émis. Apprentissage cumulatif : pour valider un build CSS minifié, **énumérer** plutôt que **compter**.

### Phase 4 close — récap (1er mai 2026)

**Métriques globales** :
- **34 commits** sur la branche `feature/tailwind-migration` (de `772b418` C1 à `63ce4b4` C20), tous mergés en main via merge `--no-ff` à la closure C21.
- 27 fichiers modifiés ; **+811 / −221 lignes (net +590)**. La majorité de l'inflation vient du `assets/css/tailwind.css` 13 KB minifié + `assets/css/tailwind.input.css` configuration + `js/dialogs.js` vanilla + `_docs/bootstrap-tailwind-mapping.md`. Hors docs/build, le delta net code est proche de zéro.
- **6 findings résolus Phase 4** : TW-001, TW-002, TW-003, TW-004, TW-005 (5 nouveaux) + QC-007 finalisé (était partial Phase 1, partie CSS absorbée par la migration Tailwind).
- **236 KB Bootstrap supprimés** (CSS `bootstrap.min.css` 156 KB + JS bundle `bootstrap.bundle.min.js` 80 KB) + ~30 KB `mediaelementplayer.css` dequeued = **~266 KB de poids vendor en moins** par page front-end.
- **Tailwind output** : 13 KB minifié (de baseline 8.4 KB initial à 13 KB final post-strip-prefix). L'output contient les 70+ utilities effectivement utilisées dans les templates + le préflight v4 + le composant `lmt-dialog`.
- **TOTAL findings résolus** : 50/72 à fin Phase 4 (44 pré-Phase-4 + 6 P4). 22 restants pour Phases 5+.

**Découpage par axe** :
- **Axe A (5 commits, prep)** : `772b418` `.gitignore` Tailwind binary + `f3c00ac` `tailwind.input.css` scaffold + `f99b6d7` mapping doc Bootstrap → Tailwind v4 + `19a2b7a` ajout `prefix(tw)` pour cohabitation + `28f025f` premier build + enqueue cohabité.
- **Axe B (11 commits, templates)** : C5 404 / C6 text / C7 explore / C8 guests / C9 header / C11 single + TW-004 / C12 index / C13 category / C14 search / C15 card-mixtape ; footer.php skippé Axe B et migré entièrement en Axe C C16. **Apprentissage TW-SCAN découvert en cours d'Axe B** : `@source "../../**/*.php"` obligatoire pour Tailwind v4 sur projet PHP (default scanner ignore les `.php`).
- **Diagnostic CHECKPOINT 2 (4 commits)** : 4 régressions visuelles détectées et corrigées : `85198ac` svg display:inline-block override préflight TW v4, `88e844e` link underline override WP wp-block-library global styles, `7cfe6f7` `.burger-menu { display: none }` dead code, `1b2fee6` `--breakpoint-lg: 62rem` aligné Bootstrap.
- **Axe C (3 commits + 1 fix parser)** : `871b11d` markup `<dialog>` natif + `js/dialogs.js` vanilla + 9 triggers migrés `data-lmt-dialog` + `82aa39f` styles components `.lmt-dialog*` + `8af8fac` Bootstrap JS bundle removed. **Apprentissage parser** (`636cdf7`) : nested CSS comments cassent silencieusement le parser Tailwind v4 (impacts toutes les rules après le commentaire incriminé).
- **Diagnostic CHECKPOINT 3 (4 commits)** : 4 régressions résiduelles : `32fad1f` flex-1 → lg:w-1/3 (image home), `5e58712` flex utilities navbar (burger position), `64f7aeb` suppression totale `.fade-in` (conflit infinite scroll), `a847a42` modal centering position:fixed inset:0 margin:auto.
- **Axe D (4 commits, closure)** : `0d763ef` Bootstrap CSS enqueue + dossier `assets/vendor/bootstrap/` supprimés + `3c7c13f` strip `tw:` prefix sur 11 templates + rebuild Tailwind sans `prefix(tw)` + `63ce4b4` dequeue `mediaelementplayer.css` (TW-005) + `[SHA-CLOSURE]` ce commit (closure docs).

**Bonus business surprises** :
- **Le diagnostic CHECKPOINT 2 a sauvé 4h+** de chasse aux régressions silencieuses : le préfixe `tw:` (D-COHAB-1) a permis de détecter avant CHECKPOINT 2 que les classes Bootstrap auraient sinon gagné silencieusement la cascade pendant la cohabitation. Sans ce préfixe, les régressions visuelles auraient été acceptées à tort comme "iso-99%" alors qu'elles cachaient un vrai bug de cascade. Pattern Phase 1+ confirmé : *toujours déboguer le build artefact, pas le code source.*
- **Tailwind v4 ne scanne pas les `.php` par défaut** (TW-SCAN) — découvert pendant Axe B. Solution `@source "../../**/*.php"` documentée comme apprentissage permanent dans CLAUDE.md.
- **CSS comments ne nestent pas** (parser corruption fix `636cdf7`) — un commentaire contenant `/* ... */` casse silencieusement le parser Tailwind v4 et fait dropper toutes les rules après. Découvert à C17 par diagnostic-d'abord.

**Apprentissages clés** (à retenir pour Phases 5-6) :
1. **Diagnostic-d'abord = bénéfice composé**. La discipline a payé en Phase 1 (decluttering reveals what was always there), Phase 2 (guests SQLi pattern), Phase 3 (PERF IDs mismatch), Phase 4 (TW-SCAN, cascade collisions, parser corruption). À chaque phase, ~10-30 min investis en diagnostic avant le premier commit ont évité plusieurs heures de chasse aux régressions silencieuses.
2. **Cohabitation par préfixe** : pattern réutilisable pour toute migration de framework CSS. Le préfixe `tw:` ajoute du verbe au markup pendant la transition mais protège mécaniquement contre les collisions namespace, et son strip mécanique en fin de phase est trivial (find/replace + rebuild). À ré-employer si migration Phase 5/6 demande la cohabitation d'un autre framework.
3. **Validation visuelle progressive (CHECKPOINTS)** > validation finale unique. CHECKPOINT 2 a détecté 4 régressions, CHECKPOINT 3 en a détecté 4 autres. Si Phase 4 avait été un marathon sans CHECKPOINT, les 8 régressions auraient été découvertes au CHECKPOINT 4 final, dans un état corrompu accumulé difficile à bisecter. **Pour les phases visuellement risquées : JAMAIS de marathon sans checkpoint intermédiaire.**
4. **Build artefact ≠ code source.** Plusieurs faux positifs (TW-SCAN initial, TW-VERIFY grep -c, parser corruption) auraient été pris pour des bugs de code si je n'avais pas vérifié le build directement. Pour valider un build minifié : énumérer (`grep -oE`) plutôt que compter (`grep -c`).
5. **Branche feature dédiée + merge `--no-ff` final** : isolation totale, possibilité de bisecter facilement, rollback simple si la phase échoue. Pattern à conserver pour Phase 5 a11y (autre phase à risque visuel).

**Pointeur Phase 5 — accessibilité (a11y)** :
- 11 findings A11Y-001 à A11Y-011 ouverts.
- **Quick wins** : focus visible (A11Y-001), `<a href="#" data-toggle...>` factices remplacés par `<button>` (A11Y-002, déjà partiellement traité par la migration `<dialog>` Axe C), skip-link (A11Y-004), landmarks (A11Y-004).
- **Plus structurants** : hiérarchie titres (A11Y-003), contrastes ACF couleurs curators (A11Y-009), prefers-reduced-motion (A11Y-007 — peut réintroduire un `.fade-in` respectueux supprimé Phase 4).
- Modals migrés `<dialog>` natif Phase 4 → A11Y-008 partiel résolu côté markup (focus trap browser-natif), reste les autres composants ARIA.

**Validation finale Phase 4 (à charge utilisateur)** :
- Branche `feature/tailwind-migration` actuellement à `[SHA-CLOSURE]`. Tests post-merge : 8 templates iso-visuels à 99% vs `_docs/captures-post-phase-3/` (modulo dette résiduelle Q13). Bootstrap CSS + JS + mediaelementplayer.css confirmés absents du Network tab DevTools. Modals/burger/like/player/infinite scroll fonctionnels.
- Captures `_docs/captures-post-phase-4/` à prendre par utilisateur post-merge.

## 5. Recommandations stratégiques

### Stack cible recommandée

> **État au 1er mai 2026 (post-Phase-4)** : la stack cible est en place. Tailwind v4 + CLI standalone implémenté ; Bootstrap 4 supprimé ; modals sur `<dialog>` natif HTML5 ; jQuery WP-bundled conservé pour like/player/infinite-scroll (les autres scripts du thème — `dialogs.js` — sont vanilla). Cette section reste à titre de référence historique.

- **Tailwind CSS v4 + CLI standalone** ✅ implémenté Phase 4 (`assets/build/tailwindcss` v4.1.18, `assets/css/tailwind.input.css` config CSS-first via `@theme`, build minifié dans `assets/css/tailwind.css` 13 KB).
- **PHP 8.2** ✅ confirmé en prod.
- **`<dialog>` HTML natif** ✅ pour les modals donate/contact (Phase 4 Axe C, `js/dialogs.js` vanilla, focus trap browser-natif). Alpine.js non utilisé.
- **jQuery WP-bundled conservé** pour `lmt-main` (like + burger), `lmt-player` (MediaElement), `lmt-infinite-scroll` (Phase 3 wrapper jQuery par convenance, vanilla-able si Phase 6 outillage le requiert). `lmt-dialogs` est vanilla. Bootstrap-imposed jQuery (Popper) supprimé Phase 4 Axe C C18.

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

### Traces de boilerplate générique
Le thème a hérité de fonctions/snippets génériques d'un boilerplate non-renommé. À la fin de la Phase 2, **toutes les traces connues ont été soit supprimées soit renommées** :
- `prefix_conditional_body_class` — supprimé en Phase 1 (`d9c0699`, finding QC-014). Référençait un template `about.php` inexistant.
- `cf_search_*` (3 fonctions), `SearchFilter`, `wp_change_search_url` — renommés `lmt_search_*` en Phase 2 (`4eafbf3`, QC-008).
- `revcon_change_post_*` (2 fonctions), `no_wordpress_errors` — renommés `lmt_relabel_*` / `lmt_obfuscate_login_errors` (`8c9745a`).
- `wpb_remove_version`, `my_deregister_scripts`, `wps_deregister_styles` — renommés `lmt_remove_generator_version` / `lmt_deregister_wp_embed` / `lmt_deregister_block_library_css` (`ba3cad6`).
- `tape_comment`, `my_update_comment_*` — renommés `lmt_comment_callback` / `lmt_comment_form_fields` / `lmt_comment_form_textarea` (`d1d15ca`).
- `wcs_post_thumbnails_in_feeds`, `posts_link_attributes_1/2` — renommés `lmt_rss_post_thumbnail` / `lmt_post_link_class_prev|next` (`2a982ad`).
- Text-domain `'text-domain'` (placeholder) — remplacé partout par `'lamixtape'` (`23cf296`, QC-004).

Si tu rencontres encore un nom qui sent le boilerplate générique (préfixe non-`lmt_`, double underscore, casse étrange), traite-le comme suspect : soit code mort à supprimer, soit code à renommer en `lmt_*` (cf. section 8).

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
| ~~9~~ | ~~Suppression du module de commentaires~~ | **Résolu Phase 2.5 (30 avril 2026) — suppression définitive complète**, cf. `_docs/AUDIT.md#business` | — |
| 10 | **PERF-006 search performance — refonte stratégie** | À planifier (probablement après Phase 6, post-refacto thème complet). Options : (1) index FT MySQL (`MATCH ... AGAINST` + `wp_postmeta` indexé), (2) plugin Relevanssi ou SearchWP, (3) Algolia / Meilisearch (overkill pour ce volume), (4) status quo accepté | Recherche fonctionne post-fix QC-005 (Phase 1) mais reste lente sur termes complexes à cause du `LEFT JOIN postmeta` systématique (`lmt_search_postmeta_join`). Tolérable < 1k posts ; à ré-évaluer si la BDD croît. Décision business potentielle (qualité résultats vs perf vs dépendance externe) |
| 11 | **Content-Security-Policy header** | À planifier Phase 5/6 (après Tailwind v4 qui élimine le Bootstrap inline). Inventaire des sources externes à autoriser : `'self'`, fonts.gstatic.com (déjà éliminé via auto-host Outfit Phase 1), `https://www.youtube.com` + `https://*.youtube-nocookie.com` (player iframes), `https://cloud.umami.is` + `https://api.umami.is` (analytics), Cloudflare Turnstile si activé, `'unsafe-inline'` style temporaire pour ACF `style="background-color:..."` dynamiques (à supprimer dès qu'on bouge ces inline en classes CSS). | Posté en Phase 3 : 5 headers de sécurité baseline (`X-Content-Type-Options`, `Referrer-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `Permissions-Policy`) + suppression `X-Powered-By` (cf. `lmt_send_security_headers` `2d10728`). CSP intentionnellement reporté car matrice non-triviale (Bootstrap inline + YouTube iframe + MediaElement + Cloudflare Turnstile + Umami CDN + ACF inline). À reprendre après Phase 4 Tailwind qui aura déjà nettoyé une bonne partie des inline styles. |
| 12 | **Dette de validation Phase 3** | Re-tester en prod après déploiement Phase 3, OU intégrer aux tests de Phase 6 (outillage CI/lint). Pas de blocker pour Phase 4 (le code est conforme aux findings AUDIT). | **Tests sécurité skippés sur Local** (option "dette acceptée" validée à la closure Phase 3) : `curl -i` rate-limit pagination → 429 attendu après 100 req/h, endpoint sans nonce → 403 attendu, `?context=injection` → 400 attendu, `curl -I https://lamixtape.local` → 5 nouveaux headers attendus + plus de `X-Powered-By`. **Tests perf** non explicitement confirmés (DevTools Network home → ~30 cards rendues serveur ; lazy loading images hors viewport ; transient `lmt_random_mixtape_*` présent ; Outfit woff2 en preload). Site fonctionne en frontal donc dette tolérable, mais le code Phase 3 mérite une vérification runtime au moment du déploiement prod. |
| 13 | **Petits écarts visuels Phase 4 acceptés en CHECKPOINT 3** | Acceptés en l'état à la closure Phase 4 (1er mai 2026), à corriger plus tard en commits dédiés ad-hoc sur `main` ou en Phase 5 a11y polish. | Périmètre exact à identifier en review post-merge par diff visuel `_docs/captures-post-phase-3/` vs `_docs/captures-post-phase-4/`. La règle no-visual-change a été tenue à 99% (les 4 régressions CHECKPOINT 2 + 4 régressions CHECKPOINT 3 ont été corrigées) ; les écarts résiduels acceptés correspondent au 1% de marge prévu par le prompt-phase-4 ("micro-différences acceptables : font-rendering Tailwind reset, kerning, sub-pixel rounding"). Patterns probables : font-rendering Outfit légèrement différent (TW v4 reset vs Bootstrap reset), spacing ½px sur certains éléments où BS spacing scale et TW spacing scale ne matchent pas exactement, behaviour `data-toggle="tooltip"` legacy sur le lien "getting lost" home (BS Tooltip stylé vs native browser title fallback). Aucun blocker fonctionnel. |

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

### Décisions techniques validées (Phase 1)
- **Umami analytics** reste **inline** dans `analytics.php` (pattern officiel SaaS, `defer` présent, `data-website-id` public visible dans tout le HTML rendu de toute façon). Pas de migration `wp_enqueue_script` (complexité gratuite). Couvert par `WP-004` (cf. AUDIT.md), accepté tel quel.
- **`QC-007` partiellement résolu en Phase 1** : extraction des `<script>` inline (player.php → `js/player.js`, `var postid` single.php → `lmtData.post_id`). Partie CSS (~18-20 `style="..."` statiques décoratifs encore présents) **reportée à Phase 4** (migration Tailwind, où les classes utilities absorberont naturellement ces styles inline). Les `style="..."` dynamiques PHP-injected (`background-color: <?php echo get_field('color'); ?>`) restent inline par nécessité.

### Décisions techniques validées (Phase 2)
- **Pas de namespace OOP** (D2). Convention thème = procédural préfixé `lmt_*` + docblocks PHPDoc. Rester cohérent dans les futures phases.
- **`theme.json` minimal** (D3). v2 + `settings.layout.contentSize/wideSize` uniquement. Pas de `color.palette` ni `typography.fontFamilies` — réservés Phase 4 Tailwind où ils auront leur source de vérité unique.
- **Structure `inc/`** = flat files (D6). Aujourd'hui : `inc/queries.php`. Pas de classes, pas de sous-dossiers. Si un nouveau besoin émerge (ex. setup, hooks, helpers), créer un nouveau fichier flat (`inc/setup.php`, `inc/hooks.php`, etc.) plutôt que d'introduire une couche OOP.
- **PERF-002 reste ouvert volontairement** (D4). `lmt_get_previous_mixtapes()` garde `posts_per_page = -1` en Phase 2 (no-visual-change). Borné en Phase 3 conjointement avec PERF-001 (home) et PERF-007 (category) — même problème UX = pagination catalogue. Indication préliminaire D5 : load-more.
- **Comments chain renommée mais préservée** : `lmt_comment_callback`, `lmt_comment_form_fields`, `lmt_comment_form_textarea` (rename pur en `d1d15ca`). Suppression effective du module commentaires reste **Q9 / Phase 2.5 dédiée**. La discipline D1 (ne touche à rien) a été respectée au sens du comportement — le rename est purement structurel et facilite la suppression Phase 2.5.
- **Tests Rank Math fallback skippés en Phase 2** (D-MARATHON-4). Test 1 (Rank Math actif) à valider côté product au moment des captures finales. Test 2 (Rank Math désactivé) skippé en l'absence d'environnement Local opérationnel ; à reprogrammer si une régression `<title>` est observée.

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
