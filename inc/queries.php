<?php
/**
 * Theme query layer.
 *
 * All custom WP_Query / get_posts / get_users calls used by templates
 * live here. Templates should never instantiate WP_Query directly:
 * they consume the return values of the lmt_get_* helpers below.
 *
 * Loaded from functions.php (require_once) — flat-file structure, no
 * class layer. See _docs/prompt-phase-2.md D6.
 *
 * @package Lamixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
