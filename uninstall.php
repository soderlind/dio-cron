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

// Delete the options and transients set by the plugin.
delete_site_transient( 'dio_cron_sites' );
flush_rewrite_rules();