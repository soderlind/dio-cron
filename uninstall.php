<?php
/**
 * Uninstall script.
 *
 * @package DIO_Cron
 */

// If uninstall.php is not called by WordPress, abort.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) && ! is_multisite() ) {
	return;
}

// Load utilities for cleanup.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-utilities.php';

// Delete the options and transients set by the plugin.
\Soderlind\Multisite\Cron\DIO_Cron_Utilities::clear_sites_cache();
flush_rewrite_rules();
