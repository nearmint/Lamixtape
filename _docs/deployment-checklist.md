# Deployment Checklist — Lamixtape Theme (Phases 0-8)

**Created** : 1er mai 2026
**Scope** : déploiement prod du refacto thème complet (Phases 0-8 closes, ~159 commits sur `main`)
**Target** : `https://lamixtape.fr`

> Cette checklist accompagne le déploiement du refacto. Cocher chaque case avant / pendant / après le push prod. Garder ce fichier à jour si la procédure évolue (CI, hébergeur, etc.).

---

## Pré-déploiement

- [ ] **Captures `_docs/captures-pre-deploy/` posées** (les 8 templates : home, single, category, search, 404, explore, guests, text). Sert de baseline pour rollback / diff visuel post-déploiement.
- [ ] **Vérifier merge `main` complet** : `git log --oneline | wc -l` doit être ~180+ commits. Working tree clean (`git status` → nothing to commit).
- [ ] **Tag git posé** : `git tag -a refacto-complete -m "Refacto thème Lamixtape complet — Phases 0 à 8"` puis `git push origin refacto-complete`. Permet rollback facile (`git reset --hard refacto-complete~1` si besoin).
- [ ] **`CLAUDE.md` à jour** : sections 4 (dette technique, 63/72 résolus + 9 reportés), 7 (Q14 audits prod différés, Q15 Phase 8 vraie reportée).
- [ ] **`.env` rempli** avec le SFTP_PASSWORD réel (récupéré depuis le manager OVH > Web Cloud > Hosting > FTP-SSH > Modify password). Ne JAMAIS committer `.env` (gitignored).
- [ ] **Cluster OVH vérifié** dans `.env` : ouvrir le manager OVH et confirmer le hostname exact `ftp.clusterXXX.hosting.ovh.net` (105 ou autre selon le compte). Ajuster `SFTP_HOST` dans `.env` si différent du default.
- [ ] **`lftp` installé** : `lftp --version` doit retourner v4.x. Sinon `brew install lftp`.
- [ ] **Backup OVH côté serveur** posé d'abord via manager OVH > Web Cloud > Hosting > Backups > Create backup. Sert de filet de sécurité avant le premier deploy du refacto complet.

---

## Configuration infrastructure (à faire manuellement, hors thème)

### wp-config.php

- [ ] **`WP_POST_REVISIONS`** (cf. PERF-014 / WP-006 reportés infrastructure) : ajouter avant la ligne `require_once ABSPATH . 'wp-settings.php';` :
  ```php
  define( 'WP_POST_REVISIONS', 5 );
  ```
  Le thème a une mitigation Phase 1 (`389dd3c` wrap `if ( ! defined() )`) qui élimine le warning, mais la définition tardive dans `functions.php` n'est pas garantie d'être appliquée à temps. wp-config.php est l'emplacement correct.

### Favicons

- [ ] **Vérifier favicons existants en prod** (cf. OTHER-004 reporté) :
  ```bash
  for f in favicon.ico favicon-32x32.png favicon-16x16.png apple-touch-icon.png site.webmanifest safari-pinned-tab.svg; do
      curl -I -s -o /dev/null -w "%{http_code} - $f\n" https://lamixtape.fr/$f
  done
  ```
  Tous doivent retourner `200`. Si `404` → upload manuel via FTP/SFTP à la racine du document root **avant** le déploiement (sinon `header.php:7-13` réfère à des fichiers absents).

### Umami / RGPD

- [ ] **Conformité RGPD Umami** (cf. OTHER-001 reporté) :
  - Confirmer auprès du legal/CNIL que Umami Cloud bénéficie de l'exemption de consentement (anonymous analytics, no cookies)
  - Si exempté : mentionner Umami dans la page `legal-notice` (déjà liée dans le menu mobile via `header.php`)
  - Si non-exempté : ajouter un bandeau de consentement (hors scope thème, plugin tiers ou contenu admin)

### Rank Math (vérifications optionnelles)

- [ ] **Module Schema** : vérifier en admin WP → Rank Math → Titles & Meta → Posts si "Schema Type" est `Article` ou `Off`. Si `Off`, la fallback Phase 6 + refactor `92e7284` injecte MusicPlaylist via filter — donc OK même si Schema désactivé. Si activé en `Article`, le `@graph` contiendra Article + MusicPlaylist (coexistence valide).
- [ ] **Sitemap** : vérifier `https://lamixtape.fr/sitemap_index.xml` → `200`.

---

## Déploiement (SFTP via lftp — OVH mutualisé sans SSH shell)

OVH mutualisé n'expose pas de SSH shell — uniquement SFTP (port 22). Pas de `git pull` côté serveur, pas de webhook hébergeur. Le déploiement se fait via `bin/deploy-sftp.sh` (lftp full mirror).

**Procédure en 3 étapes**, à exécuter dans cet ordre strict :

### 1. `--connect-test` — sanity check connexion

```bash
bash bin/deploy-sftp.sh --connect-test
```

Doit afficher 10 entrées du `SFTP_REMOTE_PATH`. Si erreur d'authentification → vérifier le password dans `.env` (manager OVH > Modify password si nécessaire). Si erreur de path → vérifier `SFTP_REMOTE_PATH` (lowercase strict).

### 2. `--dry-run` — preview du sync

```bash
bash bin/deploy-sftp.sh --dry-run
```

Affiche la liste complète des fichiers qui seraient uploadés / supprimés sur le serveur. **REVIEWER attentivement** :
- Les fichiers attendus du refacto (functions.php, header.php, footer.php, single.php, etc.) sont bien dans la liste upload
- Les fichiers exclus (`.git/`, `vendor/`, `_docs/`, `bin/`, `.env*`, etc.) ne sont PAS listés
- Les fichiers à supprimer côté serveur sont des résidus pré-refacto attendus (vendor jQuery, Bootstrap, etc.) — pas du contenu user

Si la liste contient des surprises → ABORT et investiguer avant le full deploy.

### 3. Full deploy avec confirmation interactive

```bash
bash bin/deploy-sftp.sh
```

Le script affiche les paramètres de deploy + demande `Type 'yes' to confirm deployment`. Saisir `yes` pour lancer, autre input pour cancel.

**Note critique sur `SFTP_REMOTE_PATH`** : le path destination doit être en LOWERCASE strict (`lamixtape`, pas `Lamixtape`) — Linux serveur case-sensitive vs macOS local case-insensitive. Sync vers `Lamixtape` créerait un nouveau dossier orphelin à côté de l'actif → site live pointant vers l'ancien thème non-update. Le script affiche le path avant la confirmation pour permettre un last-second abort.

### 4. Purge cache Cloudflare

- [ ] **Purger cache Cloudflare** post-deploy (Cloudflare admin → Caching → Purge Everything). Sinon les fichiers CSS/JS cachés masqueront les modifications côté visiteur.

---

## Post-déploiement — Sanity check (immédiat)

- [ ] **Sanity check home** : `https://lamixtape.fr` charge sans erreur PHP/JS console
- [ ] **View-source home + 1 mixtape** : Open Graph + Twitter Cards + JSON-LD bien posés (Rank Math priorité 10 ou fallback Phase 6 selon état RM)
  ```bash
  curl -s https://lamixtape.fr/ | grep -E 'og:|twitter:|application/ld\+json'
  curl -s https://lamixtape.fr/<une-mixtape>/ | grep -E 'og:|twitter:|application/ld\+json'
  ```
- [ ] **Test player** : ouvrir 1 mixtape, cliquer une track, vérifier play/pause + seekbar + bouton like
- [ ] **Test burger menu mobile + modals** : burger ouvre/ferme, ESC ferme, focus restored. Modals donate + contact ouvrent/ferment au clavier.
- [ ] **Test infinite scroll home** : scroll jusqu'au bout, vérifier que les ~370 mixtapes se chargent par batch de 30.
- [ ] **Test 1 search query** : `/search/dub` retourne des résultats sans erreur PHP.

## Post-déploiement — Tests automatisés (immédiat)

- [ ] **Headers prod** : `bash bin/check-headers.sh https://lamixtape.fr`
  Doit retourner exit code 0 (tous les checks ✓). Si ✗ : Cloudflare a peut-être stripé un header, à vérifier au cas par cas.

## Post-déploiement — Audits Q14 différés (à planifier dans la semaine)

- [ ] **Lighthouse prod** sur les 4 URLs auditées Phase 7 (home, classic-masters, hip-hop, search/dub) :
  ```bash
  npm run audit:lighthouse -- https://lamixtape.fr/
  npm run audit:lighthouse -- https://lamixtape.fr/classic-masters/
  npm run audit:lighthouse -- https://lamixtape.fr/category/hip-hop/
  npm run audit:lighthouse -- https://lamixtape.fr/search/dub/
  ```
  Comparer scores avec baseline Local Phase 7 (`_docs/audit/lighthouse/`). Cloudflare cache devrait significativement améliorer les LCP (single 9.8s Local → cible <3s prod).

- [ ] **Pa11y prod** sur les 4 URLs :
  ```bash
  npm run audit:pa11y -- https://lamixtape.fr/
  npm run audit:pa11y -- https://lamixtape.fr/classic-masters/
  npm run audit:pa11y -- https://lamixtape.fr/category/hip-hop/
  npm run audit:pa11y -- https://lamixtape.fr/search/dub/
  ```
  Critique : confirmer que le finding "contraste 3.82:1" (reclassé false positive Phase 7) ne réapparaît pas / reste bénin.

- [ ] **PageSpeed Insights mobile + desktop** :
  ```bash
  curl "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://lamixtape.fr&strategy=mobile" > psi-mobile.json
  curl "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://lamixtape.fr&strategy=desktop" > psi-desktop.json
  ```

- [ ] **Mozilla Observatory** (cible grade A ou A+) :
  ```bash
  curl -X POST "https://http-observatory.security.mozilla.org/api/v1/analyze?host=lamixtape.fr"
  sleep 60
  curl "https://http-observatory.security.mozilla.org/api/v1/analyze?host=lamixtape.fr"
  ```

- [ ] **securityheaders.com** (cible grade A/A+) :
  ```bash
  curl -s "https://securityheaders.com/?q=https%3A%2F%2Flamixtape.fr&hide=on&followRedirects=on"
  ```

- [ ] **Validator schema.org** sur 1 mixtape live :
  Ouvrir `https://validator.schema.org/#url=https%3A%2F%2Flamixtape.fr%2F<une-mixtape>%2F` dans un browser. Vérifier 0 error sur le `MusicPlaylist` injecté (cf. OTHER-006 refactor `92e7284`).

- [ ] **Comparaison Core Web Vitals avant / après** : si baseline pré-refacto disponible (Search Console / GSC + Web Vitals report), comparer LCP / INP / CLS avant et après pour quantifier l'impact réel du refacto sur les utilisateurs.

- [ ] **Captures `_docs/captures-post-deploy/`** posées sur les 8 templates pour archive historique + diff vs `_docs/captures-pre-deploy/`.

---

## Rollback (si nécessaire)

OVH mutualisé n'expose pas de `git pull` côté serveur. Le rollback passe **soit par le manager OVH (backup automatique)**, **soit par un re-deploy SFTP du tag git précédent** (méthode 2). Méthode 1 est plus rapide si une régression UX critique est détectée.

### Méthode 1 — Restore backup OVH (recommandée si régression critique)

- [ ] Manager OVH → Web Cloud → Hosting → Backups → identifier le backup posé en pré-déploiement → "Restore"
- [ ] OVH restaure les fichiers du serveur à l'état du backup (typiquement quelques minutes pour un thème de cette taille)
- [ ] **Purger cache Cloudflare** post-rollback
- [ ] **Validation** : `_docs/captures-pre-deploy/` vs état restauré. Iso-visuel attendu.
- [ ] Investiguer le bug en local sur la branche `main` post-rollback, fix, re-deploy via la procédure standard

### Méthode 2 — Re-deploy SFTP d'un état git précédent

- [ ] **Identifier le commit fautif** via `git log --oneline | head -20` + correlation avec le symptôme. La granularité 1 commit = 1 changement (Phases 0-8) facilite le bisect.
- [ ] **Checkout local du tag de référence** : `git checkout refacto-complete~1` (état pré-refacto) OU `git checkout <SHA-stable>` (commit avant le bug)
- [ ] `bash bin/deploy-sftp.sh --dry-run` pour preview du rollback
- [ ] `bash bin/deploy-sftp.sh` pour effectuer le re-deploy
- [ ] **Purger cache Cloudflare** post-rollback
- [ ] Une fois fixé en local : `git checkout main`, fix, commit, re-deploy via la procédure standard

### Choix méthode

- Méthode 1 (OVH backup) : plus rapide, ne touche que le serveur, sécurité maximale. **Recommandée pour régression critique en heures de pointe.**
- Méthode 2 (re-deploy git) : plus chirurgicale, permet de tester le rollback en dry-run d'abord, contrôle exact de l'état restauré. **Recommandée hors urgence.**

---

## Notes

- **Aucun changement BDD attendu** par le déploiement (pas de migration). Tous les changements Phases 0-8 sont code thème + assets.
- **Plugins WP** (ACF Pro, CF7, Rank Math, Akismet, etc.) : aucune modification requise par le refacto thème.
- **PHP version** : prod doit être >=8.2 (cf. CLAUDE.md section 2). Si <8.2, blocage déploiement avant cette checklist.
- **CI passe vert** sur main avant déploiement : vérifier https://github.com/nearmint/Lamixtape/actions (workflow `lint.yml` Phase A2).

## Liens utiles

- `CLAUDE.md` — contexte projet complet
- `_docs/AUDIT.md` — 72 findings audités, 63 résolus + 9 reportés
- `_docs/audit-post-refacto.md` — rapport audit Phase 7 baseline Local
- `bin/check-headers.sh` — script de vérification des headers HTTP runtime
- `phpcs.xml.dist` — config WPCS pour `composer lint`
- `.github/workflows/lint.yml` — CI workflow PHP lint
