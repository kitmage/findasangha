<?php
/**
 * FaS CSV importer. Format: docs/import-format.md.
 * WP-CLI first per the settled architecture: wp fas import <file> [--dry-run] [--status=draft|publish]
 * Duplicate = same title AND same city, or same website domain (spec 4.7).
 */

if (!defined('ABSPATH')) {
    exit;
}

function fas_import_meta_columns() {
    return array(
        'website_url', 'contact_email', 'meeting_day', 'meeting_time', 'sangha_timezone',
        'meeting_address', 'city', 'latitude', 'longitude', 'teacher_or_facilitator',
        'affiliation', 'dana_model', 'source_url', 'last_confirmed_date', 'verified_date',
    );
}

function fas_import_tax_columns() {
    return array(
        'traditions' => 'tradition',
        'formats' => 'format',
        'languages' => 'language',
        'features' => 'features',
        'countries' => 'country',
        'regions' => 'region',
        'lineages' => 'lineage_or_school',
    );
}

function fas_website_domain($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) {
        $host = wp_parse_url('https://' . $url, PHP_URL_HOST);
    }
    return $host ? strtolower(preg_replace('/^www\./', '', $host)) : '';
}

function fas_find_duplicate($title, $city, $website_url) {
    $domain = fas_website_domain($website_url);
    $posts = get_posts(array(
        'post_type' => 'sangha_listing',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ));
    foreach ($posts as $pid) {
        $existing_title = get_the_title($pid);
        $existing_city = get_post_meta($pid, 'city', true);
        if (strcasecmp($existing_title, $title) === 0 && strcasecmp($existing_city, (string) $city) === 0) {
            return $pid;
        }
        if ($domain !== '' && fas_website_domain(get_post_meta($pid, 'website_url', true)) === $domain) {
            return $pid;
        }
    }
    return 0;
}

function fas_import_row($row, $default_status, $dry_run) {
    $title = isset($row['title']) ? trim($row['title']) : '';
    if ($title === '') {
        return array('result' => 'error', 'message' => 'missing title');
    }
    $city = isset($row['city']) ? trim($row['city']) : '';
    $dup = fas_find_duplicate($title, $city, isset($row['website_url']) ? $row['website_url'] : '');
    if ($dup) {
        return array('result' => 'duplicate', 'id' => $dup, 'message' => "matches existing listing $dup");
    }
    if ($dry_run) {
        return array('result' => 'would-create', 'message' => $title);
    }
    $status = isset($row['status']) && in_array($row['status'], array('draft', 'publish'), true) ? $row['status'] : $default_status;
    $post_id = wp_insert_post(array(
        'post_type' => 'sangha_listing',
        'post_status' => $status,
        'post_title' => $title,
        'post_content' => isset($row['description']) ? $row['description'] : '',
        'post_excerpt' => isset($row['excerpt']) ? $row['excerpt'] : '',
    ), true);
    if (is_wp_error($post_id)) {
        return array('result' => 'error', 'message' => $post_id->get_error_message());
    }
    foreach (fas_import_meta_columns() as $col) {
        if (!empty($row[$col])) {
            $value = $col === 'website_url' || $col === 'source_url'
                ? esc_url_raw(trim($row[$col]))
                : sanitize_text_field(trim($row[$col]));
            if (($col === 'latitude' || $col === 'longitude') && !is_numeric($value)) {
                continue;
            }
            update_post_meta($post_id, $col, $value);
        }
    }
    foreach (fas_import_tax_columns() as $col => $taxonomy) {
        if (!empty($row[$col])) {
            $terms = array_filter(array_map('trim', explode(';', $row[$col])));
            if ($terms) {
                wp_set_object_terms($post_id, $terms, $taxonomy);
            }
        }
    }
    return array('result' => 'created', 'id' => $post_id, 'message' => $title);
}

function fas_import_csv_file($path, $default_status = 'draft', $dry_run = false) {
    $handle = fopen($path, 'r');
    if (!$handle) {
        return array('error' => "cannot open $path");
    }
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return array('error' => 'empty CSV');
    }
    $header = array_map(function ($h) { return strtolower(trim($h)); }, $header);
    $results = array();
    $counts = array('created' => 0, 'would-create' => 0, 'duplicate' => 0, 'error' => 0);
    $line = 1;
    while (($data = fgetcsv($handle)) !== false) {
        $line++;
        if (count($data) === 1 && trim((string) $data[0]) === '') {
            continue;
        }
        $row = array();
        foreach ($header as $i => $col) {
            $row[$col] = isset($data[$i]) ? $data[$i] : '';
        }
        $r = fas_import_row($row, $default_status, $dry_run);
        $r['line'] = $line;
        $results[] = $r;
        if (isset($counts[$r['result']])) {
            $counts[$r['result']]++;
        }
    }
    fclose($handle);
    return array('counts' => $counts, 'results' => $results);
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('fas import', function ($args, $assoc) {
        $file = $args[0];
        $dry_run = isset($assoc['dry-run']);
        $status = isset($assoc['status']) && $assoc['status'] === 'publish' ? 'publish' : 'draft';
        $out = fas_import_csv_file($file, $status, $dry_run);
        if (isset($out['error'])) {
            WP_CLI::error($out['error']);
        }
        foreach ($out['results'] as $r) {
            WP_CLI::log("line {$r['line']}: {$r['result']} — {$r['message']}");
        }
        WP_CLI::success(sprintf(
            'created=%d would-create=%d duplicates=%d errors=%d%s',
            $out['counts']['created'], $out['counts']['would-create'],
            $out['counts']['duplicate'], $out['counts']['error'],
            $dry_run ? ' (dry run, nothing written)' : ''
        ));
    });
}
