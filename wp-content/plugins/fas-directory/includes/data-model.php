<?php
/**
 * FaS data model: post types, taxonomies, meta fields, seed terms.
 * Source of truth for the directory structure. See SCAFFOLD_AND_PLUGIN_SPEC.md section 4.1.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fas_register_post_types() {
    register_post_type('sangha_listing', array(
        'labels' => array(
            'name' => 'Sangha Listings',
            'singular_name' => 'Sangha Listing',
            'add_new_item' => 'Add New Sangha Listing',
            'edit_item' => 'Edit Sangha Listing',
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'sanghas'),
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-groups',
    ));

    register_post_type('fas_review', array(
        'labels' => array(
            'name' => 'Listing Reviews',
            'singular_name' => 'Listing Review',
        ),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-star-half',
    ));

    register_post_type('fas_claim', array(
        'labels' => array(
            'name' => 'Listing Claims',
            'singular_name' => 'Listing Claim',
        ),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'custom-fields'),
        'show_in_rest' => false,
        'menu_icon' => 'dashicons-flag',
    ));
}
add_action('init', 'fas_register_post_types');

function fas_taxonomy_defs() {
    return array(
        'tradition' => array('Traditions', 'Tradition', true),
        'lineage_or_school' => array('Lineages / Schools', 'Lineage / School', true),
        'format' => array('Formats', 'Format', false),
        'language' => array('Languages', 'Language', false),
        'country' => array('Countries', 'Country', true),
        'region' => array('Regions / States', 'Region / State', true),
        'features' => array('Features', 'Feature', false),
    );
}

function fas_register_taxonomies() {
    foreach (fas_taxonomy_defs() as $slug => $def) {
        list($plural, $singular, $hierarchical) = $def;
        register_taxonomy($slug, 'sangha_listing', array(
            'labels' => array('name' => $plural, 'singular_name' => $singular),
            'public' => true,
            'hierarchical' => $hierarchical,
            'show_in_rest' => true,
            'rewrite' => array('slug' => $slug),
        ));
    }
}
add_action('init', 'fas_register_taxonomies');

function fas_seed_terms() {
    return array(
        'tradition' => array(
            'Theravada', 'Mahayana', 'Vajrayana', 'Zen / Chan / Seon / Thien',
            'Pure Land', 'Nichiren', 'Insight / Vipassana', 'Plum Village tradition',
            'Triratna', 'Non-sectarian', 'Other',
        ),
        'format' => array('Online', 'In person', 'Hybrid'),
        'language' => array('English', 'Vietnamese', 'Spanish'),
        'features' => array(
            'Beginner friendly', 'Teacher led', 'Peer led', 'Retreats offered',
            'Family friendly', 'LGBTQ+ welcoming', 'Wheelchair accessible',
            'Young adult group', 'Prison or hospital outreach',
        ),
    );
}

function fas_seed_taxonomy_terms() {
    foreach (fas_seed_terms() as $taxonomy => $terms) {
        foreach ($terms as $term) {
            if (!term_exists($term, $taxonomy)) {
                wp_insert_term($term, $taxonomy);
            }
        }
    }
}
// Also seed on init (idempotent) so terms exist even if activation ran before this code.
add_action('init', 'fas_seed_taxonomy_terms', 20);

function fas_meta_fields() {
    // Verification means existence and currency were checked. It never means endorsement of a teacher.
    return array(
        'website_url', 'contact_email', 'meeting_day', 'meeting_time', 'sangha_timezone',
        'meeting_address', 'city', 'latitude', 'longitude',
        'teacher_or_facilitator', 'affiliation', 'dana_model',
        'claimed_by', 'claimed_date', 'verified_date', 'last_confirmed_date', 'reconfirm_due_date',
        'is_featured', 'featured_until', 'featured_region', 'source_url',
    );
}

function fas_register_meta() {
    foreach (fas_meta_fields() as $key) {
        register_post_meta('sangha_listing', $key, array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }
}
add_action('init', 'fas_register_meta');
