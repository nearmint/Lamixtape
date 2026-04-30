# Prompt Claude Code — Phase 4 : Migration Bootstrap → Tailwind v4

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. Phases 0/1/2/2.5/3 closes (~80 commits poussés sur `origin/main`, 44 findings résolus + Q9 + 0 critique restant).

---

## Règle transversale (rappel)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.**
>
> **Exception explicite Phase 4** : la migration Bootstrap → Tailwind v4 implique des micro-ajustements visuels inévitables (rounded corners, spacing utilities, font-rendering légèrement différent). L'objectif est **iso-visuel à 99%** sur les 8 templates de référence, validé par diff visuel utilisateur entre chaque sous-axe.
>
> Toute différence visuelle perçue doit être documentée et validée AVANT merge final.

## Contexte

`CLAUDE.md` et `_docs/AUDIT.md` à jour après Phase 3. PHP 8.2 confirmé en prod. Workflow git établi.

**Phase 4 spécificité critique** : travail sur **branche feature dédiée**, pas sur `main`. Switch final via merge fast-forward ou merge --no-ff après validation utilisateur sur les 8 templates.

Décisions actées par l'utilisateur :

| # | Décision | Choix |
|---|---|---|
| Q1 | Stratégie migration | **Big bang** sur branche `feature/tailwind-migration`, merge final |
| Q2 | Ordre migration templates | Du moins critique (`404`, `text`, `explore`, `guests`) au plus critique (`single`, `index`, `category`, `search`, `header`, `footer`) |
| Q3 | Modals Bootstrap | Migration `<dialog>` natif HTML5 **dans Phase 4** (pas reporté Phase 5) |
| Q4 | Outil Tailwind v4 | **CLI standalone** (zip téléchargeable, 0 dépendance Node) |
| Q5 | Génération CSS | Output Tailwind commité dans le repo (`assets/css/tailwind.css`), pas de build à l'install |
| Q6 | `style="..."` inline statiques (Phase 1 reportés) | Migrés en utility classes Tailwind dans Phase 4 |
| Q7 | Mode | **Marathon avec checkpoints visuels** entre sous-axes (4 checkpoints au total) |
| Q8 | Captures de référence | `_docs/captures-post-phase-3/` (à confirmer existantes par l'utilisateur avant 4.0) |

## Objectif Phase 4

À l'issue de la phase :
- Bootstrap 4.4.1 totalement supprimé du repo (CSS + JS bundle + dépendance Popper)
- Tailwind v4 en place avec output `assets/css/tailwind.css` commité
- 8 templates migrés vers utilities Tailwind avec rendu iso-visuel à 99%
- 2 modals (#donatemodal, #contactmodal) migrés vers `<dialog>` natif (plus de JS Bootstrap)
- ~20 `style="..."` statiques absorbés en utilities Tailwind
- Branche `feature/tailwind-migration` mergée sur `main` après validation utilisateur

Findings ciblés (cf. `_docs/AUDIT.md` — IDs canoniques **TW-*** post D-PRE-PHASE-4.1) :

| ID canonique | Sévérité | Sujet réel AUDIT.md | Couverture Phase 4 |
|---|---|---|---|
| TW-001 | Haute | Bootstrap 4.4.1 fortement utilisé (grille + utilities + composants) | Axe A (setup Tailwind) + Axe B (10 templates) + Axe D (suppression Bootstrap CSS) |
| TW-002 | Haute | Modals dépendent de Bootstrap JS + jQuery + Popper | Axe C (migration `<dialog>` natif + suppression Bootstrap JS bundle) |
| TW-003 | Moyenne | Menu mobile couplé visuellement aux classes BS | Axe B (header.php) |
| TW-004 | Moyenne | Player utilise `embed-responsive embed-responsive-16by9` BS4 | Axe B (single.php) — wrapper `embed-responsive*` → `aspect-video` Tailwind, ~1 ligne |
| TW-005 | Moyenne | `mediaelementplayer.css` chargé sans usage visuel (~30 KB) | Axe D — `wp_dequeue_style('wp-mediaelement')` après l'enqueue WP |

> **Note D-PRE-PHASE-4.1** : la rédaction initiale de ce prompt référençait des IDs `TAIL-001` à `TAIL-005` qui ne correspondaient ni en numérotation ni en sujet aux findings audit canoniques `TW-001` à `TW-005`. Le mapping ci-dessus est la version corrigée. Les commits Phase 4 référencent uniquement les IDs TW-*.

> **Améliorations bonus hors audit** (D-PRE-PHASE-4.3) : ces 2 étapes d'implémentation n'ont pas de finding audit, pas de Statut à poser, mention dans le récap CLAUDE.md à la closure :
> - Configuration Tailwind v4 + design tokens (Axe A.2)
> - Documentation conventions Tailwind dans CLAUDE.md (closure 4.D.6)
>
> Et **suppression jQuery résiduel** : la décision Axe D (4.D.4) est de garder jQuery WP-bundled pour `lmt-main`/`lmt-player` et migrer en vanilla uniquement les modals via `js/dialogs.js`. Pas de finding audit "remove jQuery" — le bénéfice marginal ne justifie pas la réécriture du player MediaElement.

---

## Étape 4.0 — Préparation et création de la branche

### 4.0.1 Lecture obligatoire

Lire `CLAUDE.md`, `_docs/AUDIT.md`, et `_docs/prompt-phase-4.md` (ce prompt).

### 4.0.2 Validation des prérequis utilisateur

Confirmer auprès de l'utilisateur que les **captures `_docs/captures-post-phase-3/`** existent. Si absentes → **STOP** et demander captures avant de continuer.

### 4.0.3 Création de la branche dédiée

```bash
git checkout main
git pull origin main
git checkout -b feature/tailwind-migration
```

À partir de ce point, **TOUS les commits Phase 4 sont sur cette branche**, push systématique vers `origin/feature/tailwind-migration`. `main` reste intact pendant toute la phase.

### 4.0.4 Diagnostic IDs AUDIT.md (rappel discipline post-Phase-3)

Le cross-check IDs a été fait pré-marathon (D-PRE-PHASE-4.1, commit `[SHA-PREP-1]`) et le mapping TAIL-* → TW-* canonique est documenté dans la table "Findings ciblés" en début de prompt. Les commits Phase 4 référencent uniquement les IDs canoniques TW-001 à TW-005 + QC-007 (résiduel CSS).

### 4.0.5 Confirmation utilisateur

Confirmer en 5 lignes :
- Branche `feature/tailwind-migration` créée et poussée
- IDs TW-* canoniques validés (TAIL-* du prompt original remappés en pré-flight, cf. table "Findings ciblés")
- Captures pre-Phase-4 disponibles
- 4 checkpoints prévus (Axe A → B → C → D)
- Mode marathon par axe, validation visuelle utilisateur entre axes

---

## Axe A — Setup Tailwind v4 + design tokens (CHECKPOINT 1)

### 4.A.1 Installation Tailwind v4 CLI standalone

Téléchargement de l'exécutable depuis https://github.com/tailwindlabs/tailwindcss/releases (dernière release v4 stable). Plateforme : `tailwindcss-macos-arm64` ou `tailwindcss-macos-x64` selon la machine de l'utilisateur.

Stockage : `assets/build/tailwindcss` (ne pas commiter le binaire — ajouter au `.gitignore`).

```bash
# .gitignore additions
assets/build/tailwindcss
assets/build/tailwindcss.exe
```

### 4.A.2 Fichier source Tailwind

Créer `assets/css/tailwind.input.css` :

```css
@import "tailwindcss";

@theme {
  /* Design tokens — à confirmer après audit du CSS existant Phase 1 */
  --color-bg: #333;
  --color-text: #fff;
  --color-accent: /* couleur principale Lamixtape, à extraire du CSS actuel */;
  --font-sans: "Outfit", sans-serif;
}

/* Utility extensions et legacy compat seront ajoutés au fil des sous-axes */
```

### 4.A.3 Audit du CSS Bootstrap utilisé

Inventaire des classes Bootstrap réellement utilisées dans les templates (pour calibrer la migration et identifier les `style="..."` à absorber) :

```bash
grep -rho 'class="[^"]*"' --include="*.php" . | tr ' ' '\n' | sed 's/.*class="//;s/".*//;s/^"//' | sort -u > /tmp/all-classes-pre-migration.txt
```

Présenter à l'utilisateur :
- Liste des classes Bootstrap utilisées (col-*, row, container, d-*, mt-*, etc.)
- Liste des `style="..."` statiques restants (cf. inventaire Phase 1 §C.2 dans CLAUDE.md ou prompt 3.8)

### 4.A.4 Fichier de mapping Bootstrap → Tailwind

Créer `_docs/bootstrap-tailwind-mapping.md` avec la table de correspondance des classes utilisées :

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `container` | `container mx-auto` | |
| `row` | `flex flex-wrap` | |
| `col-md-6` | `md:w-1/2` | |
| `col-lg-4` | `lg:w-1/3` | |
| `d-flex` | `flex` | |
| `d-none` | `hidden` | |
| `d-md-none` | `md:hidden` | |
| `mb-3` | `mb-3` (équivalence directe) | |
| `text-center` | `text-center` | équivalence directe |
| ... | ... | |

Ce fichier est **la source de vérité** pour la migration. Tout template migré doit utiliser ces équivalences. Pas de classe Bootstrap résiduelle après migration d'un template.

### 4.A.5 Premier build et enqueue

```bash
./assets/build/tailwindcss -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify
```

Output : `assets/css/tailwind.css` (commité dans le repo).

Modifier `lmt_enqueue_assets()` dans `functions.php` :
- Ajouter `wp_enqueue_style('lmt-tailwind', ..., array(), null)` AVANT les CSS legacy
- **Ne PAS supprimer** Bootstrap CSS encore (cohabitation jusqu'à la fin de l'Axe B template migration)

### 4.A.6 Commits Axe A

| # | Commit | Détail |
|---|---|---|
| C1 | `chore(build): add Tailwind v4 CLI to .gitignore` | .gitignore |
| C2 | `feat(tailwind): scaffold tailwind.input.css with @theme tokens` | Fichier source + design tokens initiaux |
| C3 | `docs(tailwind): map Bootstrap classes to Tailwind utilities` | Mapping doc |
| C4 | `feat(assets): build initial tailwind.css and enqueue (Axe A)` | Build + enqueue cohabitation |

### 4.A.7 ⚠️ CHECKPOINT 1 — Validation utilisateur

**STOP marathon. Ne pas enchaîner sur Axe B.**

Confirmer à l'utilisateur :
- Tailwind est installé, build fonctionne, output commité
- Aucun template n'a encore été migré (cohabitation Bootstrap + Tailwind, Bootstrap reste autoritaire visuellement)
- Le site Local doit charger **identique** à pre-Phase-4 (Tailwind chargé mais inutilisé)

Tests utilisateur attendus :
- Charge la home, single, category, search → identique à avant
- DevTools Network : `tailwind.css` est bien chargé (poids ~10-50 KB selon le contenu de `@theme`)
- Console clean

Sur GO utilisateur : enchaîner Axe B.

---

## Axe B — Migration des templates (CHECKPOINT 2)

### 4.B.1 Ordre de migration

Du moins critique au plus critique (Q2 validé) :

1. `404.php`
2. `text.php` (page template)
3. `explore.php` (page template)
4. `guests.php` (page template)
5. `header.php` + `footer.php` (touchent toutes les pages)
6. `single.php`
7. `index.php` (home)
8. `category.php`
9. `search.php`
10. `template-parts/card-mixtape.php` (touche 4+ templates)

### 4.B.2 Procédure pour CHAQUE template

Pour chacun des 10 templates :

1. **Lecture du template actuel** : identifier toutes les classes Bootstrap utilisées + tous les `style="..."` inline statiques
2. **Substitution méthodique** selon `_docs/bootstrap-tailwind-mapping.md`
3. **Absorption des `style="..."`** statiques en utilities Tailwind (ex. `style="margin-bottom:0"` → `mb-0`, `style="display:none"` → `hidden`, `style="position:absolute;top:20px;right:20px"` → `absolute top-5 right-5`)
4. **PHP-injected dynamiques** : restent inline (cf. décision Phase 1 — couleurs ACF curators via `style="background-color:<?php echo $color; ?>"`)
5. **Build Tailwind** : rebuild `assets/css/tailwind.css` à chaque template migré (les nouvelles classes utilisées doivent être détectées par le scan v4)
6. **Vérification visuelle locale** : recharger le template sur Local, comparer à la capture pre-Phase-4
7. **Commit dédié par template** : `refactor(template): migrate <name>.php to Tailwind utilities (Axe B)`
8. **Push immédiat**

### 4.B.3 Cas spéciaux

#### `single.php` — TW-004 player iframe
Le wrapper `<div class="embed-responsive embed-responsive-16by9" style="display:none">` (ligne ~70 single.php, contient `#youtubePlayer`) doit être migré en `<div class="aspect-video relative w-full hidden">` lors du commit single.php (C11). Cela ferme TW-004 dans le même commit que la migration globale de single. Vérifier visuellement qu'au play YouTube l'iframe s'affiche correctement dans son ratio 16/9.

#### Cards mixtape (`template-parts/card-mixtape.php`)
Touche 4 templates (index, single, category, search). Migrer en dernier dans l'ordre B (après les autres templates qui l'incluent), pour minimiser les risques de cascade.

#### Header / Footer
Le burger menu mobile + les modals donate/contact y vivent. Le markup des modals reste avec classes Bootstrap pendant l'Axe B (sera migré en Axe C). Préserver les `id="donatemodal"` et `id="contactmodal"` + leurs `data-toggle` Bootstrap pour l'instant.

#### Sentinel infinite scroll (`#lmt-infinite-sentinel`)
Aucun changement classes — c'est un élément invisible, pas de styling.

### 4.B.4 Audit après chaque template

Après le commit d'un template, grep pour vérifier qu'il ne reste aucune classe Bootstrap résiduelle DANS CE TEMPLATE :

```bash
grep -E 'class="[^"]*\b(col-|row|container|d-|mt-|mb-|pt-|pb-|pl-|pr-|m-|p-|text-|float-|d-flex|justify-content|align-items|btn|navbar)[^"]*"' template.php
```

Si match → mauvaise migration, corriger avant de continuer.

**Exception** : `mb-*`, `mt-*`, `pt-*`, etc. existent à l'identique en Tailwind. Le grep va matcher mais c'est acceptable. Le critère réel est : "le template fonctionne avec **uniquement** Tailwind chargé".

### 4.B.5 Test de désactivation Bootstrap

À la fin de la migration des 10 templates (avant CHECKPOINT 2), faire un test temporaire :

```php
// Dans lmt_enqueue_assets() — TEMPORAIRE pour test
// wp_enqueue_style( 'lmt-bootstrap', ... ); // Commenté pour test
```

Recharger les 8 templates de référence sur Local. Si tout reste visuellement OK = migration B complète. Si écarts → identifier quel template n'est pas pleinement migré → corriger.

**Décommenter Bootstrap APRÈS le test** (Bootstrap reste enqueued jusqu'à fin Axe C, modals encore bootstrap).

### 4.B.6 Commits Axe B

10 commits (1 par template) + éventuels commits de fix :

| # | Template |
|---|---|
| C5 | 404.php |
| C6 | text.php |
| C7 | explore.php |
| C8 | guests.php |
| C9 | header.php |
| C10 | footer.php |
| C11 | single.php |
| C12 | index.php |
| C13 | category.php |
| C14 | search.php |
| C15 | template-parts/card-mixtape.php |

### 4.B.7 ⚠️ CHECKPOINT 2 — Validation visuelle utilisateur

**STOP marathon. Validation visuelle obligatoire AVANT Axe C.**

Captures `_docs/captures-mid-phase-4/` par l'utilisateur sur les 8 templates. Diff visuel manuel vs `_docs/captures-post-phase-3/` :

| Template | Diff attendu |
|---|---|
| 8 templates | **Iso-visuel à 99%** — micro-différences acceptables (font-rendering, rounded corners, kerning Tailwind reset) |

Si écart visible significatif → arrêt, diagnostic, fix avant Axe C.

Si validation utilisateur OK → enchaîner Axe C.

---

## Axe C — Migration modals Bootstrap → `<dialog>` natif (CHECKPOINT 3)

### 4.C.1 Inventaire modals

2 modals dans le thème :
- `#donatemodal` (header.php — bouton "Support us" du burger menu)
- `#contactmodal` (header.php — bouton "Contact us")

Aujourd'hui : Bootstrap 4 modal markup + `data-toggle="modal"` + `data-target="#xxx"` + JS Bootstrap pour open/close.

### 4.C.2 Migration vers `<dialog>` natif

Markup nouveau pour chaque modal :

```html
<dialog id="donatemodal" class="lmt-dialog">
  <div class="lmt-dialog-content">
    <button type="button" class="lmt-dialog-close" aria-label="Close">×</button>
    <!-- contenu donate -->
  </div>
</dialog>
```

Triggers (boutons d'ouverture) : remplacer `data-toggle="modal" data-target="#donatemodal"` par `data-lmt-dialog="donatemodal"`.

JS minimal vanilla (nouveau fichier `js/dialogs.js`) :

```js
document.addEventListener( 'click', function ( e ) {
    const trigger = e.target.closest( '[data-lmt-dialog]' );
    if ( trigger ) {
        e.preventDefault();
        const id = trigger.getAttribute( 'data-lmt-dialog' );
        const dialog = document.getElementById( id );
        if ( dialog && typeof dialog.showModal === 'function' ) {
            dialog.showModal();
        }
        return;
    }

    const closer = e.target.closest( '.lmt-dialog-close' );
    if ( closer ) {
        const dialog = closer.closest( 'dialog' );
        if ( dialog && typeof dialog.close === 'function' ) {
            dialog.close();
        }
    }
} );

// Fermeture par click sur backdrop
document.addEventListener( 'click', function ( e ) {
    if ( e.target.tagName === 'DIALOG' && e.target.open ) {
        const rect = e.target.getBoundingClientRect();
        if ( e.clientX < rect.left || e.clientX > rect.right ||
             e.clientY < rect.top || e.clientY > rect.bottom ) {
            e.target.close();
        }
    }
} );
```

Enqueue conditionnel (sur toutes les pages où les modals sont accessibles, donc partout vu qu'elles sont dans `header.php`) :

```php
wp_enqueue_script( 'lmt-dialogs', $theme_uri . '/js/dialogs.js', array(), null, true );
```

### 4.C.3 Style des `<dialog>` en Tailwind

`<dialog>` natif a des défauts visuels (centrage, backdrop) configurables en CSS. Ajouter dans `assets/css/tailwind.input.css` (section `@layer utilities` ou `@layer components`) :

```css
@layer components {
  .lmt-dialog {
    @apply fixed inset-0 m-auto max-w-2xl w-[90vw] max-h-[90vh] p-0 bg-transparent;
  }
  .lmt-dialog::backdrop {
    @apply bg-black/70 backdrop-blur-sm;
  }
  .lmt-dialog-content {
    @apply bg-zinc-900 text-white p-6 rounded-lg overflow-auto max-h-full;
  }
  .lmt-dialog-close {
    @apply absolute top-2 right-2 w-8 h-8 flex items-center justify-center text-white/70 hover:text-white;
  }
}
```

À ajuster pour matcher le rendu Bootstrap modal actuel (consulter les captures pre-Phase-4 pour les modals ouverts).

### 4.C.4 Suppression du JS Bootstrap pour les modals

Aujourd'hui Bootstrap bundle (avec Popper) est enqueued globalement. Les modals étaient l'unique usage côté thème (cf. inventaire Phase 1).

Test à la fin de l'Axe C : commenter temporairement `wp_enqueue_script('lmt-bootstrap-bundle', ...)` et vérifier que tout fonctionne :
- Modals donate + contact s'ouvrent et se ferment via `<dialog>`
- Aucune erreur console "Bootstrap is not defined"
- Pas d'autre fonctionnalité cassée

Si test passe → suppression définitive de l'enqueue Bootstrap JS dans le commit C18.

### 4.C.5 Commits Axe C

| # | Commit | Détail |
|---|---|---|
| C16 | `feat(modals): migrate donatemodal and contactmodal to native <dialog>` | Markup + triggers + dialogs.js |
| C17 | `feat(css): add Tailwind dialog component styles` | tailwind.input.css update + rebuild |
| C18 | `chore(assets): remove Bootstrap JS bundle (modals migrated)` | Suppression enqueue lmt-bootstrap-bundle |

### 4.C.6 ⚠️ CHECKPOINT 3 — Validation utilisateur

**STOP marathon. Tests modals obligatoires.**

Tests utilisateur :
- Click sur "Support us" → modal donate s'ouvre, contenu identique
- Click sur "×" ou fond noir → modal se ferme
- Touche Escape → modal se ferme (comportement natif `<dialog>`)
- Idem "Contact us"
- Console clean (pas d'erreur "Bootstrap not defined")
- Aucun warning a11y "aria-hidden on focused element" (cf. A11Y-NEW-001 noté Phase 1)

Si OK → enchaîner Axe D.

---

## Axe D — Suppression définitive Bootstrap + closure (CHECKPOINT 4)

### 4.D.1 Suppression Bootstrap CSS

Modifier `lmt_enqueue_assets()` dans `functions.php` :
- Supprimer l'enqueue `lmt-bootstrap` (CSS)
- Supprimer l'enqueue `lmt-bootstrap-bundle` si pas déjà fait à C18

### 4.D.2 Suppression des fichiers Bootstrap locaux

Supprimer le dossier `assets/vendor/bootstrap/` :
- `bootstrap.min.css` (~156 KB)
- `bootstrap.bundle.min.js` (~80 KB)

Total libéré : ~236 KB.

### 4.D.3 Suppression dépendance Popper

Popper était inclus dans `bootstrap.bundle.min.js`. Comme on supprime le bundle, Popper disparaît automatiquement. Aucune action séparée.

### 4.D.4 TW-005 — Dequeue `mediaelementplayer.css`

`wp_enqueue_style('wp-mediaelement')` dans `lmt_enqueue_assets()` charge automatiquement la CSS des contrôles natifs MediaElement (~30 KB), or notre player utilise des contrôles custom dans `#footer-player` (cf. `js/player.js` initialisé avec `features: []`). La CSS est téléchargée et parsée pour rien.

Action :
- Garder `wp_enqueue_script('wp-mediaelement')` (le JS est utilisé pour le decoding MP3).
- Soit ne plus enqueuer du tout `wp_enqueue_style('wp-mediaelement')` (si l'enqueue style et script sont décorrélés côté API WP),
- Soit `wp_dequeue_style('wp-mediaelement')` après l'enqueue automatique déclenché par le script (priorité tardive sur `wp_enqueue_scripts`).

Choix simple : retirer la ligne `wp_enqueue_style('wp-mediaelement')` (ajoutée Phase 1) et vérifier que le JS marche toujours sans la CSS associée — vu que `features: []`, aucun contrôle natif n'est rendu donc aucun style natif n'est requis.

### 4.D.5 Audit jQuery résiduel (enhancement, hors audit)

Vérifier les usages de jQuery dans le thème post-migration :

```bash
grep -rn 'jQuery\|\\$(' --include="*.js" js/
```

Inventaire attendu :
- `js/main.js` : like handler (utilise `$.ajax`)
- `js/player.js` : MediaElement init (`$audioPlayer.mediaelementplayer()`)
- `js/infinite-scroll.js` : `jQuery(function($){...})` wrapper

Décision sur jQuery :
- Option A : conserver jQuery pour like + player (WP-bundled, gratuit)
- Option B : migrer like + player en vanilla fetch / vanilla DOM (plus invasif)

**Reco** : A (conserver). Le like utilise déjà le pattern `lmtData.nonce` + `$.ajax`. Le player MediaElement nécessite jQuery par design (lib externe). Migrer en vanilla = +200 lignes JS, gain marginal.

→ Pas de finding audit "remove jQuery". Mention dans CLAUDE.md récap "Améliorations bonus hors audit" : Bootstrap-imposed jQuery retiré (Popper dans le bundle Bootstrap supprimé), jQuery-via-WP-bundled conservé pour `lmt-main` (like) + `lmt-player` (MediaElement). `lmt-infinite-scroll` (Phase 3) et `lmt-dialogs` (Phase 4 Axe C) sont déjà vanilla.

### 4.D.6 Mise à jour `_docs/AUDIT.md`

Pour chaque finding TW fermé en Phase 4 : ajouter `**Statut** : Résolu Phase 4 (SHA + ...)`.

Findings canoniques concernés (post D-PRE-PHASE-4.1) :
- **TW-001** (Bootstrap fortement utilisé) — fermé par Axe A + B + D
- **TW-002** (Modals BS JS + jQuery + Popper) — fermé par Axe C
- **TW-003** (Menu mobile couplé classes BS) — fermé par Axe B (header.php migré)
- **TW-004** (Player `embed-responsive` BS4) — fermé par Axe B (single.php — wrapper → `aspect-video`)
- **TW-005** (`mediaelementplayer.css` chargé inutile) — fermé par Axe D (`wp_dequeue_style`)

Aussi : fermeture du résiduel **QC-007** (style="..." statiques absorbés en utilities Tailwind dans Axe B).

Pas de Statut audit pour les bonus hors audit (setup Tailwind tokens, docs conventions) — mention CLAUDE.md récap uniquement.

### 4.D.7 Mise à jour `CLAUDE.md`

Section 4 (dette technique) : recompter findings résolus.

Nouvelle subsection "Phase 4 close — récap" :
- Date
- Métriques (commits, KB économisés, fichiers supprimés, fichiers ajoutés)
- Apprentissages (cohabitation pendant migration, build Tailwind manuel, etc.)
- Pointeur Phase 5 (a11y) — phase suivante structurelle

Section 5 (recommandations stratégiques) : retirer la mention "Tailwind v4 + CLI standalone" du futur, marquer comme implémenté.

### 4.D.8 Commits Axe D

| # | Commit | Détail |
|---|---|---|
| C19 | `chore(assets): remove Bootstrap CSS enqueue and local files (TW-001)` | Suppression enqueue + dossier `assets/vendor/bootstrap/` |
| C19.5 | `refactor(css): strip tw: prefix from utilities (post Bootstrap removal)` | Reconfigure `tailwind.input.css` sans `prefix(tw)` + rebuild + find/replace `tw:` → `` dans tous les templates *.php. Vérification : `grep -rn "tw:" --include="*.php" .` retourne 0 (sauf `_docs/` historique acceptable). Cf. D-COHAB-1 dans CLAUDE.md section 4 "Phase 4 en cours". |
| C20 | `perf(assets): dequeue mediaelementplayer.css unused (TW-005)` | `wp_dequeue_style('wp-mediaelement')` ou suppression `wp_enqueue_style('wp-mediaelement')` ; `wp_enqueue_script('wp-mediaelement')` conservé |
| C21 | `docs: update AUDIT statuses and CLAUDE.md for Phase 4 close` | Closure docs (TW-001/002/003/004/005 + QC-007 résiduel) ; consolide la subsection "Phase 4 en cours" en "Phase 4 close — récap" |

### 4.D.9 ⚠️ CHECKPOINT 4 — Validation finale + merge

**STOP marathon avant merge.**

Tests finaux utilisateur sur la branche `feature/tailwind-migration` :
- Captures `_docs/captures-post-phase-4/` sur les 8 templates
- Diff visuel manuel vs `_docs/captures-post-phase-3/`
- Tests fonctionnels :
  - Like 🔥 OK
  - Player MP3 + YouTube OK
  - Modals donate + contact OK
  - Infinite scroll OK (home, single, category)
  - Search OK
- Console clean sur toutes les pages
- DevTools Network : pas de Bootstrap CSS ni JS chargé. Tailwind output chargé.
- Poids global réduit (Bootstrap ~236 KB libérés)

### 4.D.10 Merge vers `main`

Si validation utilisateur OK :

```bash
git checkout main
git merge --no-ff feature/tailwind-migration -m "Merge Phase 4: Bootstrap to Tailwind v4 migration"
git push origin main
git branch -d feature/tailwind-migration
git push origin --delete feature/tailwind-migration
```

Phase 4 close. **Attendre GO utilisateur avant Phase 5** (accessibilité).

---

## Règles de travail

- **Branche dédiée `feature/tailwind-migration`** : tous les commits Phase 4 y vont, JAMAIS sur `main` directement
- **Marathon par axe** : enchaînement libre dans Axe A, dans Axe B, dans Axe C, dans Axe D
- **CHECKPOINT visuel utilisateur** OBLIGATOIRE entre axes (4 checkpoints au total : 1, 2, 3, 4)
- **Diagnostic-d'abord MAINTENU** : si bug, régression, IDs incohérents avec AUDIT.md, ou décision business non couverte → arrêt et présentation
- **Build Tailwind manuel** à chaque template migré (Axe B) ou à chaque update de `tailwind.input.css` (Axe A et C)
- **Push immédiat post-commit** sur la branche feature
- **Pas de fix spéculatif** : la phase la plus risquée du refacto, on ne court pas

## Décisions D-MARATHON-PHASE-4 pré-tranchées

| ID | Cas | Décision |
|---|---|---|
| **D-M-4.1** | Classe Bootstrap non listée dans le mapping → équivalent Tailwind ambigu | Ajoute la classe au mapping dans `_docs/bootstrap-tailwind-mapping.md` avec ta meilleure équivalence + note l'incertitude. Continue. Si écart visuel détecté au CHECKPOINT, on corrige. |
| **D-M-4.2** | `style="..."` inline dynamique PHP-injected | RESTE INLINE (cf. décision Phase 1 — couleurs ACF curators) |
| **D-M-4.3** | Composant Bootstrap utilisé hors modal/dropdown/grid (ex. `breadcrumb`, `pagination`, `card`) | Lister explicitement dans le récap mid-phase, demander à l'utilisateur la stratégie (utility custom vs équivalent Tailwind) |
| **D-M-4.4** | jQuery résiduel détecté hors like/player/dialogs | Lister, ne pas migrer en vanilla par défaut, attendre validation |
| **D-M-4.5** | Conflit visuel `font-rendering` Tailwind vs Bootstrap (Tailwind v4 reset différent) | Documenté en checkpoint, micro-ajustement acceptable, pas de rollback |
| **D-M-4.6** | Build Tailwind échoue | STOP, diagnostic, ne pas commiter un build cassé |

## Checkpoint final de Phase 4

| Élément | Statut |
|---|---|
| Branche `feature/tailwind-migration` créée + commits Axe A/B/C/D | Liste SHA par axe |
| Bootstrap CSS + JS supprimés (-236 KB) | SHA C18 + C19 |
| Tailwind v4 en place + design tokens | SHA + tokens |
| 10 templates migrés sans classe Bootstrap résiduelle | Liste templates + SHA |
| 2 modals → `<dialog>` natif | SHA C16-C17 |
| `style="..."` statiques absorbés en utilities | Compte avant/après |
| Captures post-Phase-4 confirment iso-visuel à 99% ? | Oui/Non utilisateur |
| Tests fonctionnels OK ? | Oui/Non utilisateur |
| Merge `feature/tailwind-migration` → `main` | SHA merge commit |
| `_docs/AUDIT.md` mis à jour ? | Oui/Non + liste IDs |
| `CLAUDE.md` mis à jour ? | Oui/Non |
| Findings restants pour Phase 5 | Liste IDs |

Confirmer Phase 4 close et **attendre GO avant Phase 5** (accessibilité).
