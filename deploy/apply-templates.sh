#!/usr/bin/env bash
# FaS templates deploy: run ON the staging server from the repo mirror.
# Copies theme + plugin from the mirror, wires Browse/Home page content, flushes caches.
set -uo pipefail
SRC="$HOME/private_html/fas_repo"
SITE="$HOME/public_html"
WPCLI() { ( cd "$SITE" && "$HOME/.cw/bin/wp" --path="$SITE" "$@" ); }

rm -rf "$SITE/wp-content/themes/fas-child" "$SITE/wp-content/plugins/fas-directory"
cp -a "$SRC/wp-content/themes/fas-child" "$SITE/wp-content/themes/fas-child"
cp -a "$SRC/wp-content/plugins/fas-directory" "$SITE/wp-content/plugins/fas-directory"
php -l "$SITE/wp-content/plugins/fas-directory/fas-directory.php"
php -l "$SITE/wp-content/plugins/fas-directory/includes/data-model.php"
php -l "$SITE/wp-content/plugins/fas-directory/includes/display.php"
php -l "$SITE/wp-content/themes/fas-child/functions.php"
WPCLI theme activate fas-child
WPCLI plugin activate fas-directory

BROWSE_ID=$(WPCLI post list --post_type=page --name="browse-sanghas" --field=ID | head -1)
HOME_ID=$(WPCLI post list --post_type=page --name="home" --field=ID | head -1)
WPCLI post update "$BROWSE_ID" --post_content="$(cat "$SRC/deploy/content/browse-sanghas.html")"
WPCLI post update "$HOME_ID" --post_content="$(cat "$SRC/deploy/content/home.html")"

WPCLI rewrite flush
WPCLI cache flush || true
WPCLI eval 'if (has_action("breeze_clear_all_cache")) { do_action("breeze_clear_all_cache"); }' || true
echo "==VERIFY=="
WPCLI theme list --status=active
WPCLI plugin list --status=active --field=name
curl -s https://findasangha.com/sanghas/ -o /tmp/fas_archive.html; echo "archive bytes: $(wc -c < /tmp/fas_archive.html)"
grep -o "fas-filter" /tmp/fas_archive.html | head -1
grep -o "Phap Nguyen Temple" /tmp/fas_archive.html | head -1
curl -s "https://findasangha.com/sanghas/?f_format=online" -o /tmp/fas_online.html; echo "online-filter bytes: $(wc -c < /tmp/fas_online.html)"
grep -c "fas-card" /tmp/fas_online.html || true
curl -s https://findasangha.com/sanghas/phap-nguyen-temple/ -o /tmp/fas_single.html; echo "single bytes: $(wc -c < /tmp/fas_single.html)"
grep -o "Pearland" /tmp/fas_single.html | head -1
grep -o "chuaphapnguyen" /tmp/fas_single.html | head -1
curl -s https://findasangha.com/browse-sanghas/ -o /tmp/fas_browse.html; echo "browse bytes: $(wc -c < /tmp/fas_browse.html)"
grep -o "fas-results" /tmp/fas_browse.html | head -1
echo "==DONE=="
