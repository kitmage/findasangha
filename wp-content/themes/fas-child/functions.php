<?php
/**
 * Find a Sangha child theme.
 * Presentation only. Enqueues the parent block theme stylesheet.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'fas-child-style',
        get_stylesheet_uri(),
        array('twentytwentyfive-style'),
        wp_get_theme()->get('Version')
    );
}, 20);
