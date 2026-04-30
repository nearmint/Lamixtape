# Prompt Claude Code — Phase 1 : Hygiène & code mort

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. La Phase 0 doit être close (5 commits poussés sur `origin/main`).

---

## Règle transversale (toutes phases)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.** Toute modification est strictement structurelle, sécuritaire ou de performance. Si une modif risque de produire une différence visuelle, tu **arrêtes**, l'annonces, et attends validation avant de poursuivre. Le polishing UI mineur est possible mais doit être proposé et validé séparément.

Cette règle s'applique à toutes les phases (1 à 6). Mets-la à jour dans `CLAUDE.md` section 8 ("Règles pour les futures sessions Claude Code") en début de phase.

## Contexte

`CLAUDE.md` et `AUDIT.md` à la racine du thème. PHP 8.2 confirmé en prod. Workflow git établi : commit-par-commit, push après chaque commit. Remote SSH `git@github.com:nearmint/Lamixtape.git`.

## Objectif Phase 1

Préparer le thème à un refacto sain en :
1. Supprimant tout le code mort identifié dans l'audit
2. Auto-hébergeant les libs front (Bootstrap, jQuery, MediaElement, Google Font Outfit)
3. Centralisant tous les assets via `wp_enqueue_*` (plus de `<link>`/`<script>` en dur dans les templates, plus de `<style>`/`<script>` inline)
4. Réorganisant les fichiers de documentation hors du périmètre runtime

Findings ciblés (cf. `AUDIT.md`) :
- **Hygiène fichiers** : QC-009 (`.DS_Store`), QC-010 (docs `*.md` à déplacer)
- **Code mort** : QC-013 (newsletter), QC-014 (`about.php` si présent), OTHER-008 (`fbq` orphelin), PERF-013 (`console.log` du player), QC-011 (IE9 conditionals)
- **Bug i18n** : QC-012 (`__('%s')` incorrect)
- **Refacto enqueue** : WP-001 (enqueue propre `style.css`), WP-004 (enqueue de tous les CDN), QC-007 (extraction inline), SEC-006 (handle `lmt-main` propre)

À l'issue de la phase :
- Tous les CDN externes sont remplacés par des fichiers locaux dans `assets/vendor/`
- Plus aucun `<link>`/`<script>` en dur dans `header.php` / `footer.php` / templates
- Plus aucun `<style>` / `<script>` inline non justifié
- Variable JS localisée sous le handle propre `lmt-main` (en plus du nonce déjà ajouté en Phase 0)
- Documentation déplacée dans `_docs/` (sauf `CLAUDE.md` qui reste à la racine)

---

## Étape 0 — Mise à jour `CLAUDE.md` & captures de référence

### 0.1 Mise à jour `CLAUDE.md`

Ajoute en section 8 ("Règles pour les futures sessions Claude Code"), au-dessus du bloc "Bootstrap obligatoire", un bloc :

```markdown
### Règle transversale — Aucun changement visuel sans validation
Aucune modification ne doit altérer le rendu UI/UX du site. Le refacto est strictement structurel, sécuritaire et de performance. Si une modification risque un changement visuel, arrête, annonce, attends validation. Le polishing UI mineur est possible mais doit être validé séparément.
```

Commit dédié `docs(claude): add no-visual-change rule for refactor`.

### 0.2 Demande à l'utilisateur les captures de référence

Avant tout refacto enqueue (étape 3), l'utilisateur doit avoir pris **8 captures de référence** sur son Local :
- Home (`/`)
- Single mixtape (n'importe laquelle)
- Category (n'importe laquelle)
- Search (`/search/test` ou équivalent)
- 404 (URL volontairement cassée)
- Explore (page template)
- Guests (page template)
- Text (page template)

Demande à l'utilisateur de confirmer qu'il a pris ces captures avant de passer à l'étape 1.

---

## Étape 1 — Hygiène fichiers & code mort

Toutes ces actions sont sans risque visuel direct (suppression de code inactif ou de fichiers non publics). Regroupe-les en commits thématiques cohérents.

### 1.1 Suppression `.DS_Store` (QC-009)

```bash
git rm --cached -r .DS_Store 2>/dev/null || true
find . -name ".DS_Store" -type f -delete
```

`.DS_Store` est déjà dans `.gitignore` depuis la Phase 0, donc rien à ajouter. Commit : `chore: remove tracked .DS_Store files`.

### 1.2 Déplacement des docs hors du périmètre runtime (QC-010)

Crée un dossier `_docs/` à la racine du thème. Déplace dedans :
- `audit-prompt.md`
- `audit.md`
- `AUDIT.md`
- `prompt-phase-0.md`
- `screenshot.png`

**Garde `CLAUDE.md` à la racine** (Claude Code l'attend là pour les futures sessions).

Le dossier `_docs/` reste tracké dans git (pas dans `.gitignore`) pour préserver l'historique du refacto.

Commit : `chore: move audit docs to _docs/ directory`.

### 1.3 Suppression du code newsletter mort (QC-013)

Inventaire d'abord :
- `js/main.js` : sélecteurs `#subscribe-form` et associés
- `css/newsletter.css` : à supprimer **uniquement si confirmé non utilisé ailleurs** (grep)
- Templates : recherche de `subscribe-form`, `newsletter` partout

Présente la liste à l'utilisateur **avant suppression**. Si confirmation, supprime :
- Le bloc JS dans `js/main.js`
- `css/newsletter.css` (avec également retrait de tout `@import` / `wp_enqueue_style` qui le référence)

Commit : `chore: remove dead newsletter code (QC-013)`.

### 1.4 Suppression `about.php` si présent (QC-014)

Vérifie l'existence d'`about.php` dans le thème (si non présent, skip — l'audit le mentionnait peut-être à tort).

Si présent et non référencé (template orphelin) :
- Supprime
- Commit : `chore: remove unused about.php template (QC-014)`.

### 1.5 Suppression `fbq` orphelin (OTHER-008)

`search.php` ligne ~52 : supprime la ligne `<script>fbq('track', 'Search');</script>`.

Commit : `chore: remove orphan Facebook Pixel call (OTHER-008)`.

### 1.6 Suppression `console.log` du player (PERF-013)

`player.php` : supprime tous les `console.log` (et `console.warn`/`error` si purement diagnostiques). **Garde** ceux qui ont une utilité d'erreur réelle (à juger au cas par cas — si tu hésites, demande).

Commit : `chore: remove diagnostic console.log from player (PERF-013)`.

### 1.7 Suppression IE9 conditional comments (QC-011)

Recherche les patterns `<!--[if IE 9]>` ou similaires dans `header.php` / `footer.php` / templates. Supprime.

Commit : `chore: remove IE9 conditional comments (QC-011)`.

### 1.8 Correction `__('%s')` (QC-012)

`__('%s')` est un appel i18n incorrect (un placeholder ne peut pas être traduit). Localise les occurrences via `grep -rn "__('%s')" .` et remplace par le pattern correct selon le contexte :
- Si c'est une simple interpolation : utiliser `sprintf(__('Your message %s', 'lamixtape'), $value)`
- Si c'est un format technique : extraire la string fixe à traduire

**Ne touche pas au text-domain `'text-domain'` global** (réservé à la Phase 2, find/replace global).

Commit : `fix(i18n): correct invalid __(%s) calls (QC-012)`.

### 1.9 Validation utilisateur après bloc 1.x

À l'issue des étapes 1.1 → 1.8, fais un récap commits + propose un test rapide à l'utilisateur :
- Charger la home, une mixtape, la search, le 404 → vérifier qu'aucune erreur PHP n'apparaît
- Console JS : vérifier l'absence d'erreurs (notamment plus de `fbq is not defined` sur `/search/`)

**Attends GO avant l'étape 2.**

---

## Étape 2 — Auto-hébergement des libs front

### 2.1 Création de la structure

Crée la structure :
```
assets/
├── vendor/
│   ├── bootstrap/      # 4.4.1 (à supprimer en Phase 4)
│   ├── jquery/         # 3.6.0
│   ├── mediaelement/   # 4.2.16
│   └── outfit/         # Google Font Outfit (poids utilisés uniquement)
```

### 2.2 Téléchargement des libs

Pour chaque lib, **respecte la version exacte** identifiée dans `CLAUDE.md` :

**Bootstrap 4.4.1**
- `bootstrap.min.css` depuis https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css
- `bootstrap.bundle.min.js` (inclut Popper) depuis https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js
- Vérifier les hashes SHA-384 si dispo

**jQuery 3.6.0**
- `jquery.min.js` depuis https://code.jquery.com/jquery-3.6.0.min.js

**MediaElement.js 4.2.16**
- Toutes les ressources nécessaires (CSS + JS + éventuels assets) depuis https://cdn.jsdelivr.net/npm/mediaelement@4.2.16
- Identifie précisément quels fichiers sont utilisés actuellement (par référence dans `header.php` / `footer.php` / `style.css`) et télécharge uniquement ceux-là

**Google Font Outfit**
- Identifie les poids utilisés dans le CSS (probablement 300, 400, 500, 600, 700 — à vérifier via `grep -rn "Outfit" css/` et lecture du `@import` dans `style.css`)
- Télécharge les fichiers `.woff2` correspondants depuis https://google-webfonts-helper.herokuapp.com/fonts/outfit (ou équivalent)
- Crée un fichier `assets/vendor/outfit/outfit.css` avec les `@font-face` correspondants, `font-display: swap`

### 2.3 Vérification

Liste à l'utilisateur :
- Chaque fichier téléchargé avec sa taille
- Le total
- Confirmation que les versions sont identiques aux CDN (à l'octet près si possible, via `curl -s URL | shasum` vs `shasum local-file`)

Commit : `feat(assets): self-host Bootstrap, jQuery, MediaElement, Outfit (SEC-007)`.

### 2.4 Validation utilisateur

**Pas de modification du `header.php` / `footer.php` / `style.css` à ce stade.** L'enqueue propre est l'étape 3. Cette étape 2 est uniquement le téléchargement des fichiers.

Demande GO avant l'étape 3.

---

## Étape 3 — Refacto enqueue (étape la plus risquée visuellement)

### 3.1 Approche commit-par-commit

Chaque sous-étape ci-dessous = 1 commit + validation visuelle utilisateur sur les 8 templates avant d'enchaîner.

### 3.2 Enqueue de Bootstrap (CSS + JS) (WP-004)

- Dans `functions.php`, fonction d'enqueue (`lmt_enqueue_assets` à créer si pas déjà nommée ainsi, hookée sur `wp_enqueue_scripts`) : ajoute `wp_enqueue_style('lmt-bootstrap', get_template_directory_uri() . '/assets/vendor/bootstrap/bootstrap.min.css', [], '4.4.1')` et le JS équivalent (en footer, dépendance jQuery).
- Dans `style.css`, supprime le `@import` Bootstrap CDN.
- Dans `header.php` / `footer.php`, supprime les `<link>` / `<script>` Bootstrap en dur.

Commit : `refactor(assets): enqueue Bootstrap from local vendor (WP-004)`.

**Validation visuelle utilisateur sur les 8 templates avant suite.**

### 3.3 Enqueue de jQuery local (override de jQuery WP)

- Désenregistre le jQuery par défaut de WP et enregistre le local : `wp_deregister_script('jquery'); wp_register_script('jquery', get_template_directory_uri() . '/assets/vendor/jquery/jquery.min.js', [], '3.6.0', false);`
- **Attention** : cette manipulation a des effets de bord potentiels (autres plugins peuvent s'attendre au jQuery WP). Discute avec l'utilisateur **avant** de pousser cette modif. Alternative plus sûre : laisser le jQuery WP par défaut et supprimer simplement le `<script>` jQuery CDN du `header.php` (le jQuery WP suffit).
- **Recommandation** : opter pour l'alternative (laisser jQuery WP, supprimer le CDN du header). Plus simple, moins risqué.

Commit : `refactor(assets): rely on WP-bundled jQuery, remove CDN`.

**Validation visuelle utilisateur.**

### 3.4 Enqueue de MediaElement local

- MediaElement est probablement déjà bundlé avec WP (via `wp_enqueue_script('wp-mediaelement')`). Vérifie la version. Si la version WP correspond ou est plus récente, **utilise celle-là** au lieu du local téléchargé (plus simple, économise des Ko, fait gagner du temps de maintenance).
- Si la version WP est trop ancienne ou si le pattern actuel charge un fork spécifique, alors enqueue le local depuis `assets/vendor/mediaelement/`.
- Discute le choix avec l'utilisateur avant.

Commit : `refactor(assets): use WP-bundled MediaElement` ou `refactor(assets): enqueue local MediaElement 4.2.16`.

**Validation visuelle utilisateur** (pages avec player MP3 surtout).

### 3.5 Enqueue de Google Font Outfit local

- Dans `functions.php`, ajoute `wp_enqueue_style('lmt-outfit', get_template_directory_uri() . '/assets/vendor/outfit/outfit.css', [], '1.0')`.
- Dans `style.css`, supprime le `@import` Google Fonts.
- Dans `header.php`, supprime les `<link rel="preconnect">` Google Fonts si présents.

Commit : `refactor(assets): self-host Outfit font (SEC-007)`.

**Validation visuelle utilisateur** (typographie sensible).

### 3.6 Enqueue de tous les CSS locaux (WP-001)

`style.css` contient aujourd'hui une cascade de `@import` vers les 15 fichiers CSS du dossier `css/`. Refacto :
- Réduis `style.css` à son rôle minimal : header de thème WordPress + (optionnel) reset CSS minimal.
- Pour chaque fichier dans `css/`, ajoute un `wp_enqueue_style` dédié avec dépendances explicites (ex. `lmt-mixtape-page` dépend de `lmt-bootstrap`).
- Les CSS spécifiques aux templates (ex. `mixtape-page.css`) doivent être enqueued **conditionnellement** via `is_singular('post')`, `is_search()`, etc. — pas chargés partout.
- Conserve l'ordre exact actuel pour préserver la cascade.

Commit : `refactor(assets): enqueue all theme CSS via wp_enqueue_style (WP-001)`.

**Validation visuelle utilisateur sur les 8 templates** — étape la plus risquée visuellement de la phase, prends le temps.

### 3.7 Refacto handle `lmt-main` (SEC-006)

Aujourd'hui : `wp_localize_script('jquery', 'bloginfo', [...])`. Refacto :
- Enregistre `js/main.js` sous le handle `lmt-main` avec `jquery` en dépendance.
- Localise sur `lmt-main` avec un nom plus propre : `lmtData` au lieu de `bloginfo`.
- Mets à jour `js/main.js` pour utiliser `lmtData` au lieu de `bloginfo`.

Commit : `refactor(assets): proper lmt-main handle and lmtData localization (SEC-006)`.

**Validation utilisateur** : tester que le like fonctionne toujours (le nonce est dans `lmtData.nonce` maintenant, pas `bloginfo.nonce`).

### 3.8 Extraction des `<style>` / `<script>` inline (QC-007)

Inventaire :
- `<style>` inline dans `header.php` / `footer.php` / templates → extraire vers `css/inline-{contexte}.css` enqueued conditionnellement
- `<script>` inline (notamment `player.php`) → extraire vers `js/{contexte}.js` enqueued conditionnellement
- `analytics.php` (Umami) : laisser inline pour ce cas précis (snippet officiel Umami) **sauf** si le wrapping en `wp_enqueue_script` avec `data-website-id` est faisable proprement (à discuter)

Présente la liste avant extraction. Procède template par template.

Commit(s) : `refactor(assets): extract inline styles/scripts to dedicated files (QC-007)` (un seul commit ou plusieurs si volume important).

**Validation visuelle utilisateur après chaque template extrait.**

---

## Règles de travail

- **Lecture de `CLAUDE.md` et `AUDIT.md`** au démarrage
- **Commit-par-commit** avec push après chaque commit (workflow Phase 0 confirmé)
- **Validation visuelle utilisateur** obligatoire avant chaque commit risqué (étape 3)
- **Si la cascade CSS diverge** suite à un changement d'ordre d'enqueue → rollback immédiat, on creuse
- **Si une décision business émerge** (ex. faut-il garder la newsletter ou non, faut-il vraiment auto-héberger MediaElement vs WP-bundled) → arrête et demande
- **Ne touche pas au text-domain `'text-domain'`** (Phase 2)
- **Ne touche pas à la cascade Bootstrap → Tailwind** (Phase 4)
- **Préfixe `lmt_`** sur toute nouvelle fonction (handles, hooks, options, transients)

---

## Checkpoint final de Phase 1

À la fin de la phase, produis un récap :

| Élément | Statut |
|---|---|
| Code mort supprimé | Liste finding par finding (QC-009 à 014, OTHER-008, PERF-013, QC-011, QC-012) avec SHA des commits |
| Auto-hébergement libs | Liste fichiers téléchargés + tailles |
| Refacto enqueue | Liste des handles enqueued + dépendances + chargement conditionnel |
| Validation visuelle | Liste des 8 templates testés visuellement, avec un statut "identique" / "écart mineur validé" / "régression rollbackée" |
| `CLAUDE.md` mis à jour | Section 8 + section 4 (dette technique) + sections 2 et 7 si besoin |
| Findings restants pour Phase 2 | Liste des IDs cibles |

Confirme à l'utilisateur que la Phase 1 est close et **attends son feu vert avant Phase 2** (refacto structurel PHP).
