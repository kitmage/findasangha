#!/usr/bin/env bash
# Copy the fas-directory plugin from the repo mirror to the site and lint it.
set -uo pipefail
SRC="$HOME/private_html/fas_repo"
SITE="$HOME/public_html"
WPCLI() { ( cd "$SITE" && "$HOME/.cw/bin/wp" --path="$SITE" "$@" ); }
rm -rf "$SITE/wp-content/plugins/fas-directory"
cp -a "$SRC/wp-content/plugins/fas-directory" "$SITE/wp-content/plugins/fas-directory"
find "$SITE/wp-content/plugins/fas-directory" -name "*.php" -exec php -l {} \;
WPCLI plugin activate fas-directory
WPCLI cache flush || true
WPCLI eval 'if (has_action("breeze_clear_all_cache")) { do_action("breeze_clear_all_cache"); }' || true
WPCLI fas import --help 2>&1 | head -5 || true
echo "==PLUGIN DEPLOY DONE=="
