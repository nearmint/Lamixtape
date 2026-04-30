# Bootstrap 4 → Tailwind v4 mapping

> **Source de vérité** pour la migration Phase 4 (Axe B — templates).
> Toute classe Bootstrap rencontrée dans un template DOIT trouver son
> équivalent ici avant d'être substituée. Si la classe n'est pas listée
> et qu'aucune équivalence évidente n'existe → ajouter une ligne ici en
> documentant l'incertitude (D-M-4.1) et continuer.

> **Phase** : 4 — Migration Bootstrap 4.4.1 → Tailwind v4
> **Périmètre** : 153 occurrences de classes BS (cf. TW-001 dans AUDIT.md)

---

## ⚠️ Différence critique : échelle de spacing

Bootstrap 4 et Tailwind v4 ont des **échelles de spacing différentes** pour les utilities `m-*` / `p-*`. Substituer `mb-3 → mb-3` change le rendu (1rem → 0.75rem).

| BS spacing | Valeur | TW spacing équivalent |
|---|---|---|
| `*-0` | 0 | `*-0` |
| `*-1` | .25rem (4px) | `*-1` |
| `*-2` | .5rem (8px) | `*-2` |
| **`*-3`** | **1rem (16px)** | **`*-4`** |
| **`*-4`** | **1.5rem (24px)** | **`*-6`** |
| **`*-5`** | **3rem (48px)** | **`*-12`** |

**Règle de migration** : `*-3 → *-4`, `*-4 → *-6`, `*-5 → *-12`. À appliquer sur `m`, `mt`, `mb`, `ml`, `mr`, `mx`, `my`, `p`, `pt`, `pb`, `pl`, `pr`, `px`, `py`.

Négatifs BS → TW :
| BS | TW |
|---|---|
| `mr-n3` | `-mr-4` |
| `mb-n2` | `-mb-2` |

---

## Grille (BS grid → TW flex/grid)

Bootstrap 4 utilise un container max-width responsive avec une grille 12 colonnes via flex. Tailwind a un `container` similaire (max-width par breakpoint) mais sans système de colonnes natif — on utilise `flex` ou `grid` directement.

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `container` | `container mx-auto px-4` | TW container nécessite `mx-auto` explicite |
| `container-fluid` | `w-full px-4` | |
| `row` | `flex flex-wrap` | Pas de gutter natif TW ; ajouter `-mx-4` si gutter BS attendu |
| `col` | `flex-1` | |
| `col-auto` | `flex-none` | |
| `col-12` | `w-full` | |
| `col-2` | `w-1/6` | 2/12 = 1/6 |
| `col-4` | `w-1/3` | 4/12 = 1/3 |
| `col-6` | `w-1/2` | |
| `col-8` | `w-2/3` | |
| `col-md-4` | `md:w-1/3` | breakpoint md >= 768px (équivalent TW md) |
| `col-md-6` | `md:w-1/2` | |
| `col-md-8` | `md:w-2/3` | |
| `col-md-12` | `md:w-full` | |
| `col-lg-4` | `lg:w-1/3` | breakpoint lg >= 992px BS / >= 1024px TW (~ équivalent) |
| `col-lg-8` | `lg:w-2/3` | |
| `col-xs` | `flex-1` | xs = "auto width, fills space" |

Gotcha : `col-md-*` en BS implique aussi `display: flex` jusqu'au breakpoint (stack mobile par défaut). TW `md:w-X` ne change pas le display ; le parent doit avoir `flex flex-wrap` pour le wrap mobile.

---

## Display utilities

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `d-none` | `hidden` | |
| `d-block` | `block` | |
| `d-inline` | `inline` | |
| `d-inline-block` | `inline-block` | |
| `d-flex` | `flex` | |
| `d-inline-flex` | `inline-flex` | |
| `d-sm-none` | `sm:hidden` | breakpoint match |
| `d-md-none` | `md:hidden` | |
| `d-md-block` | `md:block` | |
| `d-lg-block` | `lg:block` | |
| `d-none d-sm-none d-md-none d-lg-block` | `hidden lg:block` | TW est plus concis |

---

## Spacing (margin / padding) — voir échelle plus haut

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `m-0` ... `m-2` | `m-0` ... `m-2` | identique |
| `m-3` | `m-4` | 1rem |
| `m-4` | `m-6` | 1.5rem |
| `m-5` | `m-12` | 3rem |
| `mb-0` | `mb-0` | |
| `mb-3` | `mb-4` | |
| `mb-4` | `mb-6` | |
| `mt-4` | `mt-6` | |
| `mt-5` | `mt-12` | |
| `mr-1` | `mr-1` | identique |
| `mr-2` | `mr-2` | identique |
| `mr-3` | `mr-4` | |
| `mr-n3` | `-mr-4` | négatif |
| `ml-1` | `ml-1` | identique |
| `ml-3` | `ml-4` | |
| `pb-2` | `pb-2` | identique |
| `pb-4` | `pb-6` | |
| `pb-5` | `pb-12` | |
| `pt-1` | `pt-1` | identique |
| `pt-2` | `pt-2` | identique |
| `pt-3` | `pt-4` | |
| `pt-4` | `pt-6` | |
| `pt-5` | `pt-12` | |
| `pl-*` | `pl-*` | mêmes mappings que `m-*` |
| `pr-*` | `pr-*` | mêmes mappings |

---

## Text utilities

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `text-center` | `text-center` | identique |
| `text-right` | `text-right` | identique |
| `text-left` | `text-left` | identique |
| `text-justify` | `text-justify` | identique |
| `text-uppercase` | `uppercase` | |
| `text-lowercase` | `lowercase` | |
| `text-capitalize` | `capitalize` | |
| `text-truncate` | `truncate` | |
| `text-nowrap` | `whitespace-nowrap` | |
| `font-weight-bold` | `font-bold` | |

---

## Float / position

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `float-left` | `float-left` | identique |
| `float-right` | `float-right` | identique |
| `float-none` | `float-none` | |

---

## Flexbox utilities

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `align-items-center` | `items-center` | |
| `align-items-start` | `items-start` | |
| `align-items-end` | `items-end` | |
| `justify-content-center` | `justify-center` | |
| `justify-content-between` | `justify-between` | |

---

## Sizing

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `img-fluid` | `max-w-full h-auto` | |
| `w-100` | `w-full` | |
| `h-100` | `h-full` | |

---

## Embed (player iframe — TW-004)

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `embed-responsive` | (parent) `relative` | |
| `embed-responsive-16by9` | `aspect-video` | TW v4 utilité native — closes TW-004 |
| `embed-responsive-4by3` | `aspect-[4/3]` | si jamais utilisé |

---

## Lists

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `list-unstyled` | `list-none p-0` | retire bullets + padding |
| `list-inline` | `flex gap-x-2` | comportement équivalent ; ajuster gap selon contexte |
| `list-inline-item` | (rien) | suffit que le parent soit `flex` ou `inline-flex` |

---

## Composants Bootstrap (à remplacer par utilities ou composants Tailwind dédiés)

### Boutons

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `btn` | `inline-flex items-center px-4 py-2 cursor-pointer` | base custom à étendre selon variant |
| `btn-link` | `text-accent underline hover:no-underline` | |
| `btn-outline-light` | `border border-white text-white hover:bg-white hover:text-bg` | |
| `btn-xs` | `px-2 py-1 text-xs` | XS = compact |

### Modals (TW-002 — migrés `<dialog>` Axe C)

Les classes `modal`, `modal-dialog`, `modal-lg`, `modal-content`, `modal-body`, `close` disparaissent intégralement avec la migration `<dialog>` natif Axe C. Ne pas mapper.

### Collapse (Phase 2.5 a déjà retiré les usages dans single.php)

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `collapse` | (rien — utiliser `<details>` ou JS custom) | — |
| `multi-collapse` | (rien) | — |
| `tab-content` | (rien) | — |

### Forms (modals + CF7 ; revisiter au moment de la migration des modals Axe C)

| Bootstrap 4 | Tailwind v4 | Note |
|---|---|---|
| `form-control` | `block w-full px-3 py-2 border border-white bg-transparent text-white` | input réécrit |
| `form-group` | `mb-4` (= BS mb-3) | wrapper |

---

## Classes thème (préfixées, non Bootstrap)

À conserver telles quelles : `mixtape`, `mixtape-list`, `like__btn`, `like__number`, `font-smoothing`, `fade-in`, `delay-N`, `no--hover`, `tag`, `curator`, `highlight`, `tracklist`, `nothing--found`, `lmt-card-skeleton`, `lmt-mixtapes-container`, `lmt-infinite-sentinel`, `author-N`. Pas de migration — ce sont des classes custom du thème.

---

## Convention de migration template par template

À chaque template migré (Axe B) :

1. **Lire** le markup en entier, lister toutes les classes `class="..."`.
2. **Substituer** chaque classe BS selon ce tableau.
3. **Absorber** les `style="..."` statiques en utilities Tailwind (ex. `style="margin-bottom:0"` → `mb-0`).
4. **Conserver** les `style="..."` PHP-injected dynamiques (couleurs ACF, voir D-M-4.2).
5. **Rebuild** Tailwind : `./assets/build/tailwindcss -i assets/css/tailwind.input.css -o assets/css/tailwind.css --minify`.
6. **Vérifier** visuellement sur Local vs capture pre-Phase-4.
7. **Commit** dédié, push immédiat sur `feature/tailwind-migration`.

Si un comportement BS dépendait d'un JS (ex. `data-toggle="collapse"`), traiter le JS au passage. Les seuls JS BS attendus dans le thème post-Phase-2.5 sont les modals (Axe C).

---

## Découvertes pendant la migration

> Section vivante. Toute classe BS rencontrée non listée ci-dessus doit être ajoutée ici avec son équivalent et l'incertitude (D-M-4.1).

(à remplir au fil des commits Axe B)
