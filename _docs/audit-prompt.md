# Prompt Claude Code — Audit thème WordPress Lamixtape.fr

> À copier-coller dans Claude Code, à la racine du repo du thème.

---

Tu es chargé d'un audit complet du thème WordPress custom de Lamixtape.fr en vue d'un refacto majeur. Ta mission est exclusivement un travail d'audit et de documentation. **Tu ne dois modifier aucun fichier de code.**

## 1. Contexte projet

- **Site** : Lamixtape.fr
- **Type** : Thème WordPress custom (probablement classique, non-FSE — à confirmer par l'audit)
- **Stack actuelle connue** : Bootstrap (à migrer vers Tailwind)
- **Environnements disponibles** : dev local + prod (pas de staging)
- **Objectifs du refacto** :
  1. Améliorer la structure des fichiers
  2. Renforcer la sécurité
  3. Optimiser les performances
  4. Améliorer la qualité du code (refacto, commentaires, lisibilité)
  5. Respecter les bonnes pratiques WordPress
  6. Migrer Bootstrap → Tailwind
  7. Améliorer l'accessibilité
  8. Toute autre amélioration que tu juges pertinente

## 2. Mission

1. **Phase exploration** : explorer le repo de manière exhaustive
2. **Phase audit** : analyser le code selon les axes définis ci-dessous
3. **Phase documentation** : produire deux livrables :
   - `CLAUDE.md` à la racine (mémoire de travail pour les futures sessions Claude Code)
   - `AUDIT.md` à la racine (findings détaillés, classés par sévérité)

Si quelque chose est ambigu ou nécessite une décision business, **arrête-toi et pose la question** plutôt que de présumer.

## 3. Phase exploration — découverte du repo

Inventorie systématiquement :

- Arborescence complète des fichiers (hors `node_modules`, `vendor`, `.git`)
- Structure du thème : `functions.php`, templates, partials, includes
- Stack PHP : version cible, namespaces, autoloading, classes vs procédural
- Stack front : CSS (Bootstrap version, SCSS, vanilla), JS (jQuery, vanilla, modules)
- Build tool éventuel : `package.json`, `gulpfile`, `webpack.config`, `vite.config`, scripts npm
- Dépendances : `composer.json`, `package.json` (versions, vulnérabilités évidentes)
- Plugins critiques utilisés ou supposés (ACF, WooCommerce, Yoast, etc. — à inférer du code)
- Custom Post Types, taxonomies, hooks, shortcodes
- Fichiers de config : `.htaccess`, `wp-config` éventuellement référencé, `theme.json`
- Présence d'un child theme, d'un parent, ou thème standalone

Documente le résultat dans `CLAUDE.md` (section "État des lieux").

## 4. Phase audit — axes à analyser

### 4.1 Sécurité

- Sanitization des inputs (`sanitize_text_field`, `wp_kses`, etc.)
- Escaping des outputs (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- Nonces sur les formulaires et actions AJAX
- Vérification des capabilities (`current_user_can`)
- Requêtes SQL : usage de `$wpdb->prepare` systématique
- Secrets ou clés API en dur dans le code
- Inclusions de fichiers non sécurisées
- Failles XSS, CSRF, injection potentielles
- Headers de sécurité côté thème
- Validation des uploads, des paramètres GET/POST

### 4.2 Performance

- Enqueue assets : ordre, dépendances, defer/async, conditionnel par template
- CSS/JS : minification, concaténation, fichiers inutilisés, code mort
- Images : formats (WebP/AVIF), `loading="lazy"`, `srcset`, dimensions
- Requêtes DB : `WP_Query` non optimisées, N+1, `posts_per_page = -1`
- Caching : transients, object cache, fragment caching
- Hooks coûteux dans des boucles
- Polices : preload, font-display, sous-ensemble
- Core Web Vitals attendus (LCP, CLS, INP) — points de vigilance dans le code

### 4.3 Accessibilité (WCAG 2.1 AA)

- Sémantique HTML (landmarks, headings, listes)
- Attributs ARIA : pertinents, non redondants
- Contrastes de couleurs (extraire la palette du CSS)
- Navigation clavier : focus visible, tabindex, skip links
- Alt texts dynamiques sur les images
- Formulaires : labels, messages d'erreur, autocomplete
- Vidéos/audio : sous-titres, transcriptions
- Textes alternatifs et lecteurs d'écran
- Animations : `prefers-reduced-motion`

### 4.4 Qualité code & dette technique

- Architecture : séparation logique/template, MVC partiel, classes vs functions.php fourre-tout
- Duplication de code (DRY)
- Naming : conventions, cohérence, préfixage
- Standards : WordPress Coding Standards, PSR-12 si OOP
- Hooks WP : actions/filters bien utilisés vs hardcoded
- Templates : usage de `get_template_part`, hiérarchie respectée
- Logique métier dans les templates (à extraire)
- Commentaires : présence, qualité, docblocks
- Code mort, fichiers obsolètes, TODOs trainants

### 4.5 Bonnes pratiques WordPress

- Template hierarchy respectée
- `theme.json` présent et exploité
- Internationalisation (`__()`, `_e()`, text domain cohérent)
- `function_exists` / `class_exists` pour éviter les conflits
- Enqueue propre (pas de `<link>`/`<script>` en dur)
- Pas de modification du core
- Custom Post Types et taxonomies correctement déclarés
- REST API : endpoints custom, permissions
- Block editor : compat, custom blocks, patterns

### 4.6 Migration Bootstrap → Tailwind

- Version actuelle de Bootstrap (3, 4, 5 ?)
- Composants Bootstrap utilisés (carousel, modal, dropdown, navbar, grid…)
- Dépendances JS de Bootstrap (jQuery, Popper)
- Classes Bootstrap omniprésentes vs localisées
- Stratégie de migration recommandée (cohabitation temporaire, big bang, par template)
- Recommandation **Tailwind v3 vs v4** selon le contexte (PHP version, build tool, complexité)
- Composants à reconstruire vs alternatives headless (Alpine.js, Stimulus, vanilla)

### 4.7 Bonus — autres axes pertinents

Si tu identifies d'autres dimensions importantes (SEO technique, RGPD/cookies, structured data, robustesse édito, observabilité, CI/CD), ajoute-les.

## 5. Livrable 1 — `CLAUDE.md`

Crée un fichier `CLAUDE.md` à la racine du repo, structuré ainsi :

```markdown
# CLAUDE.md — Lamixtape.fr

## 1. Contexte projet
Synthèse 1 paragraphe : objectif du site, stack, environnements, contraintes connues.

## 2. État des lieux technique
Tableau ou liste structurée :
- Arborescence haut niveau
- Stack PHP / JS / CSS / Build
- Plugins et dépendances clés
- Custom Post Types, taxonomies, hooks notables

## 3. Conventions et standards en vigueur
- Conventions de nommage observées
- Patterns récurrents
- Standards (ou absence)

## 4. Dette technique priorisée
Tableau synthétique : axe / sévérité / résumé / référence vers AUDIT.md

## 5. Recommandations stratégiques
- Stack cible recommandée (Tailwind v3 ou v4 + justification)
- Approche de refacto recommandée (itératif vs big bang) avec arguments
- Ordre d'attaque suggéré

## 6. Glossaire & spécificités métier
Termes spécifiques au site, sections particulières, contraintes éditoriales devinées du code.

## 7. Questions ouvertes
Décisions business / produit nécessaires avant de démarrer.

## 8. Règles pour les futures sessions Claude Code
- Conventions à respecter dans le refacto
- Fichiers sensibles à ne pas toucher sans validation
- Workflow de travail attendu
```

## 6. Livrable 2 — `AUDIT.md`

Crée un fichier `AUDIT.md` à la racine, contenant la liste exhaustive des findings, organisés par axe (Sécurité, Performance, Accessibilité, Qualité code, WP best practices, Migration Tailwind, Autres).

Pour chaque finding, format obligatoire :

```markdown
### [SEV-XXX] Titre court du finding
- **Sévérité** : Critique / Haute / Moyenne / Basse
- **Axe** : Sécurité / Performance / A11y / Qualité / WP / Tailwind / Autres
- **Fichier(s)** : chemin:ligne
- **Description** : ce qui ne va pas, en 2-3 lignes
- **Impact** : ce que ça produit (faille, lenteur, bug d'accessibilité, etc.)
- **Recommandation** : la correction proposée, concise et actionnable
```

Échelle de sévérité :
- **Critique** : faille de sécurité exploitable, bug bloquant, perte de données potentielle
- **Haute** : impact utilisateur ou maintenabilité fort, à traiter en priorité
- **Moyenne** : à corriger mais non bloquant
- **Basse** : nice-to-have, polish

Termine `AUDIT.md` par une **synthèse chiffrée** : nombre de findings par sévérité et par axe (tableau).

## 7. Règles de travail strictes

- **Aucune modification de code** durant cette phase. Lecture seule.
- **Aucun fichier créé** en dehors de `CLAUDE.md` et `AUDIT.md`.
- Si une question business / produit est nécessaire pour finaliser une recommandation, **liste-la dans la section "Questions ouvertes" du `CLAUDE.md`** plutôt que de présumer.
- Si une partie du code est trop volumineuse pour être analysée exhaustivement, **signale-le explicitement** plutôt que de produire une analyse partielle silencieuse.
- Reste factuel : pas de jugement de valeur, des constats et des recommandations.
- Français pour tous les contenus rédigés.

## 8. Démarrage

Commence par explorer le repo, puis confirme-moi en une dizaine de lignes :
1. Ce que tu as trouvé (stack, taille du code, architecture)
2. Les zones où tu vas creuser en priorité
3. Les éventuelles ambiguïtés à lever avant de produire les livrables

Puis attends mon feu vert pour produire `CLAUDE.md` et `AUDIT.md`.
