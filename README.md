# Lamixtape

WordPress theme for [Lamixtape.fr](https://lamixtape.fr) — webzine musical historique (2011–2022) qui publie des mixtapes mensuelles curatées.

## Stack

- PHP 8.2+
- WordPress 6.x
- Tailwind CSS v4 (CLI standalone, pas de Node en prod)
- jQuery WP-bundled (like + burger + infinite scroll + player MediaElement)
- `<dialog>` HTML5 natif (modals donate / contact)
- ACF Pro (champs `color`, `highlight`, `likes_number`, `tracklist`)
- Rank Math SEO (OG / Twitter / JSON-LD avec injection MusicPlaylist via filter `rank_math/json_ld`)

Voir `CLAUDE.md` pour le contexte complet (architecture, conventions, dette technique, historique des phases de refacto 0-8).

## Development

### Prerequisites

- PHP 8.2 ou plus
- Composer (pour PHP linting via WPCS)
- Node.js 18+ (pour audits Lighthouse / Pa11y)

### Setup

```bash
# Composer dev tools (WPCS PHP linter)
composer install

# npm dev tools (Lighthouse + Pa11y CLI pour audits)
npm install
```

### Build Tailwind CSS

```bash
# Single build (minified)
./assets/build/tailwindcss \
    -i assets/css/tailwind.input.css \
    -o assets/css/tailwind.css \
    --minify

# Watch mode (pendant le développement)
./assets/build/tailwindcss \
    -i assets/css/tailwind.input.css \
    -o assets/css/tailwind.css \
    --watch
```

Le binaire Tailwind CLI est gitignored (`assets/build/tailwindcss`) — chaque dev télécharge sa version platform depuis [tailwindcss.com/docs/installation](https://tailwindcss.com/docs/installation).

### Lint PHP (WPCS)

```bash
# Summary (count par fichier)
composer lint:summary

# Full report avec détails
composer lint

# Auto-fix where possible
composer lint:fix
```

Configuration : `phpcs.xml.dist` (WordPress-Core + WordPress-Docs).

### Audits

```bash
# Lighthouse Local (requiert https://lamixtape.local up)
npm run audit:lighthouse

# Pa11y Local (WCAG2AA)
npm run audit:pa11y
```

Configuration Pa11y : `pa11y.json` (chromeLaunchConfig.ignoreHTTPSErrors pour cert Local auto-signé).

Cf. `_docs/audit-post-refacto.md` pour le rapport baseline post-Phases 0-8.

## CI

GitHub Actions workflow `.github/workflows/lint.yml` :
- Triggers : push main + PR vers main + manual dispatch
- Job 1 : `php -l` sur tous les `*.php` (matrix PHP 8.2 + 8.3)
- Job 2 : `composer install` + `composer lint:summary` (WPCS)

Lighthouse / Pa11y NOT in CI (Option A choisie post-Phase-7) — re-audits prod manuels post-déploiement (cf. `CLAUDE.md` Q14).

## Conventions

- Naming PHP : préfixe `lmt_*` partout (functions, hooks, filters, options, transients, enqueue handles)
- Text-domain : `lamixtape`
- Structure `inc/` : flat-files (queries, rest, seo) — pas de couche OOP
- Templates : pas de WP_Query inline, helpers extraits dans `inc/queries.php`
- Assets : tous via `wp_enqueue_*`, aucun `<link>`/`<script>` en dur dans templates
- Échappement : `esc_*` systématique, requêtes custom via `$wpdb->prepare()`

Voir `CLAUDE.md` section 8 pour les règles complètes.

## License

GNU General Public License v2 or later.
