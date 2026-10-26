<?php
/**
 * FaS Fluent Forms + FluentCRM integration.
 *
 * Fluent Forms is the front door (forms, entries, spam layers). This module is
 * the brain: it turns Fluent submissions into directory data. FluentCRM only
 * receives contacts when the person explicitly opts in on the form.
 *
 * Form IDs live in options so the forms themselves stay rebuildable from the
 * JSON exports in data/forms/ (see docs/fluent-forms.md):
 *   fas_fluent_register_form_id, fas_fluent_submit_form_id, fas_fluent_claim_form_id
 *
 * Listings created here are always 'pending' (spec 4.3): a human verifies
 * before anything appears in the directory. Verification means existence and
 * currency were checked; it never means endorsement of a teacher.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fas_fluent_form_ids() {
    return array(
        'register' => (int) get_option('fas_fluent_register_form_id', 0),
        'submit'   => (int) get_option('fas_fluent_submit_form_id', 0),
        'claim'    => (int) get_option('fas_fluent_claim_form_id', 0),
    );
}

function fas_fluent_str($data, $key) {
    if (!isset($data[$key]) || is_array($data[$key]) || is_object($data[$key])) {
        return '';
    }
    return trim((string) $data[$key]);
}

function fas_fluent_arr($data, $key) {
    if (!isset($data[$key])) {
        return array();
    }
    $vals = is_array($data[$key]) ? $data[$key] : array($data[$key]);
    $out = array();
    foreach ($vals as $v) {
        if (is_scalar($v) && trim((string) $v) !== '') {
            $out[] = sanitize_text_field((string) $v);
        }
    }
    return $out;
}

function fas_fluent_opted_in($data) {
    return in_array('yes', fas_fluent_arr($data, 'newsletter_optin'), true);
}

/** Simple per-identity rate limit: 5 handled submissions per hour per form. */
function fas_fluent_rate_limited($bucket) {
    $identity = is_user_logged_in() ? 'u' . get_current_user_id() : 'ip' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $key = 'fas_ff_rl_' . $bucket . '_' . md5($identity);
    $count = (int) get_transient($key);
    if ($count >= 5) {
        return true;
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return false;
}

/**
 * Add an explicitly opted-in contact to FluentCRM (list: Find a Sangha
 * Updates; one tag per source). Guarded: a CRM hiccup never breaks a form.
 */
function fas_fluent_crm_optin($email, $first_name, $last_name, $tag_slug, $tag_title) {
    if (!is_email($email) || !function_exists('FluentCrmApi')) {
        return;
    }
    try {
        $list_id = 0;
        if (class_exists('\FluentCrm\App\Models\Lists')) {
            $list = \FluentCrm\App\Models\Lists::firstOrCreate(
                array('slug' => 'find-a-sangha-updates'),
                array('title' => 'Find a Sangha Updates')
            );
            $list_id = $list ? (int) $list->id : 0;
        }
        $tag_id = 0;
        if (class_exists('\FluentCrm\App\Models\Tag')) {
            $tag = \FluentCrm\App\Models\Tag::firstOrCreate(
                array('slug' => $tag_slug),
                array('title' => $tag_title)
            );
            $tag_id = $tag ? (int) $tag->id : 0;
        }
        $data = array(
            'email'      => $email,
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'status'     => 'subscribed',
        );
        if ($list_id) {
            $data['lists'] = array($list_id);
        }
        if ($tag_id) {
            $data['tags'] = array($tag_id);
        }
        FluentCrmApi('contacts')->createOrUpdate($data);
    } catch (\Throwable $e) {
        error_log('FaS FluentCRM opt-in failed: ' . $e->getMessage());
    }
}

function fas_fluent_mail_admin($subject, $message) {
    wp_mail(get_option('admin_email'), '[Find a Sangha] ' . $subject, $message);
}

add_action('fluentform/submission_inserted', 'fas_fluent_on_submission', 20, 3);
function fas_fluent_on_submission($entry_id, $form_data, $form) {
    if (!is_array($form_data) || empty($form) || empty($form->id)) {
        return;
    }
    // Dedupe guard in case both the legacy and current Fluent hooks fire.
    $lock = 'fas_ff_done_' . (int) $entry_id . '_' . (int) $form->id;
    if (get_transient($lock)) {
        return;
    }
    set_transient($lock, 1, HOUR_IN_SECONDS);

    $ids = fas_fluent_form_ids();
    $form_id = (int) $form->id;
    if ($form_id === $ids['register'] && $form_id > 0) {
        fas_fluent_handle_registration($entry_id, $form_data);
    } elseif ($form_id === $ids['submit'] && $form_id > 0) {
        fas_fluent_handle_listing($entry_id, $form_data);
    } elseif ($form_id === $ids['claim'] && $form_id > 0) {
        fas_fluent_handle_claim($entry_id, $form_data);
    }
}

function fas_fluent_handle_registration($entry_id, $data) {
    if (fas_fluent_rate_limited('register')) {
        return;
    }
    $email = sanitize_email(fas_fluent_str($data, 'email'));
    $first = sanitize_text_field(fas_fluent_str($data, 'first_name'));
    $last  = sanitize_text_field(fas_fluent_str($data, 'last_name'));
    if (!is_email($email)) {
        return;
    }
    if (email_exists($email)) {
        // Privacy-safe: the form confirmation reads the same either way.
        fas_fluent_mail_admin('Registration attempt with an existing email',
            "Someone submitted the Create Account form with an existing email: $email\nNo account was created.");
        return;
    }
    $username = sanitize_user(fas_fluent_str($data, 'username'), true);
    if ($username === '' || username_exists($username)) {
        $base = sanitize_user(strtolower(strstr($email, '@', true)), true);
        $username = $base !== '' ? $base : 'sangha-friend';
        if (username_exists($username)) {
            $username .= '-' . wp_rand(100, 999);
        }
    }
    $password = fas_fluent_str($data, 'password');
    if (strlen($password) < 8) {
        $password = wp_generate_password(16);
    }
    $user_id = wp_insert_user(array(
        'user_login'   => $username,
        'user_email'   => $email,
        'user_pass'    => $password,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => trim($first . ' ' . $last) !== '' ? trim($first . ' ' . $last) : $username,
        'role'         => 'subscriber',
    ));
    if (is_wp_error($user_id)) {
        fas_fluent_mail_admin('Registration failed', $user_id->get_error_message() . "\nEmail: $email");
        return;
    }
    wp_mail($email, 'Your Find a Sangha account is ready',
        "Welcome to Find a Sangha.\n\nYour account username is: $username\nSign in at https://findasangha.com/wp-login.php\n\nNext step: submit your sangha at https://findasangha.com/add-your-sangha/\n");
    if (!is_user_logged_in()) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
    }
    if (fas_fluent_opted_in($data)) {
        fas_fluent_crm_optin($email, $first, $last, 'registered-account', 'Registered account');
    }
    fas_fluent_mail_admin('New account created', "New Subscriber account #$user_id ($username, $email) via the Create Account form.");
}

function fas_fluent_handle_listing($entry_id, $data) {
    if (!is_user_logged_in() || fas_fluent_rate_limited('submit')) {
        return;
    }
    $user = wp_get_current_user();
    $title = sanitize_text_field(fas_fluent_str($data, 'sangha_name'));
    if ($title === '') {
        return;
    }
    $city = sanitize_text_field(fas_fluent_str($data, 'city'));
    $website = esc_url_raw(fas_fluent_str($data, 'website_url'));
    $dup_id = fas_find_duplicate($title, $city, $website);

    $post_id = wp_insert_post(array(
        'post_type'    => 'sangha_listing',
        'post_status'  => 'pending',
        'post_title'   => $title,
        'post_content' => sanitize_textarea_field(fas_fluent_str($data, 'description')),
        'post_author'  => $user->ID,
    ), true);
    if (is_wp_error($post_id)) {
        fas_fluent_mail_admin('Listing submission failed', $post_id->get_error_message());
        return;
    }

    $meta = array(
        'website_url'           => $website,
        'contact_email'         => sanitize_email(fas_fluent_str($data, 'contact_email')) ?: $user->user_email,
        'meeting_day'           => sanitize_text_field(fas_fluent_str($data, 'meeting_day')),
        'meeting_time'          => sanitize_text_field(fas_fluent_str($data, 'meeting_time')),
        'meeting_address'       => sanitize_text_field(fas_fluent_str($data, 'meeting_address')),
        'city'                  => $city,
        'teacher_or_facilitator' => sanitize_text_field(fas_fluent_str($data, 'teacher')),
        'affiliation'           => sanitize_text_field(fas_fluent_str($data, 'affiliation')),
        'dana_model'            => sanitize_text_field(fas_fluent_str($data, 'dana_model')),
        'source_url'            => esc_url_raw(fas_fluent_str($data, 'source_url')) ?: $website,
    );
    $tz = fas_fluent_str($data, 'sangha_timezone');
    if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
        $meta['sangha_timezone'] = $tz;
    }
    foreach (array('latitude', 'longitude') as $coord) {
        $v = fas_fluent_str($data, $coord);
        if ($v !== '' && is_numeric($v)) {
            $meta[$coord] = $v;
        }
    }
    foreach ($meta as $k => $v) {
        if ($v !== '' && $v !== null) {
            update_post_meta($post_id, $k, $v);
        }
    }
    update_post_meta($post_id, '_fas_submitted_by', $user->ID);
    update_post_meta($post_id, '_fas_fluent_entry_id', (int) $entry_id);
    if ($dup_id) {
        update_post_meta($post_id, '_fas_potential_duplicate_of', (int) $dup_id);
    }

    $tax_map = array(
        'traditions' => 'tradition',
        'formats'    => 'format',
        'languages'  => 'language',
        'features'   => 'features',
    );
    foreach ($tax_map as $field => $taxonomy) {
        $terms = fas_fluent_arr($data, $field);
        if ($terms) {
            wp_set_object_terms($post_id, $terms, $taxonomy);
        }
    }
    $lang_other = sanitize_text_field(fas_fluent_str($data, 'language_other'));
    if ($lang_other !== '') {
        wp_set_object_terms($post_id, $lang_other, 'language', true);
    }
    $lineage = sanitize_text_field(fas_fluent_str($data, 'lineage'));
    if ($lineage !== '') {
        wp_set_object_terms($post_id, $lineage, 'lineage_or_school');
    }
    $country = sanitize_text_field(fas_fluent_str($data, 'country'));
    if ($country !== '') {
        wp_set_object_terms($post_id, $country, 'country');
    }
    $region = sanitize_text_field(fas_fluent_str($data, 'region'));
    if ($region !== '') {
        wp_set_object_terms($post_id, $region, 'region');
    }

    $admin_msg = "New pending listing #$post_id: $title ($city)\nSubmitted by {$user->user_login} (#{$user->ID}).\nReview: https://findasangha.com/wp-admin/post.php?post=$post_id&action=edit";
    if ($dup_id) {
        $admin_msg .= "\n\nFlag: possible duplicate of listing #$dup_id (" . get_the_title($dup_id) . ").";
    }
    fas_fluent_mail_admin('New listing submission: ' . $title, $admin_msg);
    wp_mail($meta['contact_email'], 'We received your sangha listing',
        "Thank you for submitting $title to Find a Sangha.\n\nEvery listing is checked by hand before it appears, so yours is now in the review queue. We will email you at this address if we need anything else.\n\nVerification means we checked that the sangha exists and its details are current. It does not mean endorsement of a teacher.\n");
    if (fas_fluent_opted_in($data)) {
        fas_fluent_crm_optin($meta['contact_email'], $user->first_name, $user->last_name, 'listing-submitter', 'Listing submitter');
    }
}

function fas_fluent_handle_claim($entry_id, $data) {
    if (!is_user_logged_in() || fas_fluent_rate_limited('claim')) {
        return;
    }
    $user = wp_get_current_user();
    $ref = fas_fluent_str($data, 'listing_ref');
    if ($ref === '') {
        return;
    }
    $listing_id = 0;
    if (filter_var($ref, FILTER_VALIDATE_URL)) {
        $path = trim((string) wp_parse_url($ref, PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        $slug = end($parts);
        $found = get_page_by_path($slug, OBJECT, 'sangha_listing');
        if ($found) {
            $listing_id = (int) $found->ID;
        }
    }
    if (!$listing_id) {
        $matches = get_posts(array(
            'post_type' => 'sangha_listing',
            'post_status' => 'any',
            'posts_per_page' => 1,
            's' => $ref,
        ));
        if ($matches) {
            $listing_id = (int) $matches[0]->ID;
        }
    }
    $claimant_email = sanitize_email(fas_fluent_str($data, 'claimant_email')) ?: $user->user_email;
    $listing_label = $listing_id ? get_the_title($listing_id) : $ref;
    $post_id = wp_insert_post(array(
        'post_type'   => 'fas_claim',
        'post_status' => 'pending',
        'post_title'  => 'Claim: ' . $listing_label . ' (' . $claimant_email . ')',
        'post_content' => sanitize_textarea_field(fas_fluent_str($data, 'message')),
        'post_author' => $user->ID,
    ), true);
    if (is_wp_error($post_id)) {
        fas_fluent_mail_admin('Claim submission failed', $post_id->get_error_message());
        return;
    }
    update_post_meta($post_id, '_fas_listing_id', $listing_id);
    update_post_meta($post_id, '_fas_listing_ref', sanitize_text_field($ref));
    update_post_meta($post_id, '_fas_claimant_name', sanitize_text_field(fas_fluent_str($data, 'claimant_name')));
    update_post_meta($post_id, '_fas_claimant_email', $claimant_email);
    update_post_meta($post_id, '_fas_claimant_role', sanitize_text_field(fas_fluent_str($data, 'claimant_role')));
    update_post_meta($post_id, '_fas_evidence_url', esc_url_raw(fas_fluent_str($data, 'evidence_url')));
    update_post_meta($post_id, '_fas_submitted_by', $user->ID);
    update_post_meta($post_id, '_fas_fluent_entry_id', (int) $entry_id);

    fas_fluent_mail_admin('New listing claim: ' . $listing_label,
        "Claim #$post_id for listing " . ($listing_id ? "#$listing_id $listing_label" : "(unmatched) $ref") . "\nClaimant: $claimant_email\nReview: https://findasangha.com/wp-admin/post.php?post=$post_id&action=edit");
    wp_mail($claimant_email, 'We received your claim',
        "Thank you for claiming the listing \"$listing_label\" on Find a Sangha.\n\nClaims are reviewed by hand. We may verify your connection to the sangha by email at the address on the listing's own website, or by a link placed on that site. We will email you at this address with the outcome.\n");
    if (fas_fluent_opted_in($data)) {
        $name_parts = explode(' ', sanitize_text_field(fas_fluent_str($data, 'claimant_name')), 2);
        fas_fluent_crm_optin($claimant_email, $name_parts[0] ?? '', $name_parts[1] ?? '', 'listing-claimant', 'Listing claimant');
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fas fluent-status', function () {
        $ids = fas_fluent_form_ids();
        global $wpdb;
        foreach ($ids as $kind => $fid) {
            $title = $fid ? $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}fluentform_forms WHERE id = %d", $fid)) : null;
            WP_CLI::log("$kind: form #" . ($fid ?: 'unset') . ($title ? " ($title)" : ''));
        }
    });
}
