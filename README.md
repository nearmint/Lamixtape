# Lamixtape

WordPress theme for [Lamixtape.fr](https://lamixtape.fr) — a music webzine (2011–2022) publishing monthly curated mixtapes.

## Stack

- PHP 8.2+
- WordPress 6.x
- Tailwind CSS v4 (standalone CLI, no Node in production)
- jQuery WP-bundled (like + burger + infinite scroll + MediaElement player)
- HTML5 native `<dialog>` (donate / contact modals)
- ACF Pro (`color`, `highlight`, `likes_number`, `tracklist` fields)
- Rank Math SEO (OG / Twitter / JSON-LD with MusicPlaylist injection via `rank_math/json_ld` filter)

## CI

GitHub Actions workflow `.github/workflows/lint.yml`:

- Triggers: push to main + PR to main + manual dispatch
- Job 1: `php -l` on all `*.php` files (matrix PHP 8.2 + 8.3)
- Job 2: `composer install` + `composer lint:summary` (WPCS)

## Conventions

- PHP naming: `lmt_*` prefix everywhere (functions, hooks, filters, options, transients, enqueue handles)
- Text domain: `lamixtape`
- `inc/` structure: flat files (queries, rest, seo) — no OOP layer
- Templates: no inline WP_Query, helpers extracted into `inc/queries.php`
- Assets: all via `wp_enqueue_*`, no hardcoded `<link>`/`<script>` in templates
- Escaping: systematic `esc_*`, custom queries via `$wpdb->prepare()`

## License

GNU General Public License v2 or later.
