<?php
/**
 * SVG upload support (enabled at Mike's request 2026-10-26, for the site logo).
 *
 * Scope and safety: this only widens the allowed mime list for users who can
 * already upload media (administrators and editors on this site). SVGs are
 * XML and can carry scripts, so treat uploaded SVGs as trusted files only:
 * never accept them from untrusted submitters. The public forms do not accept
 * file uploads, so this does not widen the public attack surface.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('upload_mimes', function ($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
});

// WordPress's filetype check rejects SVG without this (it cannot read an
// image size for vector files), which would otherwise block the upload even
// with the mime allowed.
add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes) {
    if (substr($filename, -4) === '.svg') {
        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';
    }
    return $data;
}, 10, 4);

// SVGs have no generated thumbnails; give them a sane display size in the
// media library grid so they do not render at their intrinsic (huge) size.
add_action('admin_head', function () {
    echo '<style>.attachment-info .thumbnail img[src$=".svg"], .media-icon img[src$=".svg"] { width: 100%; height: auto; }</style>';
});
