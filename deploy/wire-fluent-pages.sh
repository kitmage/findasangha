#!/usr/bin/env bash
# Wire the Fluent forms into the Add / Claim pages and create the account page.
# Run on the server after deploy/create-fluent-forms.php has run.
set -uo pipefail
SITE="$HOME/public_html"
WPCLI() { ( cd "$SITE" && "$HOME/.cw/bin/wp" --path="$SITE" "$@" ); }

SUBMIT_ID="$(WPCLI option get fas_fluent_submit_form_id)"
CLAIM_ID="$(WPCLI option get fas_fluent_claim_form_id)"
REGISTER_ID="$(WPCLI option get fas_fluent_register_form_id)"
echo "forms: submit=$SUBMIT_ID claim=$CLAIM_ID register=$REGISTER_ID"

ADD_ID="$(WPCLI post list --post_type=page --name=add-your-sangha --field=ID)"
CLAIM_PAGE_ID="$(WPCLI post list --post_type=page --name=claim-your-listing --field=ID)"
REGISTER_PAGE_ID="$(WPCLI post list --post_type=page --name=create-account --field=ID)"
if [ -z "$REGISTER_PAGE_ID" ]; then
  REGISTER_PAGE_ID="$(WPCLI post create --post_type=page --post_status=publish --post_title='Create Account' --post_name=create-account --porcelain)"
fi
echo "pages: add=$ADD_ID claim=$CLAIM_PAGE_ID register=$REGISTER_PAGE_ID"

read -r -d '' ADD_CONTENT <<'HTML' || true
<!-- wp:paragraph -->
<p>Tell us about your sangha. Every listing is checked by hand before it appears, and contact emails are never shown publicly.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Submitting takes a free account, so we can reach the person who knows the listing best. <a href="/create-account/">Create an account</a> or <a href="/wp-login.php">sign in</a>, then the form below will open.</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[fluentform id="__SUBMIT_ID__"]
<!-- /wp:shortcode -->
HTML
ADD_CONTENT="${ADD_CONTENT/__SUBMIT_ID__/$SUBMIT_ID}"
WPCLI post update "$ADD_ID" --post_content="$ADD_CONTENT"

read -r -d '' CLAIM_CONTENT <<'HTML' || true
<!-- wp:paragraph -->
<p>If your sangha is already listed and you help run it, claim the listing to keep its details current. Claims are reviewed by hand.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Claiming takes a free account. <a href="/create-account/">Create an account</a> or <a href="/wp-login.php">sign in</a>, then use the form below.</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[fluentform id="__CLAIM_ID__"]
<!-- /wp:shortcode -->
HTML
CLAIM_CONTENT="${CLAIM_CONTENT/__CLAIM_ID__/$CLAIM_ID}"
WPCLI post update "$CLAIM_PAGE_ID" --post_content="$CLAIM_CONTENT"

read -r -d '' REGISTER_CONTENT <<'HTML' || true
<!-- wp:paragraph -->
<p>One free account lets you submit sanghas and claim listings on Find a Sangha.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>Already have an account? <a href="/wp-login.php">Sign in</a>.</p>
<!-- /wp:paragraph -->
<!-- wp:shortcode -->
[fluentform id="__REGISTER_ID__"]
<!-- /wp:shortcode -->
HTML
REGISTER_CONTENT="${REGISTER_CONTENT/__REGISTER_ID__/$REGISTER_ID}"
WPCLI post update "$REGISTER_PAGE_ID" --post_content="$REGISTER_CONTENT"

WPCLI cache flush || true
WPCLI eval 'if (has_action("breeze_clear_all_cache")) { do_action("breeze_clear_all_cache"); }' || true
echo "==PAGES WIRED=="
