# Prompt Claude Code — Phase 0 : Bootstrap & critiques bloquantes

> À copier-coller dans Claude Code, à la racine du thème Lamixtape.

---

## Contexte

Tu démarres le refacto du thème Lamixtape.fr selon le plan défini dans `CLAUDE.md` (à la racine du thème). L'audit complet a produit `AUDIT.md` (70 findings classés). Cette session traite **uniquement la Phase 0**, qui est le prérequis bloquant à tout autre travail.

Lis `CLAUDE.md` et `AUDIT.md` avant toute action. Référence-toi aux IDs de findings (`SEC-001`, `SEC-002`, `QC-001`) tout au long.

## Objectif Phase 0

Débloquer les 3 verrous critiques avant tout refacto :
1. **QC-001** : initialiser le versioning git
2. **SEC-002** : supprimer la feature `dislike` (jamais finalisée, callback inexistant → 500)
3. **SEC-001** : sécuriser l'endpoint REST `likes` (permission_callback explicite, nonce REST, rate-limit)

À l'issue de cette phase, le site doit être :
- Versionné avec un historique propre
- Débarrassé d'une feature cassée
- Protégé contre le spam d'incrément de likes

---

## Étape 1 — Versioning git (QC-001)

### 1.1 Init du repo

À la racine du **thème** (`wp-content/themes/lamixtape/` — pas à la racine du WordPress) :

```bash
git init
git config user.name "..."   # à demander si inconnu
git config user.email "..."  # à demander si inconnu
```

### 1.2 Création du `.gitignore`

Crée un `.gitignore` à la racine du thème avec **au minimum** :

```
# OS
.DS_Store
Thumbs.db

# Editors
.idea/
.vscode/
*.swp
*.swo

# Dependencies (anticipation Phase 4 Tailwind)
node_modules/
vendor/

# Logs
*.log

# Build output (anticipation Phase 4)
dist/
build/

# WordPress
wp-config.php
```

### 1.3 Premier commit "as-is"

Conserve `audit.md`, `CLAUDE.md` et `AUDIT.md` dans ce commit (leur déplacement/ignore est traité en Phase 1).

```bash
git add .
git commit -m "chore: initial commit, theme as-is before refactor"
```

### 1.4 Validation utilisateur

Confirme à l'utilisateur :
- Le commit initial est créé (montre le SHA + le nombre de fichiers trackés)
- Demande s'il veut paramétrer un remote (GitHub/GitLab) maintenant ou plus tard
- **Attends sa réponse avant d'enchaîner sur l'étape 2**

---

## Étape 2 — Suppression de la feature `dislike` (SEC-002)

**Décision business validée** : la feature dislike n'a jamais été finalisée (callback `social__dislike` inexistant), elle est supprimée intégralement.

### 2.1 Inventaire avant suppression

Avant de toucher au code, fais un `grep`/`rg` exhaustif sur les patterns :
- `social__dislike`
- `social/v2/dislikes`
- `dislikes_number`
- `dislike` (en CSS/HTML/JS)
- `dislike_btn`, `dislikeBtn`, etc.

Présente la liste des fichiers/lignes impactés à l'utilisateur **avant** de modifier quoi que ce soit.

### 2.2 Code à supprimer

Une fois validé :
- `functions.php` : supprimer la route REST `social/v2/dislikes/{id}` et le commentaire `// (Add social__dislike and any other missing functions as needed)`
- `js/main.js` : supprimer le bloc `dislike` (~lignes 80-94) et le sélecteur associé
- Templates concernés (probablement `single.php` ou `player.php`) : supprimer le bouton dislike côté HTML
- CSS : supprimer les styles dédiés au bouton dislike s'ils sont isolés

### 2.3 Ce qu'il **ne faut pas** toucher

- Le champ ACF `dislikes_number` reste en BDD (les données existantes sont conservées au cas où la feature reviendrait, et la suppression ACF est une décision business à part).
- La feature `like` reste active (sera sécurisée à l'étape 3).

### 2.4 Commit dédié

```bash
git add .
git commit -m "fix(security): remove unfinished dislike feature (SEC-002)

- Remove undefined social__dislike REST route
- Remove dislike button and JS handler
- Keep dislikes_number ACF field intact (data preservation)"
```

### 2.5 Validation utilisateur

- Liste précisément les fichiers et lignes modifiés
- Confirme qu'aucun appel résiduel à `dislike` ne subsiste (refais un `grep` final)
- **Attends validation avant l'étape 3**

---

## Étape 3 — Sécurisation de l'endpoint `likes` (SEC-001)

### 3.1 Stratégie de protection retenue

Triple verrou :
1. **`permission_callback` explicite** (`__return_true` documenté, plus de warning WP)
2. **Nonce REST** (`X-WP-Nonce` envoyé par le JS, vérifié côté PHP)
3. **Rate-limit transient** : 1 like par IP par post par heure

### 3.2 Côté PHP (`functions.php`)

Modifications attendues :

```php
register_rest_route('social/v2', '/likes/(?P<id>\d+)', [
    'methods'             => WP_REST_Server::CREATABLE, // POST uniquement
    'callback'            => 'lmt_social_like',
    'permission_callback' => 'lmt_social_like_permission',
    'args'                => [
        'id' => [
            'validate_callback' => function($param) {
                return is_numeric($param) && get_post((int) $param);
            },
        ],
    ],
]);
```

Le `permission_callback` :
- Vérifie le nonce REST (`wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')`)
- Retourne `WP_Error` 403 si nonce invalide

Le callback `lmt_social_like` (renommé selon convention `lmt_*` du `CLAUDE.md`) :
- Récupère l'IP via `$_SERVER['REMOTE_ADDR']` (sanitize via `filter_var(..., FILTER_VALIDATE_IP)`)
- Construit la clé transient : `lmt_like_{hash_ip}_{post_id}` (hasher l'IP via `wp_hash` pour éviter de stocker l'IP en clair → conformité RGPD)
- Si le transient existe → retourne 429 `WP_Error('lmt_rate_limited', __('Already liked', 'lamixtape'), ['status' => 429])`
- Sinon : incrémente `likes_number` via `update_field` (ACF) ou `update_post_meta`, pose le transient `HOUR_IN_SECONDS`, retourne le nouveau compteur

L'ancienne fonction `social__like` est supprimée. Préfixe les nouvelles fonctions en `lmt_*` (cf. règles `CLAUDE.md`).

### 3.3 Côté JS (`js/main.js`)

- Récupérer le nonce depuis la variable localisée (cf. 3.4)
- Ajouter le header `X-WP-Nonce` aux requêtes fetch/POST sur `/wp-json/social/v2/likes/{id}`
- Gérer le cas 429 (afficher un feedback discret type "Déjà aimé" ou désactiver le bouton)

### 3.4 Localisation du nonce (anticipation partielle de SEC-006)

Aujourd'hui, `wp_localize_script('jquery', 'bloginfo', [...])` est attaché au mauvais handle. Pour cette phase, **on garde le handle existant temporairement** mais on ajoute la clé `nonce` au tableau localisé :

```php
wp_localize_script('jquery', 'bloginfo', [
    'template_url' => get_template_directory_uri(),
    'site_url'     => site_url(),
    'post_id'      => get_queried_object_id(),
    'nonce'        => wp_create_nonce('wp_rest'),
]);
```

Le refacto complet du handle (passage à `lmt-main`) est traité en Phase 1.

### 3.5 Tests manuels attendus

Avant de commiter, l'utilisateur doit valider :
- Un like fonctionne (compteur incrémenté côté UI + BDD)
- Un second like immédiat sur le même post depuis la même IP → bloqué (429)
- Un like sur un autre post → fonctionne
- Un like avec un nonce invalide (ex. cookie expiré) → 403

Tu fournis à l'utilisateur **une commande `curl` de test** pour chaque cas.

### 3.6 Commit dédié

```bash
git add .
git commit -m "fix(security): secure likes REST endpoint (SEC-001)

- Add explicit permission_callback with nonce verification
- Restrict to POST method only
- Add IP-based rate limit (1 like/IP/post/hour)
- Hash IP with wp_hash for GDPR compliance
- Localize wp_rest nonce to JS"
```

### 3.7 Validation utilisateur

Liste les modifications, fournis les commandes `curl` de test, et **attends la confirmation de fonctionnement avant de clôturer la phase**.

---

## Règles de travail

- **Lecture obligatoire de `CLAUDE.md` et `AUDIT.md`** avant toute modification
- **Un commit par étape** (3 commits attendus à la fin de la phase)
- **Aucun commit auto-amende ou squash** sans demande explicite
- **Préfixe `lmt_`** sur toute nouvelle fonction (convention validée dans `CLAUDE.md`)
- **Text-domain** : si tu introduis un nouveau string, utilise `'lamixtape'` (le find/replace global du placeholder `'text-domain'` est traité en Phase 2 ; pour la Phase 0, n'aggrave pas la dette)
- **Toute query SQL custom** doit passer par `$wpdb->prepare`
- **Si une décision business inattendue émerge**, arrête-toi et demande
- **Si le scope d'une étape déborde**, signale-le plutôt que de le faire en silence

---

## Checkpoint final de Phase 0

À la fin de la phase, produis un récapitulatif synthétique :

| Élément | Statut |
|---|---|
| Commit initial git | SHA + nb fichiers |
| Suppression dislike | Liste fichiers modifiés |
| Sécurisation likes | Liste fichiers modifiés + résultats des tests curl |
| `CLAUDE.md` à mettre à jour ? | Oui/Non + quelles sections |
| Findings restants pour Phase 1 | Liste les IDs (cf. ordre d'attaque CLAUDE.md §5) |

Mets à jour `CLAUDE.md` si nécessaire (ex. marquer SEC-001/SEC-002/QC-001 comme `[résolu Phase 0]` dans la section dette technique ou en note).

Confirme à l'utilisateur que la Phase 0 est close et **attends son feu vert avant de toucher à la Phase 1**.
