#!/bin/bash
#
# bin/deploy-sftp.sh
#
# Deploy WordPress theme to OVH SFTP via lftp mirror.
#
# Usage:
#   bash bin/deploy-sftp.sh                # Full sync with confirmation
#   bash bin/deploy-sftp.sh --dry-run      # Preview without uploading
#   bash bin/deploy-sftp.sh --connect-test # Test SFTP connection
#
# Last update : 2026-05-01 (Phase setup déploiement SFTP).
#
# IMPORTANT — case sensitivity :
#   The local repo path is `.../themes/Lamixtape/` (L majuscule)
#   on macOS (case-insensitive filesystem). The OVH server is Linux
#   (case-sensitive). The destination path in $SFTP_REMOTE_PATH must
#   be LOWERCASE strict (`lamixtape`) to match the active WordPress
#   theme directory on prod. Syncing to `Lamixtape` would create a
#   new directory beside the active one, leaving the live site
#   pointing to the old (un-updated) theme — an silent regression
#   that's hard to detect from the SFTP output.
#

set -euo pipefail

# ═══════════════════════════════════════════════════════════════════
# 1. Load .env
# ═══════════════════════════════════════════════════════════════════

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
THEME_DIR="$( dirname "$SCRIPT_DIR" )"
ENV_FILE="$THEME_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: $ENV_FILE not found."
    echo "Copy .env.example to .env and fill in the SFTP credentials."
    exit 1
fi

# Source .env (handles spaces, quotes safely via auto-export)
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

# ═══════════════════════════════════════════════════════════════════
# 2. Validate required env vars
# ═══════════════════════════════════════════════════════════════════

REQUIRED_VARS=("SFTP_HOST" "SFTP_USER" "SFTP_PORT" "SFTP_PASSWORD" "SFTP_REMOTE_PATH")
for VAR in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!VAR:-}" ] || [ "${!VAR}" = "your_password_here" ]; then
        echo "ERROR: $VAR is not set in .env (or still has placeholder value)."
        echo "Edit $ENV_FILE and fill in the real value."
        exit 1
    fi
done

# ═══════════════════════════════════════════════════════════════════
# 3. Validate lftp is installed
# ═══════════════════════════════════════════════════════════════════

if ! command -v lftp &> /dev/null; then
    echo "ERROR: lftp is not installed."
    echo "Install with: brew install lftp"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════
# 4. Parse mode argument
# ═══════════════════════════════════════════════════════════════════

MODE="full"
case "${1:-}" in
    --dry-run)
        MODE="dry-run"
        ;;
    --connect-test)
        MODE="connect-test"
        ;;
    "")
        MODE="full"
        ;;
    *)
        echo "Unknown argument: $1"
        echo "Usage: $0 [--dry-run | --connect-test]"
        exit 1
        ;;
esac

# ═══════════════════════════════════════════════════════════════════
# 5. Display deploy info
# ═══════════════════════════════════════════════════════════════════

echo "═══════════════════════════════════════════════════════════════"
echo "  SFTP DEPLOY — Lamixtape theme"
echo "═══════════════════════════════════════════════════════════════"
echo "Mode          : $MODE"
echo "Source local  : $THEME_DIR"
echo "Target server : $SFTP_USER@$SFTP_HOST:$SFTP_PORT"
echo "Target path   : $SFTP_REMOTE_PATH"
echo "Timestamp     : $(date '+%Y-%m-%d %H:%M:%S')"
echo "═══════════════════════════════════════════════════════════════"

# ═══════════════════════════════════════════════════════════════════
# 6. Build lftp exclude list
# ═══════════════════════════════════════════════════════════════════
#
# These files/dirs are dev-only and must NOT land on the prod server :
# - .git/, .github/    : VCS + CI workflows
# - node_modules/      : npm dev deps (audit tools)
# - vendor/            : Composer dev deps (PHPCS / WPCS)
# - _docs/             : internal documentation
# - bin/               : dev scripts (this script + check-headers.sh)
# - .env*              : secrets — JAMAIS sur le serveur
# - composer.*         : dev tooling configs
# - phpcs.xml.dist     : lint config
# - package.*          : npm config
# - .editorconfig      : editor config
# - .gitignore         : VCS only
# - composer.phar      : Composer binary (dev install)
# - README.md          : dev doc only (WP doesn't read it from theme)
# - *.log, *.tmp       : transient junk
# - .DS_Store          : macOS metadata
#

EXCLUDES=(
    "--exclude-glob=.git/"
    "--exclude-glob=.github/"
    "--exclude-glob=node_modules/"
    "--exclude-glob=vendor/"
    "--exclude-glob=_docs/"
    "--exclude-glob=bin/"
    "--exclude-glob=.env*"
    "--exclude-glob=.gitignore"
    "--exclude-glob=.editorconfig"
    "--exclude-glob=composer.json"
    "--exclude-glob=composer.lock"
    "--exclude-glob=composer.phar"
    "--exclude-glob=phpcs.xml.dist"
    "--exclude-glob=pa11y.json"
    "--exclude-glob=package.json"
    "--exclude-glob=package-lock.json"
    "--exclude-glob=README.md"
    "--exclude-glob=*.log"
    "--exclude-glob=*.tmp"
    "--exclude-glob=.DS_Store"
)

# ═══════════════════════════════════════════════════════════════════
# 7. Execute mode
# ═══════════════════════════════════════════════════════════════════

case "$MODE" in
    connect-test)
        echo "Testing SFTP connection..."
        echo
        lftp -u "$SFTP_USER,$SFTP_PASSWORD" -p "$SFTP_PORT" "sftp://$SFTP_HOST" <<EOF
set ssl:verify-certificate no
cd $SFTP_REMOTE_PATH
ls -la | head -10
bye
EOF
        echo
        echo "✓ Connection test successful."
        ;;

    dry-run)
        echo "DRY-RUN — no files will be uploaded."
        echo
        # lftp mirror with --dry-run flag previews without changes
        lftp -u "$SFTP_USER,$SFTP_PASSWORD" -p "$SFTP_PORT" "sftp://$SFTP_HOST" <<EOF
set ssl:verify-certificate no
mirror --reverse --delete --verbose --dry-run \\
    ${EXCLUDES[@]} \\
    "$THEME_DIR" "$SFTP_REMOTE_PATH"
bye
EOF
        echo
        echo "✓ Dry-run complete. Review the output above before running a real deploy."
        ;;

    full)
        echo "FULL SYNC — files will be uploaded to OVH production."
        echo "TARGET PATH (LOWERCASE strict)  : $SFTP_REMOTE_PATH"
        echo "If the path looks wrong, ABORT now and double-check the .env"
        echo "(L majuscule local vs lowercase prod — see script header)."
        echo
        read -p "Type 'yes' to confirm deployment: " CONFIRM
        if [ "$CONFIRM" != "yes" ]; then
            echo "Deployment cancelled."
            exit 0
        fi

        echo
        echo "Starting deploy..."
        lftp -u "$SFTP_USER,$SFTP_PASSWORD" -p "$SFTP_PORT" "sftp://$SFTP_HOST" <<EOF
set ssl:verify-certificate no
mirror --reverse --delete --verbose \\
    ${EXCLUDES[@]} \\
    "$THEME_DIR" "$SFTP_REMOTE_PATH"
bye
EOF
        echo
        echo "✓ Deploy complete."
        echo
        echo "Next steps:"
        echo "  1. Run sanity check: bash bin/check-headers.sh https://lamixtape.fr"
        echo "  2. Verify the site visually: https://lamixtape.fr"
        echo "  3. Purge Cloudflare cache if needed (manager.cloudflare.com)"
        echo "  4. Run Q14 deferred audits (cf. _docs/deployment-checklist.md)"
        ;;
esac

echo
echo "Done."
