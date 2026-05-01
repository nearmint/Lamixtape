# Prompt Claude Code — Phase 6 : Outillage SEO + clôture finale

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. Phases 0/1/2/2.5/3/4/5 closes (~135 commits poussés sur `origin/main`, 61 findings résolus + Q9 + 0 critique restant + WCAG 2.1 AA conforme).

---

## Règle transversale (rappel)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.**
>
> **Phase 6 spécificité** : phase essentiellement **invisible visuellement** (theme.json design tokens, métadonnées HTML head, JSON-LD structured data, audit OTHER). Aucune altération visuelle attendue sauf cas exceptionnel justifié.

## Contexte

`CLAUDE.md` et `_docs/AUDIT.md` à jour après Phase 5. Phase 6 = dernière phase du refacto thème. À l'issue, tous les findings traitables côté thème sont fermés ou explicitement reportés à des phases ultérieures (infrastructure, CI, search rewrite).

Décisions actées par l'utilisateur :

| # | Décision | Choix |
|---|---|---|
| Q1 | Périmètre Phase 6 | theme.json complet + OG/Twitter + JSON-LD + audit OTHER findings |
| Q2 | Mode | **Marathon avec Pre-flight d'audit IDs** (cohérence Phases 3/4/5) |
| Q3 | Q11 (CSP) | **Reporté phase dédiée infrastructure** (hors scope Phase 6) |
| Q4 | Q12 (validation runtime Phase 3) | **Reste ouvert**, à intégrer phase outillage CI ultérieure |
| Q5 | CI / phpcs / WPCS | **Reporté phase dédiée outillage post-refacto** (hors scope Phase 6) |

## Objectif Phase 6

Finaliser tous les findings traitables côté thème. À l'issue :
- `theme.json` complet avec design tokens cohérents Tailwind v4
- Open Graph + Twitter Cards posés sur toutes les pages
- JSON-LD schema.org Article posé sur les single mixtape (potentiellement BreadcrumbList sur category)
- Tous les findings OTHER audités : fermés, reportés explicitement, ou marqués hors scope thème
- Refacto thème **fermé** — prêt pour merge prod

Findings ciblés (cf. `_docs/AUDIT.md`) :

| ID/Sujet | Sévérité | À valider en pre-flight |
|---|---|---|
| OTHER-003 (corrigé post-6.0.2) | Moyenne | Open Graph / Twitter Cards |
| OTHER-006 (corrigé post-6.0.2) | Basse | JSON-LD structured data |
| theme.json complet | (pas un finding direct) | Design tokens + settings v2 complets |
| OTHER findings (~6 autres) | Variable | RGPD, monitoring, SEO autres |

> **Mapping corrigé post-diagnostic 6.0.2** : le prompt initial référençait par erreur WP-005 (rename Posts → Playlist) et WP-006 (WP_POST_REVISIONS) pour Open Graph et JSON-LD. Les vrais IDs AUDIT.md sont **OTHER-003** (Open Graph / Twitter Cards) et **OTHER-006** (Schema.org structured data). Pattern Phase 3 (PERF) et Phase 4 (TAIL → TW) confirmé : toujours cross-checker les IDs prompt vs AUDIT avant marathon.

**Findings explicitement HORS périmètre Phase 6** :
- Q10 (PERF-006 search rewrite) → phase dédiée search rewrite
- Q11 (CSP) → phase dédiée infrastructure
- Q12 (validation runtime Phase 3) → phase outillage CI
- CI / phpcs / WPCS → phase outillage CI
- A toute phase d'évolution future du site (nouvelles features, etc.)

---

## Étape 6.0 — Pre-flight obligatoire

### 6.0.1 Lecture obligatoire

Lire `CLAUDE.md`, `_docs/AUDIT.md`, et `_docs/prompt-phase-6.md` (ce prompt).

### 6.0.2 Diagnostic IDs AUDIT.md (discipline post-Phase-3/4/5)

Cross-checker dans `_docs/AUDIT.md` :
- Les IDs WP-005 et WP-006 (sujets exacts ?)
- Tous les findings OTHER restants (ID + sujet + sévérité)
- État final attendu : liste complète et précise des findings à traiter en Phase 6

Si écart entre prompt et AUDIT.md → arrêt et présentation, comme en Phases 3/4/5.

### 6.0.3 Inventaire OTHER

Présenter à l'utilisateur la liste exhaustive des findings OTHER restants depuis AUDIT.md. Pour chacun :
- ID
- Sévérité
- Sujet en 1 ligne
- Recommandation Audit (résumé)
- Catégorie de traitement proposée :
  * **(F) Fixable Phase 6** : finding code thème, traitable maintenant
  * **(I) Infrastructure** : hors scope thème (hébergeur, plugin tiers, configuration WP admin)
  * **(B) Business decision** : nécessite décision utilisateur avant fix
  * **(R) Reporter** : trop volumineux pour Phase 6, à planifier séparément

### 6.0.4 Captures pre-Phase-6

Confirmer auprès de l'utilisateur que `_docs/captures-post-phase-5/` existe (ou stockage hors repo équivalent). Si absent → demande captures avant 6.1 (au moins home + single + category + 1 text page, pour pouvoir valider iso-visuel post-Phase-6).

### 6.0.5 Confirmation utilisateur

Confirmer en 5 lignes :
- IDs WP-005/WP-006 + OTHER validés (ou écarts signalés)
- Inventaire OTHER présenté avec catégorisation F/I/B/R
- Captures Phase 5 disponibles
- Mode marathon activé
- Discipline diagnostic-d'abord MAINTENUE

---

## Marathon Phase 6 — 4 axes

### Axe A — `theme.json` complet

#### 6.A.1 État actuel
`theme.json` minimal posé en Phase 2 (commit 945b5c5) : version 2 + layout (contentSize 1140 + wideSize 1320). À étendre.

#### 6.A.2 Périmètre extension
- `settings.color.palette` : aligner avec design tokens Tailwind v4 (`--color-bg`, `--color-text`, `--color-accent`, etc.)
- `settings.typography.fontFamilies` : aligner avec `--font-sans` (Outfit)
- `settings.typography.fontSizes` : aligner avec `--text-xs`, `--text-lg` (extraire les autres tailles utilisées)
- `settings.spacing` : si pertinent
- `styles` minimaux : pas d'override massif (le thème est largement custom CSS, theme.json n'a pas vocation à reprogrammer le design)

#### 6.A.3 Bénéfices attendus
- Cohérence design tokens entre theme.json (éditeur Gutenberg) et Tailwind (rendu front)
- Color palette accessible aux contributeurs dans l'éditeur Gutenberg
- Reduce style overrides WP injectés dans `<style id="global-styles-inline-css">`

#### 6.A.4 Validation
- Édition d'un post dans l'éditeur Gutenberg : color picker affiche la palette custom
- Front-end : aucun changement visuel inattendu (le thème CSS reste autoritaire)

#### 6.A.5 Commit
`feat(theme.json): extend with design tokens (color palette + typography + fontSizes)`

### Axe B — Open Graph + Twitter Cards

#### 6.B.1 État actuel
À auditer :
- Rank Math gère probablement les meta OG/Twitter de façon dynamique (à confirmer)
- Si Rank Math est suffisant → pas de code thème nécessaire, marquer WP-005 résolu via "Rank Math configuration"
- Si Rank Math est insuffisant ou absent sur certains templates → poser le code thème

#### 6.B.2 Audit
1. View Page Source sur `/` (home), `/<une-mixtape>/` (single), `/category/<cat>/` (category)
2. Identifier les balises `<meta property="og:..." />` et `<meta name="twitter:..." />` présentes
3. Vérifier la complétude pour chaque template :
   - `og:title` / `twitter:title`
   - `og:description` / `twitter:description`
   - `og:image` / `twitter:image` (image de la mixtape, du post, ou fallback)
   - `og:url` / `twitter:url`
   - `og:type` (`website` pour home, `article` pour mixtape, etc.)
   - `og:site_name`
   - `twitter:card` (`summary_large_image` recommandé pour mixtapes)

#### 6.B.3 Action conditionnelle
- **Si Rank Math complet** : pas de code, marquer Statut "Résolu Phase 6 (Rank Math configured)"
- **Si lacunes ciblées** : poser un fallback côté thème via `wp_head` filter pour les balises manquantes uniquement
- **Si Rank Math absent** : poser un système de meta complet via `wp_head` filter

#### 6.B.4 Commit
`feat(seo): ensure Open Graph and Twitter Cards meta on all templates (OTHER-003)`

OU si pas de code nécessaire :
`docs(audit): confirm OTHER-003 resolved via Rank Math configuration`

### Axe C — JSON-LD schema.org

#### 6.C.1 État actuel
À auditer : Rank Math pose probablement du JSON-LD basique. Vérifier sur :
- single mixtape : `Article` ou `MusicAlbum` ou `MusicPlaylist` schema.org ?
- home : `WebSite` schema.org ?
- category : `CollectionPage` ou similaire ?

#### 6.C.2 Audit
1. View Page Source sur `/`, `/<mixtape>/`, `/category/<cat>/`
2. Identifier les balises `<script type="application/ld+json">`
3. Pour chaque page, valider la pertinence du schema posé :
   - Mixtape = `MusicPlaylist` plus pertinent que `Article` (curated tracklist) — à valider business
   - Home = `WebSite`
   - Category = `CollectionPage` avec `hasPart` listant les items

#### 6.C.3 Action conditionnelle
- **Si Rank Math pose un JSON-LD pertinent** : marquer Statut "Résolu Phase 6 (Rank Math configured)" + éventuellement enrichir via `rank_math/json_ld` filter si gain marginal identifié
- **Si JSON-LD absent ou incorrect** : poser un système de JSON-LD côté thème via `wp_head` filter

#### 6.C.4 Décision business potentielle
Le choix entre `MusicPlaylist` et `Article` pour les mixtapes est business :
- `MusicPlaylist` = sémantiquement correct mais moins exploité par Google (rich results limitées)
- `Article` = exploitation Google maximale (rich results), mais sémantiquement approximatif

Si la décision n'est pas évidente → **arrête, présente, demande**.

#### 6.C.5 Commit
`feat(seo): structured data JSON-LD for mixtape singles and home (OTHER-006)`

OU si Rank Math suffisant :
`docs(audit): confirm OTHER-006 resolved via Rank Math configuration`

### Axe D — Audit OTHER findings

#### 6.D.1 Procédure par finding
Pour chaque finding OTHER catégorisé (F) **Fixable Phase 6** en 6.0.3 :
- Lire la recommandation AUDIT
- Appliquer le fix (lecture template/code, modification, test rapide)
- Commit dédié : `fix(<scope>): <ID> <description>`
- Push

Pour chaque finding OTHER catégorisé (I) **Infrastructure**, (B) **Business**, ou (R) **Reporter** :
- Marquer dans `_docs/AUDIT.md` : `**Statut** : Reporté <phase ou raison>` avec note explicative
- Commit doc dédié si plusieurs findings : `docs(audit): mark OTHER findings as reported (infrastructure/business/scope)`

#### 6.D.2 Décisions D-MARATHON-PHASE-6 pré-tranchées

| ID | Cas | Décision |
|---|---|---|
| **D-M-6.1** | Finding OTHER (F) avec recommandation ambiguë | Arrête, présente, demande clarification |
| **D-M-6.2** | Finding OTHER (B) business | Arrête, présente options à utilisateur |
| **D-M-6.3** | Finding OTHER (I) infrastructure (ex. configuration hébergeur, plugin tiers) | Marque "Reporté infrastructure" sans fix code |
| **D-M-6.4** | Finding OTHER (R) reporter (trop volumineux) | Marque "Reporté Phase 7+ ou phase dédiée X" sans fix code |
| **D-M-6.5** | Finding OTHER déjà résolu en Phase précédente sans Statut | Backfill Statut rétroactif comme on a fait pour PERF-003/004/010/012 en Phase 3 prep |

---

## Étape 6.10 — Closure Phase 6 + clôture refacto thème

### 6.10.1 Mise à jour `_docs/AUDIT.md`

Pour chaque finding traité en Phase 6 : ajouter `**Statut** : Résolu Phase 6 (...)` ou `**Statut** : Reporté (...)`.

À l'issue : **tous les findings AUDIT.md doivent avoir un Statut** (résolu ou reporté). 0 finding sans Statut. Si tu détectes des findings sans Statut résiduels, les traiter ou marquer reportés explicitement.

### 6.10.2 Mise à jour `CLAUDE.md`

Section 4 (dette technique) : recompter findings résolus, marquer la complétion.

Nouvelle subsection "Phase 6 close — récap" :
- Date
- Métriques (commits, fichiers touchés, findings traités)
- Détails par axe (A theme.json / B OG / C JSON-LD / D OTHER)
- Apprentissages éventuels
- Pointeur "refacto thème complet"

### 6.10.3 Section "Refacto thème complet — bilan global"

Ajouter dans `CLAUDE.md` une section finale récapitulative :

```markdown
## Refacto thème Lamixtape — bilan global

**Période** : Phase 0 → Phase 6
**Phases bouclées** : 0, 1, 2, 2.5, 3, 4, 5, 6 (8 phases)
**Commits totaux** : ~XXX
**Findings AUDIT résolus** : XX/72 (XX%)
**Findings reportés explicitement** : Q10, Q11, Q12 + autres OTHER reportés
**KB libérés (Phase 4)** : ~266 KB Bootstrap + 30 KB mediaelement
**Tailwind output final** : ~13 KB minifié
**Conformité a11y** : WCAG 2.1 AA (Phase 5)
**Architecture** : PHP 8.2 + WP 6.x + Tailwind v4 + jQuery WP-bundled + MediaElement.js

### Apprentissages techniques majeurs (consolidés)

- D-COHAB-1 (Phase 4) : préfixe Tailwind cohabitation pour éviter collisions Bootstrap
- TW-SCAN (Phase 4) : @source explicite pour les .php (Tailwind v4 ne scanne pas par défaut)
- TW-VERIFY (Phase 4) : la preuve visuelle (head/less) bat le grep paramétrique
- TW-PARTIAL (Phase 4) : grep tous les .php (pas seulement la hiérarchie WP) pour migrations CSS
- "Decluttering reveals" (Phases 1, 2.5) : chaque suppression de bruit révèle des bugs latents
- Diagnostic-d'abord (toutes phases) : ~10+ régressions silencieuses évitées sur l'ensemble du refacto

### Phases ultérieures planifiées

- Q10 — Search rewrite (PERF-006) : phase dédiée
- Q11 — CSP : phase dédiée infrastructure
- Q12 — Validation runtime : phase outillage CI
- A11y polish ad-hoc : si Lighthouse / axe DevTools révèlent des points en review post-déploiement
```

### 6.10.4 Captures finales

Captures `_docs/captures-post-phase-6/` (ou stockage hors repo) sur les 8 templates par l'utilisateur.

Diff visuel manuel vs `_docs/captures-post-phase-5/` : **iso-visuel à 99%** attendu (Phase 6 ne touche pas au rendu).

### 6.10.5 Commit final + push

Commit unique : `docs: close Phase 6 + refacto thème complete`.

### 6.10.6 Tag de release (optionnel)

Suggestion à l'utilisateur : créer un tag git pour marquer la fin du refacto.

```bash
git tag -a refacto-complete -m "Refacto thème Lamixtape complet — Phase 0 à 6"
git push origin refacto-complete
```

Permet un repère git lisible pour les futures évolutions.

---

## Règles de travail

- **Marathon** : pas de validation intermédiaire entre axes A/B/C/D
- **Discipline diagnostic-d'abord** MAINTENUE
- **Commit-par-commit** avec push immédiat
- **Aucun changement visuel** au-delà de ce qui est strictement nécessaire (Phase 6 = invisible)

## Checkpoint final de Phase 6

| Élément | Statut |
|---|---|
| Pre-flight 6.0 (IDs validés + OTHER catégorisés + captures pre-6 dispo) | OK/KO |
| Axe A theme.json étendu | SHA |
| Axe B Open Graph + Twitter | SHA + statut Rank Math |
| Axe C JSON-LD | SHA + statut Rank Math |
| Axe D OTHER findings traités | Liste SHA + Statuts |
| _docs/AUDIT.md : 100% findings avec Statut | Oui/Non |
| CLAUDE.md récap Phase 6 + bilan global | Oui/Non |
| Captures post-Phase-6 confirment iso-visuel à 99% ? | Oui/Non utilisateur |
| Tag refacto-complete (optionnel) | Oui/Non |

Confirmer Phase 6 close = **refacto thème Lamixtape complet**.
