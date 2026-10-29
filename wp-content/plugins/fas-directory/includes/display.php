<?php
/**
 * FaS display layer: listing details, cards, filter form, filtered results.
 * Search/filter basics for Phase 1. Map view arrives with the Leaflet build.
 */

if (!defined('ABSPATH')) {
    exit;
}

function fas_filter_params() {
    return array(
        'keyword' => isset($_GET['f_keyword']) ? sanitize_text_field(wp_unslash($_GET['f_keyword'])) : '',
        'location' => isset($_GET['f_location']) ? sanitize_text_field(wp_unslash($_GET['f_location'])) : '',
        'tradition' => isset($_GET['f_tradition']) ? sanitize_text_field(wp_unslash($_GET['f_tradition'])) : '',
        'format' => isset($_GET['f_format']) ? sanitize_text_field(wp_unslash($_GET['f_format'])) : '',
        'language' => isset($_GET['f_language']) ? sanitize_text_field(wp_unslash($_GET['f_language'])) : '',
        'country' => isset($_GET['f_country']) ? sanitize_text_field(wp_unslash($_GET['f_country'])) : '',
    );
}

function fas_tax_query_from_params($p) {
    $map = array(
        'tradition' => 'tradition',
        'format' => 'format',
        'language' => 'language',
        'country' => 'country',
    );
    $tax_query = array();
    foreach ($map as $key => $taxonomy) {
        if (!empty($p[$key])) {
            $tax_query[] = array(
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $p[$key],
            );
        }
    }
    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    return $tax_query;
}

// Location search: free text matched against the city and address meta, and
// against region and country term names. Returns listing IDs (possibly empty).
function fas_location_post_ids($loc) {
    $ids = array();
    $meta_q = new WP_Query(array(
        'post_type' => 'sangha_listing',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => 'city', 'value' => $loc, 'compare' => 'LIKE'),
            array('key' => 'meeting_address', 'value' => $loc, 'compare' => 'LIKE'),
        ),
    ));
    $ids = array_merge($ids, $meta_q->posts);
    foreach (array('region', 'country') as $taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false, 'name__like' => $loc));
        if (is_wp_error($terms) || !$terms) {
            continue;
        }
        $tax_q = new WP_Query(array(
            'post_type' => 'sangha_listing',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => array(array(
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => wp_list_pluck($terms, 'term_id'),
            )),
        ));
        $ids = array_merge($ids, $tax_q->posts);
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

// Restrict a query (WP_Query args array or WP_Query object setter) to the
// listings matching the location parameter. Empty array means "no matches".
function fas_location_ids_for_params($p) {
    if ($p['location'] === '') {
        return null;
    }
    $ids = fas_location_post_ids($p['location']);
    return $ids ? $ids : array(0);
}

// Archive filtering: the /sanghas/ archive honors the same filter form.
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_post_type_archive('sangha_listing')) {
        return;
    }
    $p = fas_filter_params();
    if ($p['keyword'] !== '') {
        $query->set('s', $p['keyword']);
    }
    $loc_ids = fas_location_ids_for_params($p);
    if ($loc_ids !== null) {
        $query->set('post__in', $loc_ids);
    }
    $tax_query = fas_tax_query_from_params($p);
    if (!empty($tax_query)) {
        $query->set('tax_query', $tax_query);
    }
    $query->set('posts_per_page', 20);
});

function fas_term_names($post_id, $taxonomy) {
    $terms = get_the_terms($post_id, $taxonomy);
    if (!$terms || is_wp_error($terms)) {
        return array();
    }
    return wp_list_pluck($terms, 'name');
}

function fas_location_line($post_id) {
    $city = get_post_meta($post_id, 'city', true);
    $region_terms = fas_term_names($post_id, 'region');
    $country_terms = fas_term_names($post_id, 'country');
    $bits = array_filter(array($city, $region_terms ? $region_terms[0] : '', $country_terms ? $country_terms[0] : ''));
    $formats = fas_term_names($post_id, 'format');
    if (in_array('Online', $formats, true) && empty($bits)) {
        return 'Online';
    }
    return implode(', ', $bits);
}

function fas_chips($post_id, $taxonomies) {
    $out = '';
    foreach ($taxonomies as $taxonomy) {
        $terms = get_the_terms($post_id, $taxonomy);
        if (!$terms || is_wp_error($terms)) {
            continue;
        }
        foreach ($terms as $term) {
            $out .= '<span class="fas-chip fas-chip-' . esc_attr($taxonomy) . '">' . esc_html($term->name) . '</span> ';
        }
    }
    return $out ? '<div class="fas-chips">' . $out . '</div>' : '';
}

// Card meta, used inside the archive query loop and the results shortcode.
function fas_card_html($post_id) {
    $loc = fas_location_line($post_id);
    $html = '';
    if ($loc !== '') {
        $html .= '<p class="fas-card-location">' . esc_html($loc) . '</p>';
    }
    $html .= fas_chips($post_id, array('tradition', 'format'));
    $confirmed = get_post_meta($post_id, 'last_confirmed_date', true);
    if ($confirmed) {
        $ts = strtotime($confirmed);
        $label = $ts ? date_i18n('F j, Y', $ts) : $confirmed;
        $html .= '<p class="fas-card-confirmed">Last confirmed ' . esc_html($label) . '</p>';
    }
    return $html;
}

// Published listing count, for the home page trust copy.
add_shortcode('fas_listing_count', function () {
    $counts = wp_count_posts('sangha_listing');
    return $counts ? (string) intval($counts->publish) : '0';
});
add_shortcode('fas_card_meta', function () {
    global $post;
    return $post ? fas_card_html($post->ID) : '';
});

// Full details block for the single listing template.
add_shortcode('fas_listing_details', function () {
    global $post;
    if (!$post || $post->post_type !== 'sangha_listing') {
        return '';
    }
    $id = $post->ID;
    $meta = function ($key) use ($id) { return get_post_meta($id, $key, true); };
    $rows = array();
    $loc = fas_location_line($id);
    if ($loc !== '') {
        $rows['Location'] = $loc;
    }
    if ($meta('meeting_address')) {
        $rows['Address'] = $meta('meeting_address');
    }
    if ($meta('website_url')) {
        $rows['Website'] = '<a href="' . esc_url($meta('website_url')) . '" rel="noopener">' . esc_html($meta('website_url')) . '</a>';
    }
    if ($meta('teacher_or_facilitator')) {
        $rows['Teacher / facilitator'] = $meta('teacher_or_facilitator');
    }
    if ($meta('affiliation')) {
        $rows['Affiliation'] = $meta('affiliation');
    }
    if ($meta('meeting_day') || $meta('meeting_time')) {
        $rows['Meets'] = trim($meta('meeting_day') . ' ' . $meta('meeting_time'));
    }
    if ($meta('sangha_timezone')) {
        $rows['Timezone'] = $meta('sangha_timezone');
    }
    if ($meta('dana_model')) {
        $rows['Cost'] = $meta('dana_model');
    }
    $langs = fas_term_names($id, 'language');
    if ($langs) {
        $rows['Languages'] = implode(', ', $langs);
    }
    $feats = fas_term_names($id, 'features');
    if ($feats) {
        $rows['Features'] = implode(', ', $feats);
    }
    $html = '<section class="fas-details" aria-label="Listing details">';
    if ($meta('verified_date')) {
        $html .= '<p class="fas-verified">Verified ' . esc_html($meta('verified_date')) . '. Verified means this listing existed and its details were current when checked. It does not mean endorsement of a teacher.</p>';
    } elseif ($meta('last_confirmed_date')) {
        $html .= '<p class="fas-confirmed">Last confirmed ' . esc_html($meta('last_confirmed_date')) . '</p>';
    }
    if ($rows) {
        $html .= '<dl class="fas-details-list">';
        foreach ($rows as $label => $value) {
            $html .= '<div class="fas-detail"><dt>' . esc_html($label) . '</dt><dd>' . wp_kses_post($value) . '</dd></div>';
        }
        $html .= '</dl>';
    }
    $html .= fas_chips($id, array('tradition', 'format'));
    if ($meta('source_url')) {
        $html .= '<p class="fas-source">Source: <a href="' . esc_url($meta('source_url')) . '" rel="noopener">' . esc_html($meta('source_url')) . '</a></p>';
    }
    $html .= '</section>';
    return $html;
});

// Filter form. Defaults to the archive; pass target="browse" on the Browse page.
add_shortcode('fas_filter_form', function ($atts) {
    $atts = shortcode_atts(array('target' => ''), $atts);
    $action = $atts['target'] === 'browse'
        ? get_permalink(get_page_by_path('browse-sanghas'))
        : get_post_type_archive_link('sangha_listing');
    $p = fas_filter_params();
    $select = function ($name, $taxonomy, $label) use ($p) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
        $html = '<label class="fas-filter-field"><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '">';
        $html .= '<option value="">Any</option>';
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $html .= '<option value="' . esc_attr($term->slug) . '"' . selected($p[str_replace('f_', '', $name)], $term->slug, false) . '>' . esc_html($term->name) . '</option>';
            }
        }
        return $html . '</select></label>';
    };
    $html = '<form class="fas-filter" method="get" action="' . esc_url($action) . '" role="search">';
    $html .= '<label class="fas-filter-field fas-filter-keyword"><span>Keyword</span><input type="search" name="f_keyword" value="' . esc_attr($p['keyword']) . '" placeholder="Name or teacher..."></label>';
    $html .= '<label class="fas-filter-field fas-filter-location"><span>Location</span><input type="search" name="f_location" value="' . esc_attr($p['location']) . '" placeholder="City, state, or country"></label>';
    $html .= $select('f_tradition', 'tradition', 'Tradition');
    $html .= $select('f_format', 'format', 'Format');
    $html .= $select('f_language', 'language', 'Language');
    $html .= '<button type="submit" class="fas-filter-submit">Search sanghas</button>';
    if (array_filter($p)) {
        $html .= '<a class="fas-filter-clear" href="' . esc_url($action) . '">Clear filters</a>';
    }
    $html .= '</form>';
    return $html;
});

// Filtered results for the Browse page and the /sanghas/ archive template.
// (The archive uses this instead of a query loop: a shortcode inside a block
// query loop does not receive each iteration's post, so card meta went stale.)
add_shortcode('fas_results', function ($atts) {
    $atts = shortcode_atts(array('limit' => 0, 'orderby' => ''), is_array($atts) ? $atts : array());
    $limit = max(0, intval($atts['limit']));
    $by_confirmed = $atts['orderby'] === 'confirmed';
    $p = fas_filter_params();
    $on_archive = is_post_type_archive('sangha_listing');
    $page = $on_archive ? max(1, intval(get_query_var('paged'))) : (isset($_GET['fpage']) ? max(1, intval($_GET['fpage'])) : 1);
    $args = array(
        'post_type' => 'sangha_listing',
        'post_status' => 'publish',
        'posts_per_page' => $by_confirmed ? -1 : ($limit > 0 ? $limit : 20),
        'paged' => $limit > 0 ? 1 : $page,
    );
    if ($p['keyword'] !== '') {
        $args['s'] = $p['keyword'];
    }
    $loc_ids = fas_location_ids_for_params($p);
    if ($loc_ids !== null) {
        $args['post__in'] = $loc_ids;
    }
    $tax_query = fas_tax_query_from_params($p);
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }
    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        wp_reset_postdata();
        $online = get_post_type_archive_link('sangha_listing') . '?f_format=online';
        return '<div class="fas-no-results"><p>No sanghas match those filters yet.</p><p>Try <a href="' . esc_url($online) . '">online sanghas</a>, or <a href="' . esc_url(home_url('/add-your-sangha/')) . '">add this sangha</a> if you know one that belongs here.</p></div>';
    }
    if ($by_confirmed) {
        // Sort the fetched posts by last confirmed date (newest first, undated
        // last) and let the normal query loop walk them in that order.
        // Note: iterate with $q->the_post(); a bare setup_postdata() loop does
        // not populate the globals reliably on this install (verified 2026-10-26).
        usort($q->posts, function ($a, $b) {
            $da = get_post_meta($a->ID, 'last_confirmed_date', true) ?: '';
            $db = get_post_meta($b->ID, 'last_confirmed_date', true) ?: '';
            return strcmp((string) $db, (string) $da);
        });
        if ($limit > 0) {
            $q->posts = array_slice($q->posts, 0, $limit);
            $q->post_count = count($q->posts);
        }
    }
    $html = '<div class="fas-results">';
    while ($q->have_posts()) {
        $q->the_post();
        $id = get_the_ID();
        $html .= '<article class="fas-card"><h3 class="fas-card-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
        $html .= fas_card_html($id);
        $html .= '<p class="fas-card-excerpt">' . esc_html(get_the_excerpt()) . '</p></article>';
    }
    $html .= '</div>';
    $total = $q->max_num_pages;
    if ($total > 1 && $limit === 0) {
        $filter_args = array_filter(array(
            'f_keyword' => $p['keyword'], 'f_location' => $p['location'], 'f_tradition' => $p['tradition'], 'f_format' => $p['format'],
            'f_language' => $p['language'], 'f_country' => $p['country']));
        $html .= '<nav class="fas-pagination">';
        for ($i = 1; $i <= $total; $i++) {
            if ($on_archive) {
                $base = get_post_type_archive_link('sangha_listing') . ($i > 1 ? 'page/' . $i . '/' : '');
                $url = add_query_arg($filter_args, $base);
            } else {
                $base = get_permalink(get_page_by_path('browse-sanghas'));
                $url = add_query_arg(array_merge($filter_args, array('fpage' => $i)), $base);
            }
            $html .= $i === $page ? '<span class="fas-page-current">' . $i . '</span> ' : '<a href="' . esc_url($url) . '">' . $i . '</a> ';
        }
        $html .= '</nav>';
    }
    wp_reset_postdata();
    return $html;
});
