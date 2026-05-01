# Bootstrap 4 → Tailwind v4 mapping

> **Source de vérité** pour la migration Phase 4 (Axe B — templates).
> Toute classe Bootstrap rencontrée dans un template DOIT trouver son
> équivalent ici avant d'être substituée. Si la classe n'est pas listée
> et qu'aucune équivalence évidente n'existe → ajouter une ligne ici en
> documentant l'incertitude (D-M-4.1) et continuer.

> **Phase** : 4 — Migration Bootstrap 4.4.1 → Tailwind v4
> **Périmètre** : 153 occurrences de classes BS (cf. TW-001 dans AUDIT.md)

## ⚠️ Préfixe `tw:` pendant la cohabitation Axe B/C

BS 4 et TW v4 partagent les mêmes noms de classes (`mb-3`, `mb-4`, `text-center`, `d-flex`, etc.) avec des valeurs divergentes pour spacing/display. Sans préfixe, les rules BS (chargées après, avec `!important`) gagnent silencieusement la cascade pendant la cohabitation → régressions visuelles invisibles au CHECKPOINT 2.

**Solution Phase 4 (D-COHAB-1, validée user)** : `@import "tailwindcss" prefix(tw);` dans `tailwind.input.css`. Toutes les utilities générées portent le préfixe `tw:` (syntaxe variant-style v4) :
- `mb-3` (BS, 1rem) → `tw:mb-4` (TW préfixé, 1rem) — **pas de collision**
- `d-none` (BS) → `tw:hidden` — pas de collision (déjà non colliding mais préfixé pour cohérence)
- `text-uppercase` (BS) → `tw:uppercase` — pas de collision

**Suppression du préfixe en Axe D** : commit dédié C19.5 `refactor(css): strip tw: prefix from utilities` après suppression Bootstrap CSS (C19). Le strip se fait par find/replace mécanique dans les templates *.php + reconfiguration `tailwind.input.css` sans `prefix(tw)` + rebuild. Vérification finale : `grep -rn "tw:" --include="*.php" .` doit retourner 0.

→ **Toutes les colonnes "Tailwind v4" ci-dessous portent donc le préfixe `tw:` pendant Axe B/C.** Référer à C19.5 pour le strip final.

---

## ⚠️ Différence critique : échelle de spacing

Bootstrap 4 et Tailwind v4 ont des **échelles de spacing différentes** pour les utilities `m-*` / `p-*`. Substituer `mb-3 → tw:mb-3` change le rendu (1rem → 0.75rem).

| BS spacing | Valeur | TW spacing équivalent (préfixé) |
|---|---|---|
| `*-0` | 0 | `tw:*-0` |
| `*-1` | .25rem (4px) | `tw:*-1` |
| `*-2` | .5rem (8px) | `tw:*-2` |
| **`*-3`** | **1rem (16px)** | **`tw:*-4`** |
| **`*-4`** | **1.5rem (24px)** | **`tw:*-6`** |
| **`*-5`** | **3rem (48px)** | **`tw:*-12`** |

**Règle de migration** : `*-3 → tw:*-4`, `*-4 → tw:*-6`, `*-5 → tw:*-12`. À appliquer sur `m`, `mt`, `mb`, `ml`, `mr`, `mx`, `my`, `p`, `pt`, `pb`, `pl`, `pr`, `px`, `py`.

Négatifs BS → TW :
| BS | TW (préfixé) |
|---|---|
| `mr-n3` | `tw:-mr-4` |
| `mb-n2` | `tw:-mb-2` |

---

## Grille (BS grid → TW flex/grid)

Bootstrap 4 utilise un container max-width responsive avec une grille 12 colonnes via flex. Tailwind a un `container` similaire (max-width par breakpoint) mais sans système de colonnes natif — on utilise `flex` ou `grid` directement.

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `container` | `tw:container tw:mx-auto tw:px-4` | TW container nécessite `mx-auto` explicite |
| `container-fluid` | `tw:w-full tw:px-4` | |
| `row` | `tw:flex tw:flex-wrap` | Pas de gutter natif TW ; ajouter `tw:-mx-4` si gutter BS attendu |
| `col` | `tw:flex-1` | |
| `col-auto` | `tw:flex-none` | |
| `col-12` | `tw:w-full` | |
| `col-2` | `tw:w-1/6` | 2/12 = 1/6 |
| `col-4` | `tw:w-1/3` | 4/12 = 1/3 |
| `col-6` | `tw:w-1/2` | |
| `col-8` | `tw:w-2/3` | |
| `col-md-4` | `tw:md:w-1/3` | breakpoint md >= 768px (équivalent TW md) |
| `col-md-6` | `tw:md:w-1/2` | |
| `col-md-8` | `tw:md:w-2/3` | |
| `col-md-12` | `tw:md:w-full` | |
| `col-lg-4` | `tw:lg:w-1/3` | breakpoint lg >= 992px BS / >= 1024px TW (~ équivalent) |
| `col-lg-8` | `tw:lg:w-2/3` | |
| `col-xs` | `tw:flex-1` | xs = "auto width, fills space" |

Gotcha : `col-md-*` en BS implique aussi `display: flex` jusqu'au breakpoint (stack mobile par défaut). TW `tw:md:w-X` ne change pas le display ; le parent doit avoir `tw:flex tw:flex-wrap` pour le wrap mobile.

---

## Display utilities

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `d-none` | `tw:hidden` | |
| `d-block` | `tw:block` | |
| `d-inline` | `tw:inline` | |
| `d-inline-block` | `tw:inline-block` | |
| `d-flex` | `tw:flex` | |
| `d-inline-flex` | `tw:inline-flex` | |
| `d-sm-none` | `tw:sm:hidden` | breakpoint match |
| `d-md-none` | `tw:md:hidden` | |
| `d-md-block` | `tw:md:block` | |
| `d-lg-block` | `tw:lg:block` | |
| `d-none d-sm-none d-md-none d-lg-block` | `tw:hidden tw:lg:block` | TW est plus concis |

---

## Spacing (margin / padding) — voir échelle plus haut

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `m-0` ... `m-2` | `tw:m-0` ... `tw:m-2` | identique en valeur |
| `m-3` | `tw:m-4` | 1rem |
| `m-4` | `tw:m-6` | 1.5rem |
| `m-5` | `tw:m-12` | 3rem |
| `mb-0` | `tw:mb-0` | |
| `mb-3` | `tw:mb-4` | |
| `mb-4` | `tw:mb-6` | |
| `mt-4` | `tw:mt-6` | |
| `mt-5` | `tw:mt-12` | |
| `mr-1` | `tw:mr-1` | identique en valeur |
| `mr-2` | `tw:mr-2` | identique en valeur |
| `mr-3` | `tw:mr-4` | |
| `mr-n3` | `tw:-mr-4` | négatif |
| `ml-1` | `tw:ml-1` | identique en valeur |
| `ml-3` | `tw:ml-4` | |
| `pb-2` | `tw:pb-2` | identique en valeur |
| `pb-4` | `tw:pb-6` | |
| `pb-5` | `tw:pb-12` | |
| `pt-1` | `tw:pt-1` | identique en valeur |
| `pt-2` | `tw:pt-2` | identique en valeur |
| `pt-3` | `tw:pt-4` | |
| `pt-4` | `tw:pt-6` | |
| `pt-5` | `tw:pt-12` | |
| `pl-*` | `tw:pl-*` | mêmes mappings que `m-*` |
| `pr-*` | `tw:pr-*` | mêmes mappings |

---

## Text utilities

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `text-center` | `tw:text-center` | identique en classname |
| `text-right` | `tw:text-right` | identique en classname |
| `text-left` | `tw:text-left` | identique en classname |
| `text-justify` | `tw:text-justify` | identique en classname |
| `text-uppercase` | `tw:uppercase` | |
| `text-lowercase` | `tw:lowercase` | |
| `text-capitalize` | `tw:capitalize` | |
| `text-truncate` | `tw:truncate` | |
| `text-nowrap` | `tw:whitespace-nowrap` | |
| `font-weight-bold` | `tw:font-bold` | |

---

## Float / position

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `float-left` | `tw:float-left` | identique en classname |
| `float-right` | `tw:float-right` | identique en classname |
| `float-none` | `tw:float-none` | |

---

## Flexbox utilities

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `align-items-center` | `tw:items-center` | |
| `align-items-start` | `tw:items-start` | |
| `align-items-end` | `tw:items-end` | |
| `justify-content-center` | `tw:justify-center` | |
| `justify-content-between` | `tw:justify-between` | |

---

## Sizing

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `img-fluid` | `tw:max-w-full tw:h-auto` | |
| `w-100` | `tw:w-full` | |
| `h-100` | `tw:h-full` | |

---

## Embed (player iframe — TW-004)

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `embed-responsive` | (parent) `tw:relative` | |
| `embed-responsive-16by9` | `tw:aspect-video` | TW v4 utilité native — closes TW-004 |
| `embed-responsive-4by3` | `tw:aspect-[4/3]` | si jamais utilisé |

---

## Lists

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `list-unstyled` | `tw:list-none tw:p-0` | retire bullets + padding |
| `list-inline` | `tw:flex tw:gap-x-2` | comportement équivalent ; ajuster gap selon contexte |
| `list-inline-item` | (rien) | suffit que le parent soit `tw:flex` ou `tw:inline-flex` |

---

## Composants Bootstrap (à remplacer par utilities ou composants Tailwind dédiés)

### Boutons

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `btn` | `tw:inline-flex tw:items-center tw:px-4 tw:py-2 tw:cursor-pointer` | base custom à étendre selon variant |
| `btn-link` | `tw:text-accent tw:underline hover:tw:no-underline` | |
| `btn-outline-light` | `tw:border tw:border-white tw:text-white hover:tw:bg-white hover:tw:text-bg` | |
| `btn-xs` | `tw:px-2 tw:py-1 tw:text-xs` | XS = compact |

### Modals (TW-002 — migrés `<dialog>` Axe C)

Les classes `modal`, `modal-dialog`, `modal-lg`, `modal-content`, `modal-body`, `close` disparaissent intégralement avec la migration `<dialog>` natif Axe C. Ne pas mapper.

### Collapse (Phase 2.5 a déjà retiré les usages dans single.php)

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `collapse` | (rien — utiliser `<details>` ou JS custom) | — |
| `multi-collapse` | (rien) | — |
| `tab-content` | (rien) | — |

### Forms (modals + CF7 ; revisiter au moment de la migration des modals Axe C)

| Bootstrap 4 | Tailwind v4 (préfixé) | Note |
|---|---|---|
| `form-control` | `tw:block tw:w-full tw:px-3 tw:py-2 tw:border tw:border-white tw:bg-transparent tw:text-white` | input réécrit |
| `form-group` | `tw:mb-4` (= BS mb-3) | wrapper |

---

## Classes thème (préfixées, non Bootstrap)

À conserver **telles quelles** (pas de préfixe `tw:`) : `mixtape`, `mixtape-list`, `like__btn`, `like__number`, `font-smoothing`, `fade-in`, `delay-N`, `no--hover`, `tag`, `curator`, `highlight`, `tracklist`, `nothing--found`, `lmt-card-skeleton`, `lmt-mixtapes-container`, `lmt-infinite-sentinel`, `author-N`. Ce sont des classes custom du thème, pas générées par Tailwind, pas concernées par la cohabitation.

---

## Convention de migration template par template

À chaque template migré (Axe B) :

1. **Lire** le markup en entier, lister toutes les classes `class="..."`.
2. **Substituer** chaque classe BS selon ce tableau (chaque utility Tailwind porte le préfixe `tw:`).
3. **Absorber** les `style="..."` statiques en utilities Tailwind préfixées (ex. `style="margin-bottom:0"` → `tw:mb-0`).
4. **Conserver** les `style="..."` PHP-injected dynamiques (couleurs ACF, voir D-M-4.2).
5. **Rebuild** Tailwind : `./assets/build/tailwindcss -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify`.
6. **Vérifier** visuellement sur Local vs capture pre-Phase-4.
7. **Commit** dédié, push immédiat sur `feature/tailwind-migration`.

Si un comportement BS dépendait d'un JS (ex. `data-toggle="collapse"`), traiter le JS au passage. Les seuls JS BS attendus dans le thème post-Phase-2.5 sont les modals (Axe C).

---

## Découvertes pendant la migration

> Section vivante. Toute classe BS rencontrée non listée ci-dessus doit être ajoutée ici avec son équivalent et l'incertitude (D-M-4.1).

(à remplir au fil des commits Axe B)
