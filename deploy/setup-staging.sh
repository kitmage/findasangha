#!/usr/bin/env bash
# FaS first scaffold session: run ON the staging server from the repo mirror at
# ~/private_html/fas_repo (the app user's home itself is read-only on Cloudways;
# private_html is writable and not web-visible).
# Deploy = copy repo files into public_html/wp-content (never edit in place on the server),
# then WP-CLI for options, pages, and seed listings. Idempotent: safe to re-run.
set -uo pipefail

SRC="$HOME/private_html/fas_repo"
SITE="$HOME/public_html"
# Cloudways wp-config.php does a relative require of wp-salt.php, so WP-CLI only
# loads WordPress when the working directory is the site root. Run every call there.
WPCLI() { ( cd "$SITE" && "$HOME/.cw/bin/wp" --path="$SITE" "$@" ); }

echo "==VERIFY BEFORE=="
whoami; hostname; php -v | head -1
WPCLI core version
WPCLI theme list || true
WPCLI plugin list || true

echo "==DEPLOY FILES FROM REPO MIRROR=="
rm -rf "$SITE/wp-content/themes/fas-child" "$SITE/wp-content/plugins/fas-directory"
cp -a "$SRC/wp-content/themes/fas-child" "$SITE/wp-content/themes/fas-child"
cp -a "$SRC/wp-content/plugins/fas-directory" "$SITE/wp-content/plugins/fas-directory"
ls "$SITE/wp-content/themes/fas-child" "$SITE/wp-content/plugins/fas-directory"

echo "==THEME AND PLUGIN=="
WPCLI theme is-installed twentytwentyfive || WPCLI theme install twentytwentyfive
WPCLI theme activate fas-child
WPCLI plugin activate fas-directory
WPCLI php -r 'echo "php-lint-ok\n";' 2>/dev/null || true
php -l "$SITE/wp-content/plugins/fas-directory/fas-directory.php"
php -l "$SITE/wp-content/plugins/fas-directory/includes/data-model.php"
php -l "$SITE/wp-content/themes/fas-child/functions.php"

echo "==OPTIONS=="
WPCLI option update blogname "Find a Sangha"
WPCLI option update blogdescription "A global directory of Buddhist sanghas: online, in person, and hybrid."
WPCLI option update timezone_string "America/Chicago"
WPCLI option update date_format "Y-m-d"
WPCLI option update permalink_structure "/%postname%/"
WPCLI option update blog_public "0"
WPCLI rewrite flush --hard || WPCLI rewrite flush

echo "==PAGES=="
ensure_page() {
  local title="$1" slug="$2" content="$3"
  local existing
  existing=$(WPCLI post list --post_type=page --name="$slug" --field=ID 2>/dev/null | head -1)
  if [ -n "$existing" ]; then echo "page exists: $slug ($existing)"; echo "$existing"; return; fi
  WPCLI post create --post_type=page --post_status=publish --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain
}
HOME_ID=$(ensure_page "Home" "home" "Find a sangha. Search Buddhist communities worldwide: online, in person, and hybrid. Search and filters arrive with the directory launch; the first hand-checked listings are being prepared now.")
ensure_page "Browse Sanghas" "browse-sanghas" "Browse all sangha listings. Filters by tradition, format, language, and features are part of the directory build now in progress." >/dev/null
ensure_page "Find Online Sanghas" "find-online-sanghas" "Sanghas that meet online, listed with their own timezone shown plainly." >/dev/null
ensure_page "Add Your Sangha" "add-your-sangha" "Public submissions open at launch. Every listing is hand-checked before it appears." >/dev/null
ensure_page "Claim Your Listing" "claim-your-listing" "If your sangha is listed and you represent it, you will be able to claim the listing so you can keep its details current. Claims open at launch." >/dev/null
ensure_page "How Verification Works" "how-verification-works" "Verified means a listing existed and its details were current when checked. It never means endorsement of a teacher or a teaching. Listings are reconfirmed on a regular cycle." >/dev/null
ensure_page "Review Guidelines" "review-guidelines" "Reviews are firsthand experience only, written in the spirit of Right Speech. No doctrinal verdicts. Structured answers first, free text second. Reviews open after launch." >/dev/null
ensure_page "About" "about" "Find a Sangha is a global directory of Buddhist sanghas, built to help practitioners find community wherever they live." >/dev/null
ensure_page "Contact" "contact" "Contact details for the directory team will be published here before launch." >/dev/null
ensure_page "Privacy" "privacy" "Privacy policy placeholder. Key commitments: contact emails are never displayed raw, and home-based sanghas may list at city level only, with no exact address and no home map pin." >/dev/null
ensure_page "Terms" "terms" "Terms of use placeholder, to be completed before public launch." >/dev/null
WPCLI option update show_on_front "page"
WPCLI option update page_on_front "$HOME_ID"

echo "==MENU (best effort; block themes render a page-list navigation by default)=="
WPCLI menu create "Primary" 2>/dev/null || true
WPCLI menu location assign primary primary 2>/dev/null || true

echo "==TAXONOMY TERMS (seeded by plugin on init)=="
for tax in tradition format language features country region lineage_or_school; do
  printf "%s: " "$tax"; WPCLI term list "$tax" --field=name 2>/dev/null | paste -sd ", " -; echo
done

echo "==LISTINGS=="
ensure_listing() {
  local title="$1" slug="$2" status="$3" content="$4"
  local existing
  existing=$(WPCLI post list --post_type=sangha_listing --name="$slug" --post_status=any --field=ID 2>/dev/null | head -1)
  if [ -n "$existing" ]; then echo "listing exists: $slug ($existing)"; echo "$existing"; return; fi
  WPCLI post create --post_type=sangha_listing --post_status="$status" --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain
}
VS_ID=$(ensure_listing "Virtual Sangha" "virtual-sangha" "publish" "An online Buddhist sangha connecting lay practitioners with experienced Dharma teachers and mentors. Founded as a refuge from online misinformation, with a Discord community and regular practice and study offerings.")
WPCLI post meta update "$VS_ID" website_url "https://virtualsangha.org"
WPCLI post meta update "$VS_ID" source_url "https://virtualsangha.org"
WPCLI post meta update "$VS_ID" last_confirmed_date "2026-10-25"
WPCLI post meta update "$VS_ID" sangha_timezone "America/Chicago"
WPCLI post term set "$VS_ID" tradition "Non-sectarian"
WPCLI post term set "$VS_ID" format "Online"
WPCLI post term set "$VS_ID" language "English"
WPCLI post term set "$VS_ID" features "Beginner friendly" "Teacher led"
WPCLI post term set "$VS_ID" country "United States"

PV_ID=$(ensure_listing "Plum Village Monastery" "plum-village-monastery" "draft" "Monastery founded by Thich Nhat Hanh in southwest France, root community of the Plum Village tradition. Draft seed listing: details to be hand-confirmed against the source before publishing.")
WPCLI post meta update "$PV_ID" website_url "https://plumvillage.org"
WPCLI post meta update "$PV_ID" source_url "https://plumvillage.org"
WPCLI post term set "$PV_ID" tradition "Plum Village tradition"
WPCLI post term set "$PV_ID" format "In person" "Hybrid"
WPCLI post term set "$PV_ID" language "English"
WPCLI post term set "$PV_ID" country "France"

IMS_ID=$(ensure_listing "Insight Meditation Society" "insight-meditation-society" "draft" "Retreat center in Barre, Massachusetts, in the Insight / Vipassana tradition. Draft seed listing: details to be hand-confirmed against the source before publishing.")
WPCLI post meta update "$IMS_ID" website_url "https://www.dharma.org"
WPCLI post meta update "$IMS_ID" source_url "https://www.dharma.org"
WPCLI post meta update "$IMS_ID" city "Barre"
WPCLI post term set "$IMS_ID" tradition "Insight / Vipassana"
WPCLI post term set "$IMS_ID" format "In person"
WPCLI post term set "$IMS_ID" language "English"
WPCLI post term set "$IMS_ID" country "United States"
WPCLI post term set "$IMS_ID" region "Massachusetts"

echo "==VERIFY AFTER=="
WPCLI core version
for k in siteurl home blogname timezone_string permalink_structure blog_public show_on_front page_on_front; do printf "%s=" "$k"; WPCLI option get "$k"; done
WPCLI theme list
WPCLI plugin list
WPCLI post list --post_type=page --fields=ID,post_title,post_name,post_status
WPCLI post list --post_type=sangha_listing --post_status=any --fields=ID,post_title,post_name,post_status
echo "listing terms VS:"; for t in tradition format language features country; do printf "%s: " "$t"; WPCLI post term list "$VS_ID" "$t" --field=name | paste -sd "," -; echo; done
echo "==DONE=="
