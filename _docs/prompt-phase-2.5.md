# Prompt Claude Code — Phase 2.5 : Suppression du module commentaires (Q9)

> À copier-coller dans Claude Code, à la racine du thème Lamixtape. Phases 0/1/2 closes (~50 commits poussés sur `origin/main`, dernière clôture Phase 2 validée côté product avec 0 diff visuel).

---

## Règle transversale (rappel)

> **Aucune altération du rendu graphique du site n'est autorisée sans validation explicite préalable.**
>
> **Exception explicite Phase 2.5** : la suppression du module commentaires implique la disparition contrôlée :
> - du formulaire de saisie commentaire en bas de single mixtape
> - de l'affichage des commentaires existants
> - du badge compteur 💬 N côté UI
>
> Cette altération visuelle est **explicitement validée par l'utilisateur** et constitue le périmètre attendu de Phase 2.5. Toute autre altération visuelle reste interdite sans validation.

## Contexte

`CLAUDE.md` et `_docs/AUDIT.md` à jour. Q9 (suppression commentaires) tracée comme question ouverte depuis Phase 1, business validation reçue : **suppression définitive complète**.

Décisions actées par l'utilisateur :

| # | Décision | Choix |
|---|---|---|
| Q1 | Périmètre suppression | Suppression complète (callbacks, comments.php, comments_template(), formulaire, affichage, css/comment-form.css) |
| Q2 | Données existantes en BDD | **Suppression complète via WP-CLI** (irréversible — l'utilisateur a tranché) |
| Q3 | Badge compteur 💬 N en single | Supprimé aussi (cohérence UI) |
| Q4 | Annonce utilisateurs | Pas d'annonce (feature peu utilisée, suppression silencieuse) |

## Objectif Phase 2.5

Suppression chirurgicale du module commentaires :
1. Suppression côté code thème (templates, callbacks, CSS)
2. Suppression des commentaires existants en BDD via WP-CLI
3. Désactivation des commentaires sur les posts existants (closes by default pour les futurs)
4. Aucune dette résiduelle (pas de fonction commentée, pas de fichier "au cas où")

À l'issue de la phase :
- 0 référence aux commentaires dans le code thème
- 0 commentaire en BDD
- Tous les posts existants ont `comment_status = closed` et `ping_status = closed`
- Comments closés par défaut pour les futurs posts (option WP)
- 7 templates non-single inchangés visuellement
- Single mixtape : disparition du formulaire + de l'affichage + du badge

## Étape 2.5.0 — Préparation

### 2.5.0.1 Lecture obligatoire

Lire `CLAUDE.md` (mémoire de travail) et `_docs/AUDIT.md` (statuts à jour).

### 2.5.0.2 Backup BDD (filet de sécurité)

**AVANT toute modif BDD**, demander à l'utilisateur de prendre un dump SQL de la table `wp_comments` (et `wp_commentmeta`) en local :

```bash
# Depuis le terminal Local, dans le dossier app/public
wp db export --tables=wp_comments,wp_commentmeta backup-comments-pre-phase-2.5.sql
```

Confirmer à l'utilisateur que le backup est créé **et stocké hors du repo** (pas dans le thème, pas dans `_docs/`, hors du Lamixtape working copy). Stockage suggéré : Desktop, Documents, ou un dossier dédié `~/Backups/lamixtape/`.

**Attendre confirmation backup avant de toucher quoi que ce soit en BDD.**

### 2.5.0.3 Inventaire avant suppression

Grep exhaustif à présenter à l'utilisateur **avant** toute modif :

```bash
# Côté templates et CSS
grep -rn "comments_template\|comment_form\|wp_list_comments\|comments_open\|get_comments_number\|comments_number\|comments_link" --include="*.php" .

# Callbacks renommés Phase 2
grep -rn "lmt_comment_callback\|lmt_comment_form_fields\|lmt_comment_form_textarea" .

# Fichier dédié
ls -la comments.php css/comment-form.css

# Hooks et add_filter/add_action
grep -rn "comment_form_default_fields\|comment_form_field_comment\|wp_list_comments_args" --include="*.php" .

# Badge compteur en single
grep -rn "comments_number\|get_comments_number\|💬\|comment-count" --include="*.php" .
```

Présenter le résultat structuré sous forme de tableau :

| Fichier:ligne | Type | À supprimer / modifier |
|---|---|---|

L'utilisateur valide le périmètre **avant** suppression.

### 2.5.0.4 Confirmation utilisateur

Confirmer en 5 lignes :
- Backup BDD pris et stocké hors repo
- Inventaire complet présenté
- Décisions Q1-Q4 bien intégrées
- Plan d'attaque 2.5.1 → 2.5.5
- Point de non-retour identifié = étape 2.5.4 (suppression BDD via WP-CLI)

**Attendre GO avant 2.5.1.**

---

## Étape 2.5.1 — Suppression côté templates et inclusions

### 2.5.1.1 single.php

Identifier dans `single.php` :
- L'appel `comments_template()` (probable ligne ~150-160)
- Le badge compteur 💬 N (probable ligne avec `comments_number()` ou `get_comments_number()`)
- Toute autre référence aux commentaires dans le markup

Suppression complète de ces blocs.

### 2.5.1.2 comments.php

Suppression du fichier `comments.php` (template entier).

### 2.5.1.3 Autres templates

Vérifier qu'aucun autre template n'inclut de référence aux commentaires (header.php, footer.php, page-templates) — devrait être vide selon l'audit, mais grep de confirmation.

Commit unique : `feat(comments): remove comments template and includes (Q9 partial 1/4)`.

### 2.5.1.4 Validation

Charger une mixtape sur Local. Vérifier :
- Plus de formulaire de saisie en bas
- Plus d'affichage des commentaires existants (s'il y en avait)
- Plus de badge 💬 sur la card single
- Aucune erreur PHP (pas de "function not defined" ni "template missing")

**Attendre GO avant 2.5.2.**

---

## Étape 2.5.2 — Suppression côté functions.php (callbacks et hooks)

### 2.5.2.1 Suppression des fonctions

Suppression des 3 fonctions PHP renommées en Phase 2 (commit d1d15ca) :
- `lmt_comment_callback`
- `lmt_comment_form_fields`
- `lmt_comment_form_textarea`

### 2.5.2.2 Suppression des hooks

Suppression des `add_filter` / `add_action` correspondants :
- `add_filter('comment_form_default_fields', 'lmt_comment_form_fields')`
- `add_filter('comment_form_field_comment', 'lmt_comment_form_textarea')`
- Toute autre référence détectée par le grep 2.5.0.3

### 2.5.2.3 Vérification post-suppression

```bash
grep -rn "lmt_comment\|comment_form_default_fields\|comment_form_field_comment" --include="*.php" .
```

Doit retourner 0 résultat dans le code (commit messages historiques OK).

Commit unique : `feat(comments): remove comment callbacks and hooks (Q9 partial 2/4)`.

### 2.5.2.4 Validation

Charger une mixtape. Site fonctionne sans erreur PHP. Console clean.

**Attendre GO avant 2.5.3.**

---

## Étape 2.5.3 — Suppression CSS et enqueue

### 2.5.3.1 Suppression du fichier CSS

Suppression de `css/comment-form.css`.

### 2.5.3.2 Suppression de l'enqueue correspondant

Dans `functions.php`, fonction `lmt_enqueue_assets()` : retirer le `wp_enqueue_style('lmt-comment-form', ...)` ou équivalent (handle exact à vérifier dans le code actuel).

### 2.5.3.3 Vérification

```bash
grep -rn "comment-form\.css\|lmt-comment-form" --include="*.php" .
ls css/comment-form.css 2>&1 || echo "OK: file deleted"
```

Commit unique : `feat(comments): remove comment-form CSS and enqueue (Q9 partial 3/4)`.

### 2.5.3.4 Validation

Charger une mixtape. Pas de 404 sur un fichier CSS. Console Network tab clean.

**Attendre GO avant 2.5.4.**

---

## Étape 2.5.4 — Suppression BDD et désactivation globale (POINT DE NON-RETOUR)

### 2.5.4.1 ⚠️ Confirmation explicite avant action irréversible

**STOP. Avant d'exécuter quoi que ce soit en BDD, re-confirmer avec l'utilisateur :**

1. Le backup `backup-comments-pre-phase-2.5.sql` est bien créé et stocké hors du repo ?
2. La suppression BDD est bien souhaitée (Q2 = suppression complète irréversible) ?
3. L'utilisateur a-t-il vérifié que ce backup est ouvrable / valide (test rapide via `wp db check` ou `head backup-comments-pre-phase-2.5.sql` pour voir le SQL) ?

**Attendre GO explicite "OK BDD" avant d'exécuter les commandes WP-CLI.**

### 2.5.4.2 Suppression des commentaires existants

Via WP-CLI depuis le dossier `app/public` du Local :

```bash
# Compter d'abord (sanity check)
wp comment list --format=count

# Suppression de tous les commentaires (et leurs meta cascadés automatiquement)
wp comment delete $(wp comment list --format=ids) --force

# Vérification post-suppression
wp comment list --format=count
# Doit retourner 0
```

### 2.5.4.3 Désactivation des commentaires sur les posts existants

```bash
# Forcer comment_status = closed sur tous les posts existants
wp post list --post_type=post --format=ids | xargs -I {} wp post update {} --comment_status=closed --ping_status=closed

# Vérification
wp post list --post_type=post --field=comment_status --format=csv | sort -u
# Doit retourner uniquement "closed"
```

### 2.5.4.4 Désactivation par défaut pour les futurs posts

```bash
wp option update default_comment_status closed
wp option update default_ping_status closed
```

### 2.5.4.5 Documentation des commandes exécutées

Créer ou éditer `_docs/phase-2.5-bdd-cleanup.md` avec :
- Date d'exécution
- Liste exacte des commandes WP-CLI exécutées
- Résultats (compteurs avant/après)
- Localisation du backup SQL pré-suppression (au choix de l'utilisateur)

Commit : `docs: log Phase 2.5 BDD cleanup commands (Q9 partial 4/4)`.

⚠️ **Le commit `docs` ne contient PAS le backup SQL** (qui ne doit pas entrer dans le repo, cf. règle 2.5.0.2).

### 2.5.4.6 Validation

Charger plusieurs mixtapes. Aucune erreur PHP, aucun comportement inattendu. Console clean.

Tester côté admin WP :
- L'éditeur d'un post : champ "Allow comments" décoché par défaut
- La page Comments dans l'admin : vide ou affiche "No comments yet" — selon ce que WP affiche pour une table `wp_comments` vide

**Attendre GO avant 2.5.5.**

---

## Étape 2.5.5 — Closure Phase 2.5

### 2.5.5.1 Mise à jour `_docs/AUDIT.md`

Q9 n'est pas un finding numéroté de l'audit initial — c'est une décision business. Ajouter dans `_docs/AUDIT.md` une nouvelle section dédiée :

```markdown
## Décisions business — résolues

### Q9 — Suppression du module commentaires
**Statut** : Résolu Phase 2.5 (SHA 1, SHA 2, SHA 3, SHA 4, SHA 5)
**Décision** : Suppression définitive complète (Q1=A, Q2=A suppression BDD, Q3=A badge supprimé, Q4=A pas d'annonce)
**Périmètre traité** :
- Suppression code thème : comments.php, callbacks lmt_comment_*, css/comment-form.css, hooks
- Suppression BDD : wp_comments + wp_commentmeta vidées via WP-CLI
- Posts existants : comment_status = closed, ping_status = closed
- Default WP : default_comment_status = closed, default_ping_status = closed
**Backup pré-suppression** : conservé hors repo (cf. _docs/phase-2.5-bdd-cleanup.md)
```

### 2.5.5.2 Mise à jour `CLAUDE.md`

Section 7 (Questions ouvertes) : retirer Q9 puisque résolue.

Nouvelle subsection "Phase 2.5 close — récap" :
- Date
- Métriques (commits, fichiers supprimés, lignes nettes, BDD compteurs avant/après)
- Justifications business courtes (feature peu utilisée, dette résiduelle élevée)
- Apprentissages éventuels
- Pointeur Phase 3 (perf bloquante)

Mettre à jour aussi la section 4 (dette technique) si besoin.

### 2.5.5.3 Captures post-Phase-2.5

Captures **post-Phase-2.5** des 8 templates de référence par l'utilisateur. Stocker dans `_docs/captures-post-phase-2.5/`.

Diff visuel manuel vs `_docs/captures-post-phase-2/` :
- 7 templates (home, category, search, 404, explore, guests, text) : **0 différence attendue**
- 1 template (single mixtape) : **différence attendue et validée** = disparition formulaire commentaires + affichage existant + badge 💬

Si diff inattendu sur les 7 autres templates : à investiguer **avant** clôture.

### 2.5.5.4 Commit final + push

Commit unique : `docs: close Phase 2.5 (Q9 comments removal complete)`.

Confirmer à l'utilisateur que Phase 2.5 est close et **attendre son feu vert** avant Phase 3 (perf bloquante : PERF-001, PERF-002, PERF-007 + lazy images, transients, etc.).

---

## Règles de travail (rappel — déjà en vigueur)

- **Lecture obligatoire** de `CLAUDE.md` et `_docs/AUDIT.md` au démarrage
- **Commit-par-commit** avec push après chaque commit
- **Validation utilisateur** entre chaque sous-étape (rythme Phase 1, pas de marathon ici vu le point de non-retour BDD)
- **Discipline diagnostic-d'abord** maintenue : si bug pré-existant détecté, arrêt et présentation
- **Préfixe `lmt_*`** sur toute nouvelle fonction (déjà convention)
- **Backup BDD impératif** avant 2.5.4 (filet de sécurité non-négociable malgré le commit utilisateur "suppression définitive")

## Checkpoint final de Phase 2.5

| Élément | Statut |
|---|---|
| Backup BDD pré-suppression créé et stocké hors repo | Oui/Non |
| 2.5.1 Templates et inclusions | SHA + diff stat |
| 2.5.2 Functions.php callbacks | SHA + diff stat |
| 2.5.3 CSS et enqueue | SHA + diff stat |
| 2.5.4 BDD cleanup (WP-CLI) | SHA du commit doc + résultats compteurs avant/après |
| 2.5.5 Closure (AUDIT + CLAUDE) | SHA |
| Captures post-Phase-2.5 confirment 0 diff sur 7 templates ? | Oui/Non (utilisateur) |
| Single mixtape confirme disparition contrôlée ? | Oui/Non (utilisateur) |
| Findings restants pour Phase 3 | Liste IDs |

Confirmer Phase 2.5 close, **attendre GO avant Phase 3**.
