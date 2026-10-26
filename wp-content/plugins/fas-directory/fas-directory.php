<?php
/**
 * Plugin Name: Find a Sangha Directory
 * Plugin URI: https://findasangha.com
 * Description: Custom directory for findasangha.com. Owns the sangha listing data model (post types, taxonomies, meta) in code, so a fresh install plus this plugin reproduces the directory.
 * Version: 0.4.1
 * Author: Find a Sangha
 * License: GPL-2.0-or-later
 * Text Domain: fas-directory
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FAS_DIRECTORY_VERSION', '0.4.1');

require_once plugin_dir_path(__FILE__) . 'includes/data-model.php';
require_once plugin_dir_path(__FILE__) . 'includes/display.php';
require_once plugin_dir_path(__FILE__) . 'includes/importer.php';
require_once plugin_dir_path(__FILE__) . 'includes/fluent.php';

register_activation_hook(__FILE__, function () {
    fas_register_post_types();
    fas_register_taxonomies();
    fas_seed_taxonomy_terms();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
